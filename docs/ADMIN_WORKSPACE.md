# Visible admin workspace

Open **/admin** in the application served from this checkout. Sign in with an active admin, developer or manager account using its username and password.

## Feature map

| Workspace | Screens and actions |
| --- | --- |
| Overview | Live member, advert, completed-task and queue totals; screenshot trends; completed-task views; token sales; performance earnings; recent posting evidence. No sample chart data. |
| Campaigns | Create and edit campaigns; assign an owner; campaign statistics and related adverts; campaign reports. |
| Submission workflow | Read the brief, audience, original images/videos and final files; approve; upload final image or video, thumbnail and duration; assign a designer; publish; reject with a reason and release reserved tokens. Status transitions use the existing API workflow. |
| Published adverts | Create direct staff adverts; edit creative, media type, audience, badges, capacity and expiry; preview files; repost; open posting evidence. Owner-funded submissions are published from the submission workflow so tokens are settled. |
| Posting screenshots | Filter by member, advert, stage and staff-entry source; inspect original evidence; record already verified WhatsApp values through the existing manual submission workflow. The 18-hour interval, capacity, increasing views, second-screenshot minimum and performance-completion rules still apply. |
| Image layout studio | Choose a member, advert, personal image and one of the five existing layouts; generate and preview a personalized image with the member/advert ownership token. |
| Members & staff | Create and update accounts using the current schema; role and direct permission assignment for administrators; activate/deactivate in bulk; archive/restore accounts; revoke API sessions on deactivation/archive; inspect phone verification; resend signup OTP with normal limits; member reports; referral, notification and screenshot history. Personal identifiers are generated as unique ten-digit numbers. |
| Campaign owners | Create/edit business profiles, account status and contact details; upload business logos, make one primary or remove a logo; open the owner's token wallets. |
| Notifications | Create in-app messages for individual members; inspect message/read history. This screen does not automatically send push messages. |
| Tokens | Estimate submission-plus-reach usage and purchase prices; configure prices, reach coverage and video rules; record purchases, confirm received payment or cancel pending purchases; see available/reserved balances; adjust available tokens with a reason; inspect transaction and reservation history. Token pricing keeps Gold > Platinum > Silver. |
| Reward plans | Create immutable plan versions, copy a version, configure metric ranges, weights and multiplier bounds, view rules and activate a version. Multiplication remains the default; weighted average remains available. Changing an existing metric's evaluator requires a new metric code. |
| Earning periods | Inspect per-member weekly/monthly periods, measured metrics, evidence and saved calculations; refresh open estimates; close eligible ended periods after the configured grace period. |
| Member payouts | Inspect payable amount, phone, period, provider, payment reference and failure reason; mark failed or confirm a payment already sent; retry failed payouts. Existing duplicate-reference, monthly-cap and self-payment checks remain in the payout service. Confirmation records payment; it does not initiate a provider transfer. |
| Payout schedules | Set weekly/monthly preferences through the existing period service; inspect effective dates without rewriting current periods. |
| Earnings audit | Read earnings/payment ledger entries, metric registry and qualified-referral evidence. Financial history cannot be directly edited or deleted. |
| Trust & verification | Scan campaigns for matching screenshot patterns; compare evidence; record a review; warn or deactivate involved non-staff accounts; inspect review history, member fraud flags, OTP delivery/attempt/expiry state and image ownership verification dates. OTP hashes and image token secrets are not displayed or exported. |
| Settings | Manage roles and role permissions, direct permissions, counties, subcounties, app versions and reference data. Existing role/permission keys are fixed to avoid breaking authorization. |
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

Media uploaded in Filament uses the same public storage location as API-generated files, including installations where public/storage is a directory rather than a symlink. Temporary uploads allow 50 MB videos; the host's PHP upload_max_filesize, post_max_size and web-server request limit must also permit that size.

## Validation

tests/Feature/FilamentOperationsTest.php covers screen rendering, authorization, token purchase credits, submission approval/refund, plan creation and ranges, payout failure/retry, member creation/password hashing, populated detail forms, fraud review, dashboard component registration and the media-type compatibility migration. Existing tests cover pricing, screenshot verification, layouts, OTP and reward lifecycle behavior.

Panel tests use a disposable SQLite database built from project migrations. Three historical MySQL/session conversion migrations are excluded from that fixture and submission status is normalized for SQLite. This is panel integration coverage, not a MySQL upgrade certification. No live account, purchase, SMS or payout is needed for the tests.

~~~sh
./vendor/bin/phpunit
./vendor/bin/pint --dirty --test
git diff --check
~~~
