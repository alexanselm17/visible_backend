# NCBA payments: fixed account and customer receipt claims

Customers pay manually through M-Pesa using **Paybill 880100**, **account 906854**, and the purchase's exact saved amount. They do NOT append a purchase reference. The signed-in customer then submits their M-Pesa receipt number against their own purchase.

This does not initiate STK, collect M-Pesa PINs, read SMS, accept screenshots as bank evidence, issue refunds or provide a cash wallet. A receipt number alone is not proof of payment or ownership.

## Deploy and configure

Back up the database, test on staging, migrate before serving this version and restart old workers. Run from the backend checkout:

```sh
php artisan migrate
php artisan optimize:clear
```

The November 18 migration adds the original bank ledger/internal purchase codes. November 19 adds receipt mutexes, customer claims, ordered audit events and encrypted validated bank fields. Both preserve financial records on rollback and support rerunning. Existing balances, historical receipts, quantities and prices are not rewritten. `payment_code` remains an INTERNAL support reference, not a Paybill account number.

Configure privately on the server:

```dotenv
NCBA_ENABLED=false
NCBA_TILL_ALIAS=906854
NCBA_NOTIFICATION_USERNAME=
NCBA_NOTIFICATION_PASSWORD=
NCBA_NOTIFICATION_SECRET=
NCBA_REQUIRE_HTTPS=true
NCBA_ALLOWED_IPS=
NCBA_AUTO_CREDIT_ENABLED=false
NCBA_CONTRACT_VERIFIED=false
NCBA_CLAIM_WAIT_HOURS=48
```

The default alias is `906854`; an existing environment value overrides it, so verify it during deployment. Never put bank credentials in Flutter, Git, chats or public admin settings. Do not reuse APP_KEY or staff passwords as bank credentials.

Ask NCBA to enable option 3 JSON API notifications and register:

```text
https://YOUR_BACKEND_DOMAIN/api/v1/payments/ncba/notifications
```

The callback does not use a member bearer/CSRF token. NCBA supplies configured Username, Password and Hash. Require HTTPS and configure trusted proxies correctly. Optionally allow only bank-confirmed IPs/CIDRs. Keep auto-credit gates false during UAT. Enable ingestion only after credentials/callback registration are ready, then explicitly enable both NCBA_CONTRACT_VERIFIED and NCBA_AUTO_CREDIT_ENABLED after genuine bank tests pass. Admin UI cannot change/reveal those server secrets/gates.

## Owner API / Flutter flow

All purchase/claim endpoints require an existing bearer session and active user. The owner ID comes from that session, not request input.

1. `POST /api/v1/tokens/purchases` with `{"token_type":"PLATINUM","quantity":5}`. The server saves owner, token type, quantity, exact unit price, total and currency. Image/video/text wallets remain separate.
2. Display `data.payment_instructions.paybill`, `account_number`, `amount` and `message`. The account is exactly `906854` with the configuration above. Instructions include `confirmation_method: "RECEIPT_CLAIM"` and `receipt_submission_url`. Instructions are null for disabled/incomplete bank setup or a no-longer-payable/non-KES purchase.
3. The customer pays manually and obtains their M-Pesa transaction receipt number.
4. Submit the receipt (not FTRef, SMS text, screenshot or account number):

```http
POST /api/v1/tokens/purchases/{purchaseId}/receipt
Authorization: Bearer EXISTING_SESSION_TOKEN
Content-Type: application/json

{"receipt_number":"YOUR_MPESA_RECEIPT"}
```

Receipts accept 1–32 alphanumeric characters and normalize case/outer whitespace. The backend checks purchase ownership before looking up bank data. Follow the actual bank receipt format in mobile input guidance.

- **200 / APPROVED:** payment allocated and purchase PAID, or idempotent retry of that allocation.
- **202 / WAITING_BANK:** claim saved; waiting for NCBA callback. This is NOT payment success.
- **202 / REVIEW:** administrator action needed; no additional tokens credited.
- **409 / REJECTED:** claim not approved/purchase not payable; retained in history.
- **401/403/404:** authentication, inactive/unauthorized or non-owned/not-found purchase.
- **422:** invalid receipt or excessive receipt changes; **429:** rate limit, respect Retry-After.

Response `data.claim` has safe claimant fields and `data.purchase` the refreshed order. Do not infer payment success from `ok: true` alone; 202 only accepts a claim.

5. Poll `GET /api/v1/tokens/purchases/{purchaseId}` with backoff and a bounded waiting window; refresh when the app resumes. Detail/list endpoints include `receipt_claim` and `receipt_claims`. The PAID purchase's primary claim stays the approved one even if a later invalid attempt is recorded. Other owners get 404, not bank/payer details.
6. On PAID, refresh the normal wallet endpoint. Owner gets an in-app success notification. Active admins/developers get durable approval updates in **Overview → Payment updates for you**. No SMS/email/FCM is sent. This repository contains no Flutter project; update the mobile screen to call the endpoint and display these statuses.

## Automatic approval requirements

Every condition must pass:

- Actual bank-authenticated successful receipt for Paybill 880100 and configured receiving alias; ingestion/UAT/auto-credit gates enabled.
- Open owner claim on an active existing owner's PENDING purchase.
- Exact saved amount/currency. Saved unit price × quantity must agree with total. Quotes/matching use integer minor units, not floating-point comparisons.
- Claim-time owner/type/quantity/price/currency snapshot still matches purchase. Later token-price changes do not affect saved orders.
- Signed bank payer phone matches the claimant's verified submission-time AND unchanged current verified phone. Kenyan local/country-code forms are normalized. Changing phone clears verification.
- Only one active verified account for equivalent phone formats. Copied receipt numbers alone cannot take another payer's payment. Hashed/masked/non-normalizable bank phones or third-party payers require admin ownership review; no hash algorithm is guessed.
- Bank time not materially before purchase creation or in the future (five-minute tolerance); phone verification not materially after payment.
- One eligible open purchase for the receipt. Multiple eligible orders are reviewed, not guessed.
- No conflicting deliveries, prior allocation/resolution, another order using the receipt, or previous staff rejection for this receipt/purchase.

Legitimate third-party, older-payment and timing exceptions can be reconciled by finance staff with ownership evidence/notes. Do not remove ownership checks merely because a receipt exists.

## Arrival order, retries and exceptions

- **Callback first:** persist/acknowledge verified bank receipt, await explicit owner claim. Narrative, phone, name and amount alone never select a purchase.
- **Claim first:** save WAITING_BANK. Callback automatically reconciles after verification. Durable receipt mutexes serialize both paths even before a bank row exists.
- **Repeated claim:** same latest receipt/snapshot increments count and appends an event, without another credit or resetting waiting deadline. Changed phone verification produces a new numbered attempt/snapshot.
- **Receipt corrected:** retain previous attempts as SUPERSEDED. Their later callbacks stay in review, not attached to the new claim.
- **Copied/used receipt:** no transfer or second credit. A first unverified claimant cannot reserve another owner's payment.
- **Cancelled/failed/refunded/inactive/deleted-owner, changed order, wrong currency, under/overpayment:** no automatic credit. No splitting, aggregation, excess-payment extra tokens or automatic refund.
- **Delayed callback:** after 48 hours (configurable), command flags an open claim for review and alerts admins once per state change. It does not expire bank evidence or invent confirmation. Later valid callbacks can still approve eligible open claims.
- **Staff rejection:** saves actor/reason/history and sends owner an attention notice. Repeating or replacing an attempt cannot bypass a staff rejection of this receipt for this purchase.
- **Conflict:** preserve original bank fields; record conflict digest/count and alert admins. Do not overwrite/reassign/recredit or automatically reverse existing tokens.
- **Storage failure:** callback returns 503 for retry. Receipt, wallet credit, purchase, transaction, decisions and notifications commit atomically. A customer claim is committed before separate reconciliation, so reconciliation failure does not lose the submission.

Valid stored callbacks return `{"ResultCode":"0","ResultDesc":"Notification received."}` even for review. This is receipt acknowledgement, not token approval. Callback errors distinguish authentication 401, schema 422, HTTPS/IP 403, content type 415, size 413, conflict 409, throttle 429 and temporary storage/setup 503. Confirm bank retry handling in UAT.

## Admin and clean history

Finance is restricted to active admins/developers, not managers/members.

- **Tokens → Payment receipt claims:** filter owner/status; inspect numbered attempts, encrypted purchase/verified-phone snapshots, repeat count, reason and ordered events. Open bank inbox or reject with mandatory notes. No edit/delete of history.
- **Tokens → NCBA payments:** inspect receipt/FTRef, amount, time, encrypted payer fields, original validated callback fields (credentials excluded), delivery/conflict counts, allocation and full receipt-related history.
- **Match & credit tokens:** select the verified owner's exact-amount eligible order, confirm ownership and record notes. Server revalidates, approves matching open claim(s), closes competing claims, credits correct wallet once, notifies owner/admins. Repeating allocation adds no credit/approval notification.
- **Resolve without tokens:** record an external reconciliation/refund already completed, with notes; closes open claims. It DOES NOT transfer/refund money. Bank reversals require the verified finance procedure; no automatic bank reversal/debit is implemented.
- **Overview → Payment updates for you:** unread approvals/review/conflicts refresh every 30 seconds. Admin marks only their own updates read. Deleting a notification does not delete financial history.

Append-only events have ordered numeric sequence, UUID, actor/source, receipt/purchase/claim/payment links, reason, notes and monetary snapshots. All accepted callbacks/retries/conflicts and well-formed owned claims/resubmissions/decisions are recorded. Invalid/unauthorized/throttled requests do not become financial claims. Model guards/admin restrictions do not replace database access controls and backups.

Validated bank fields preserve original amount/time strings/payer values encrypted with APP_KEY. Claim phone/purchase snapshots are encrypted too. Original receipt fields are not overwritten by retries. Username, Password, request Hash, full raw HTTP bodies and unknown/unvalidated fields are never persisted/echoed. Protect encryption keys/backups; disable sensitive-body proxy/APM logging. Align published privacy/provider disclosures and retention with actual processing before launch. Financial history is not automatically deleted.

## Scheduler and bank recovery

```sh
php artisan ncba:reconcile-receipts
php artisan schedule:list
```

Reconciliation runs every five minutes without overlap, with keyset batches and independent per-receipt failure handling/nonzero failure exit. Ensure Laravel scheduler runs in production. It retries open claims after configuration/verification issues are resolved. It cannot call an undocumented bank lookup API. Agree bank redelivery/statement reconciliation for missed callbacks; a customer receipt is not substitute bank evidence.

## Bank contract / launch validation

The supplied guide's Java sample is implemented as Base64 of the lowercase SHA-256 HEX TEXT for:

```text
secret + TransType + TransID + TransTime + TransAmount
+ BusinessShortCode + BillRefNumber + Mobile + name + "1"
```

It is not Base64 of the binary digest. Preserve original signed strings. Confirm field mapping (BusinessShortCode/TransTime), amount string formatting, casing, receipt IDs, payer phone representation and real sample signatures with NCBA. Narrative and FTRef are NOT in that sample signature; neither is customer ownership proof. This fixed-account flow does not need purchase narration.

UAT genuine callbacks, both arrival orders, verified-payer claims, hashed/third-party manual review, duplicates/conflicts, delays, amount mismatches, cancellations and failed-storage retries. Confirm IPs, timezone, retries and reversal/refund process. Assign staff to monitor unallocated receipts, reconciliation failures, unread alerts and bank-statement discrepancies.

Local tests use disposable SQLite, fake credentials and test-only APP_KEY, not actual NCBA or production funds. SQLite is not proof of MySQL concurrency behavior. Run staging MySQL migration/concurrency checks and genuine bank UAT before production auto-credit. No live database is migrated by local tests.

Local validation: **199 tests / 1,641 assertions passed**, including receipt approval, before/after callback arrival, copied/reused receipts, encrypted evidence, wrong amounts, cancellations, delayed callbacks, admin authorization, failed-storage retries, 105-claim keyset batches and actual isolated Artisan migration install/rollback/reapply. Syntax, style, routes, scheduler registration and Blade compilation passed. A temporary MySQL 8.4 initialization attempt did not complete in this environment and was stopped; MySQL concurrent execution and real NCBA callbacks remain unverified here.

```sh
./vendor/bin/phpunit
./vendor/bin/pint --dirty --test
git diff --check
```
