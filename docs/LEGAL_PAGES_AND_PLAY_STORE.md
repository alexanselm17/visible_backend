# Public policies, branding and Play Store readiness

## What was reviewed

The supplied terms/privacy text was a useful starting point, **not ready to publish unchanged**. It contained company/date/contact placeholders; an unverified ODPC registration statement; an unconfirmed Android Accessibility Services description; references to Africa's Talking/Twilio rather than TalkSasa; and promises about providers, transfers and deletion that cannot be established from this backend.

The replacement drafts describe campaign tokens separately from member earnings; submission-plus-reach charging, reservations and settlement; performance-period multiplication, caps, weekly/monthly payout and retained history; ownership references/watermarks; TalkSasa SMS, OpenAI screenshot analysis and Firebase notifications; human review; and privacy/deletion requests. They remove guaranteed-income implications, automatic forfeiture of all balances, supposed automatic in-app deletion, and unverified registration/certification claims. They preserve mandatory consumer/privacy rights rather than imposing unreviewed compulsory arbitration or blanket exclusions.

These are editable starting points, not legal advice or a Google Play approval certificate. Have the operator and an appropriate Kenyan legal/privacy adviser review them. The Android/Flutter source is not in this checkout, so its SDKs, permissions, consent screens, payment interface and store declarations have **not** been certified.

## Deploy and configure

1. Deploy the code to the actual backend host and run `php artisan migrate`, then `php artisan optimize:clear`. The migration adds four tables, seeds two **draft** documents, preserves existing records on rollback and is safe to rerun. It does not publish either policy or alter existing user/campaign/financial records.
2. Use HTTPS. Set `APP_URL` to this public backend host, with a correctly configured web root (`public`) and usable storage URLs. Neither `www.visibledm.com` nor `app.visibledm.com` is assumed to host this deployment.
3. In **Admin → Settings → Branding & legal settings**, enter the real app/store-listing name, legal operator, monitored support/privacy mailboxes and optional phone/address. Upload a PNG/JPEG/WebP logo (2 MB, 4096 px maximum). No SVG or arbitrary path is accepted. Branding is shared with public pages, the workspace and newly encoded images. Old generated images are not rewritten; with no custom logo the image encoder retains its existing fallback.
4. Confirm actual Android Accessibility Services behavior. This setting is a **disclosure, not a switch that changes the app**. If used, describe the actual screens/data, actions, transmission recipients, purpose, consent, disable path and background behavior. Do not select “No” merely to bypass review.
5. Enter the **real** retention/deletion schedule: durations or triggers for account records, screenshots/media, transaction/earnings evidence, logs, privacy requests and backups; deletion turnaround; retained categories, lawful reasons and expiry. Verify providers' copy/deletion behavior and international transfer safeguards. A text setting does not implement purging or provider contracts.
6. Open **Settings → Legal policies**. Edit each draft, choose its effective date, save and **Preview draft**. Confirm the complete text and identity/privacy facts. Publish each document after reviewing the checklist. Publication blocks missing operator/contact details, unconfirmed Accessibility use, absent retention text, future effective dates and obvious unfilled placeholders.
7. Open both public URLs **signed out**, without geo/IP restrictions or a login wall, on mobile and desktop. Only after publication should you submit those URLs to Play Console. An unpublished document intentionally returns HTTP 503 with no draft content; it is not a valid submission-ready privacy URL.

Admin access is restricted to active admins/developers, not managers or members. Saving a policy draft does not change the live policy. Publishing creates a numbered, dated snapshot with publisher ID and the identity facts used. **Settings → Policy publication history** is read-only. Changing identity/privacy facts in settings does not rewrite published document bodies: review and republish both policies. Public users cannot edit policies. Rich text is sanitized on draft save, publication and display; scripts, embeds, tracking images, forms and unsafe link schemes are not allowed.

## URLs to use

Replace `https://YOUR-BACKEND-HOST` with the host actually serving this Laravel app:

| Purpose | Public path |
| --- | --- |
| Google Play privacy policy field / in-app Privacy link | `https://YOUR-BACKEND-HOST/privacy-policy` |
| In-app Terms link | `https://YOUR-BACKEND-HOST/terms-and-conditions` |
| Play Console account-deletion URL / in-app Delete account link | `https://YOUR-BACKEND-HOST/delete-account` |
| Support, access, correction or human-review requests | `https://YOUR-BACKEND-HOST/privacy-request` |

`/privacy` and `/terms` redirect permanently to the canonical pages. Public pages are server-rendered HTML (not a PDF), responsive, printable, and usable without JavaScript. No purchasing button, payment instructions or alternate-checkout link was added to the legal pages. This is not a way to hide actual purchases from reviewers.

Add Privacy, Terms and an easily discoverable Delete account link in Flutter settings/onboarding as appropriate. A backend page alone does not add those links or consent screens to Android. If unexpected sensitive-data processing requires prominent disclosure/consent, implement it **before** collection, not just inside this policy. Publishing a changed policy also does not send an email or push notice; arrange required change notices separately.

## Handling deletion and other requests — manual fulfillment required

The external form works without account login or reinstalling the app. It records only request type, supplied contact/message, a random reference and handling metadata. CSRF validation, a honeypot and a five-requests-per-minute IP rate limit protect submission. Responses do not reveal whether the supplied contact has an account and **never automatically disable/delete one**.

Requests appear in **Settings → Privacy requests**. Monitor this inbox and the configured mailbox; this change does not send staff email notifications. Filter by status/type, review the details and record actions. The form acknowledgement is not an email delivery confirmation or a promise that deletion has already happened.

For a verified deletion request, authorized staff must actually:

- Verify ownership proportionately via the registered contact; never ask for a password, PIN or a live OTP in free text.
- Revoke access and remove/anonymize unnecessary personal information, media, screenshots, sessions/tokens and provider copies according to the published schedule. Coordinate copies/backups as well as primary records.
- Isolate any genuinely necessary tax, payment, fraud, security or dispute records; document the category, lawful reason and retention expiry. Do not retain unrelated information just because one transaction must be retained.
- Inform the user of completion, retained exceptions and timing. Record the actual work, then mark the request complete and acknowledge fulfillment in the admin form. The checkbox is a staff attestation, **not an automated erasure routine**.

The existing Members → Delete action is a recoverable soft delete and preserves financial history. It is **not** by itself a compliant personal-data erasure process. This change adds a request/handling pathway, not a blanket hard-delete job that would destroy ledgers or unpaid earnings. Do not publish claims of automatic erasure, fixed backup deletion, or completed fulfillment until those operations actually exist and match the policy. Staff must also handle access/correction/review requests and explain justified refusals.

## Payments: do not misdeclare the app

The backend supports purchases of advertising tokens and separate performance payouts. Purchase confirmation records an already received payment reference and credits tokens; payout confirmation records money already sent. These methods are not a Google Play Billing integration and do not themselves transfer money.

Whether Play Billing is required depends on the **actual Android purchase and consumption flow**, not the label “advertising”, “wallet”, “tokens” or “M-Pesa”, and not a sentence in the terms. Paid digital content/functionality/services accessed in a Play-distributed app generally fall under Play Billing unless a specific exception or eligible program applies. Google's FAQ distinguishes digital services usable **only outside** a Play-distributed app and inaccessible in it; any reliance on that distinction must be supported by the real product flow, not assumed from WhatsApp distribution alone.

Member earnings paid out to users are not the same as users purchasing digital credits. A genuinely consumption-only/member-only Android release may have a different analysis, but only if it really does not enable purchases or lead users into prohibited alternate payment routes. Do not answer “no purchases” if campaign owners can buy credits from the Android app. Obtain a product-specific ruling/advice where needed; complete Play Console questions truthfully. No wording can guarantee Google will not ask payment questions or approve the app.

## Official references checked 4 November 2026

- [Google Play User Data — privacy policy and account deletion](https://support.google.com/googleplay/android-developer/answer/10144311): public, active, non-geofenced HTML privacy URL; accurate identity, data handling, recipients, contact, retention/deletion; in-app access too.
- [Account deletion requirements](https://support.google.com/googleplay/android-developer/answer/13327111): in-app and external pathways; a form or customer-service contact can initiate deletion; disabling/freezing is not deletion; justified retained data must be disclosed.
- [Payments policy](https://support.google.com/googleplay/android-developer/answer/9858738) and [Payments FAQ](https://support.google.com/googleplay/android-developer/answer/10281818): billing depends on the purchase/use case; exceptions/alternative-billing programs have conditions; legal/support pages must not become prohibited alternate checkout funnels.
- [AccessibilityService API](https://support.google.com/googleplay/android-developer/answer/10964491): correct declaration/use case and, where required, separate prominent in-app disclosure and affirmative consent; policy text alone is insufficient.
- [Kenya ODPC regulatory framework](https://www.odpc.go.ke/data-protection-laws-kenya/) and [data subject rights](https://www.odpc.go.ke/rights-of-a-data-subject/): confirm the operator's registration obligations, lawful bases, safeguards and rights process. Do not invent a registration number or describe a business as registered without evidence.

Also reconcile the Play Console Data safety form with the **whole Android app and its SDKs**, not only this server: account/profile data, selected location versus GPS, uploaded images/screenshots, OpenAI processing, device identifiers/push tokens, financial records and any Accessibility data. Screenshots can expose other people's information; minimize unrelated content and disclose unexpected sharing before upload when required.
