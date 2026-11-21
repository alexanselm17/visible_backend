# Visible admin workspace

Open **/admin** in the application served from this checkout. Sign in with an active admin, developer or manager account using its username and password.

For the full user workspace, filters, account/phone/email verification and audit controls, see [User management](USER_MANAGEMENT.md).

## Feature map

| Workspace | Screens and actions |
| --- | --- |
| Overview | Live member, advert, completed-task and queue totals; screenshot trends; completed-task views; token sales; performance earnings; recent posting evidence. No sample chart data. |
| Income | Finance-only dashboard directly below Overview at `/admin/income`: approved token-sale income, bank-confirmed money awaiting allocation, unpaid orders, receipt claims, paying owners and average purchase. Date/date-range presets, currency and token filters, income trend, monthly/yearly comparisons, token breakdown, order/bank status graphs, searchable histories and filtered approved-income CSV export. |
| Campaigns | Create and edit campaigns; assign an owner; campaign statistics and related adverts; campaign reports. |
| Submission workflow | Read the brief, audience, original images/videos and final files; approve; upload final image or video, thumbnail and duration; assign a designer; publish; reject with a reason and release reserved tokens. Status transitions use the existing API workflow. |
| Published adverts | Create direct staff adverts; edit creative, media type, audience, badges, capacity and expiry; preview files; repost; open posting evidence. Owner-funded submissions are published from the submission workflow so tokens are settled. |
| Posting screenshots | Filter by member, advert, stage and staff-entry source; inspect original evidence; record already verified WhatsApp values through the existing manual submission workflow. The 18-hour interval, capacity, increasing views, second-screenshot minimum and performance-completion rules still apply. |
| Image layout studio | Choose a member, attached-image advert, personal image and one of the five existing layouts; generate and preview a personalized image with the member/advert ownership token. Standalone full-image adverts use their personalized download instead. |
| Members & staff | Create and update accounts using the current schema; role and direct permission assignment for administrators; activate/deactivate in bulk; archive/restore accounts; revoke API sessions on deactivation/archive; inspect phone verification; resend signup OTP with normal limits; member reports; referral, notification and screenshot history. Personal identifiers are generated as unique ten-digit numbers. |
| Campaign owners | Create/edit business profiles, account status and contact details; upload business logos, make one primary or remove a logo; open the owner's token wallets. In Members → Business logos, click a thumbnail or Preview to see the whole logo on a transparency checkerboard, or open the original image. |
| Notifications | Create in-app messages for individual members; inspect message/read history. This screen does not automatically send push messages. |
| Tokens | Estimate submission-plus-reach usage and purchase prices; configure prices, reach coverage and video rules; record purchases, confirm received payment or cancel pending purchases; see available/reserved balances; adjust available tokens with a reason; inspect transaction and reservation history. Full images use PLATINUM; attached-image ads use EMERALD, with separate prices and wallets. Gold stays above both image prices, Silver below both, and the two image prices must differ. Configure and enable the new token before use; see `IMAGE_TOKEN_FORMATS.md`. |
| NCBA payments | Fixed-account 906854 receipt-claim flow; verify option-3 callbacks, inspect encrypted bank details, reconcile exact-price purchases, investigate conflicts or record externally completed refunds. Finance access only. Ingestion/automatic approval require secure setup and bank UAT; see `NCBA_PAYMENTS.md`. |
| Payment receipt claims | All numbered customer receipt attempts, verified-phone/purchase snapshots, waiting/review/approved/rejected/replaced statuses and ordered append-only audit history. Reject with notes or open bank inbox to approve after ownership review. Active admins/developers get unread payment alerts on Overview. |
| Reward plans | Create immutable plan versions, copy a version, configure metric ranges, weights and multiplier bounds, view rules and activate a version. Multiplication remains the default; weighted average remains available. Changing an existing metric's evaluator requires a new metric code. |
| Earning periods | Inspect per-member weekly/monthly periods, measured metrics, evidence and saved calculations; refresh open estimates; close eligible ended periods after the configured grace period. |
| Member payouts | Inspect payable amount, phone, period, provider, payment reference and failure reason; mark failed or confirm a payment already sent; retry failed payouts. Existing duplicate-reference, monthly-cap and self-payment checks remain in the payout service. Confirmation records payment; it does not initiate a provider transfer. |
| Payout schedules | Set weekly/monthly preferences through the existing period service; inspect effective dates without rewriting current periods. |
| Earnings audit | Read earnings/payment ledger entries, metric registry and qualified-referral evidence. Financial history cannot be directly edited or deleted. |
| Trust & verification | Scan campaigns for matching screenshot patterns; compare evidence; record a review; warn or deactivate involved non-staff accounts; inspect review history, member fraud flags, OTP delivery/attempt/expiry state and image ownership verification dates. OTP hashes and image token secrets are not displayed or exported. |
| Settings | Manage roles and role permissions, direct permissions, counties, subcounties, app versions and reference data. Existing role/permission keys are fixed to avoid breaking authorization. |
| Public policies & branding | Settings → Branding & legal settings controls the app/operator name, public contacts, shared logo, Android Accessibility disclosure and actual retention schedule. Legal policies provides draft editing, private previews and deliberate publication; Policy publication history keeps read-only snapshots. Privacy requests holds external deletion/access/correction/review requests for verified manual fulfillment. See `LEGAL_PAGES_AND_PLAY_STORE.md` before publishing. |
| Legacy records | Read historical credit wallets, credit movements/reservations/packages, subscriptions/plans, banking history and old advert payments. These screens do not reactivate the retired credit or per-advert reward workflows. |
| Reports & exports | Campaign overview, dated campaign report and dated member report. Filtered CSV export is available on record and audit tables; exports escape spreadsheet formula prefixes. |

## Access

- Admins and developers: all workspaces.
- Managers: campaign operations, reports, owner branding and fraud review.
- Managers need a direct manage_users or users_roles permission for member/OTP/notification screens. They cannot change staff roles/permissions or edit staff accounts.
- Financial operations and system settings remain restricted to admins/developers.
- Inactive accounts cannot sign in to or access the panel.
- Financial mutations run through the existing token/reward services. Screenshot evidence and financial history are not freely editable.
- Account archival uses soft deletion; permanent deletion is not exposed.

## Applying the update

Run from the project directory:

~~~sh
php artisan migrate
php artisan optimize:clear
~~~

The new compatibility migration adds advert_images.type only when absent and infers video/image values for those legacy rows. An already existing type column and its values are preserved. Its rollback deliberately preserves classification data.

No dependency upgrade or frontend build is needed. New navigation appears when the application runs this checkout. If component discovery has been cached for deployment, rebuild that cache after clearing it.

## Income reporting

The Income dashboard adds no migrations and does not change balances, payment approval or token crediting. It reads existing financial records; approval links open the existing payment workspaces. Active administrators and developers only can access the page, its widgets and exports.

- Click **Apply filters** after choosing a single day, custom date range or quick period. All period-based totals, graphs and tables use the applied dates, currency and token type. Draft edits do not silently change a displayed report. **Reset** restores this month through today, KES and all tokens. Applied filters are remembered per staff session.
- Dates are inclusive in East Africa Time. **Approved income** includes only `PAID` token purchases, using their saved price and `paid_at` date, not their creation date. Refunded/failed/cancelled purchases and paid purchases without approval dates are excluded; missing approval dates in orders created within the range are flagged. Historical inactive token types and archived owners do not remove revenue.
- **Pending approval** is actual `REVIEW` bank receipts dated by `transacted_at`. **Unpaid orders** are pending purchases created in the range; receipt claims without bank confirmation are not income. Bank receipt totals overlap approved income and must never be added to it. Unallocated bank receipts appear only under All tokens because they have no token type yet.
- Currencies are reported separately. The approved income card compares against the immediately preceding equal-length date range; growth is unavailable rather than misleading when prior income is zero. The trend automatically groups by day, month or year for readability.
- Monthly comparisons use the two selected years; the five-year graph ends in the main year. These two charts use their own calendar-year or equal elapsed month/day comparison window, not the main From/Through dates. For elapsed comparisons the Through date supplies the cutoff month/day, clamped for leap years. If either selected year is the current year, both elapsed windows stop no later than today's month/day. In full-calendar mode, historical years can be longer than a partial current year. Months outside the selected comparison window are not drawn as zero-income months.
- Purchase status graphs count orders **created** in the range; bank status graphs use bank transaction dates. Both show each record's **current** status, not a point-in-time reconstruction. Default history tables show approved purchases and pending bank approvals; their own status filters expose the other statuses. Applying report filters resets table pagination, searches and status filters to those defaults. The two tables paginate independently.
- **Export approved income** exports only filtered approved purchases (with purchase/owner IDs, token quantity, saved price, receipt and EAT timestamps), escaping spreadsheet formula prefixes. It does not export raw bank payloads, payer phone/name, credentials or bank hashes.

This is operational income reporting, not net profit, cash-basis reconciliation, a refund ledger or a tax statement. No artificial income, forecast or sample graph data is included.

Media uploaded in Filament uses the same public storage location as API-generated files, including installations where public/storage is a directory rather than a symlink. Temporary uploads allow 50 MB videos; the host's PHP upload_max_filesize, post_max_size and web-server request limit must also permit that size.

## Validation

tests/Feature/FilamentOperationsTest.php covers screen rendering, authorization, token purchase credits, submission approval/refund, plan creation and ranges, payout failure/retry, member creation/password hashing, populated detail forms, fraud review, dashboard component registration and the media-type compatibility migration. Existing tests cover pricing, screenshot verification, layouts, OTP and reward lifecycle behavior.

Panel tests use a disposable SQLite database built from project migrations. Three historical MySQL/session conversion migrations are excluded from that fixture and submission status is normalized for SQLite. This is panel integration coverage, not a MySQL upgrade certification. No live account, purchase, SMS or payout is needed for the tests.

`tests/Feature/IncomeDashboardTest.php` covers finance authorization, rendering, graph datasets, exact minor-unit totals, no double counting, approval-date boundaries, missing timestamps, inactive/archived records, currency/token isolation, date presets/validation, leap-year and monthly/yearly comparisons, explicit filter application, history status/search filters and safe read-only CSV export.

~~~sh
./vendor/bin/phpunit
./vendor/bin/pint --dirty --test
git diff --check
~~~
