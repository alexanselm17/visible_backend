# User management

Open **Members → All users** at **/admin/users**.

The Create user button opens the full account form. The All users button resets the view. The list supports role, active/inactive, account verification, phone verification, email verification, county, missing-role and fraud-flag filters. Search matches name, username, user ID, phone, email and referral/image identifiers. Pagination includes an All option; the Deleted tab lists recoverable deletions.

## User controls

Open a profile or choose **Manage account** on a row:

| Control | Behavior |
| --- | --- |
| Create / Edit | Name, username, contacts, occupation, gender, location, referrer, password, role and direct permissions. Personal identifiers stay fixed. |
| Activate / Deactivate | Changes the access flag. Deactivation revokes API tokens, database sessions and remembered logins. |
| Verify account / Remove verification | Changes the account approval flag. A reason is recorded. Account approval, activation and ownership verification are separate states. |
| Send / resend phone OTP | Uses the existing TalkSasa signup flow and cooldown. No OTP is shown in the panel. |
| Verify phone with OTP | Accepts the code supplied by the user. Uses the normal expiry/attempt rules. As in signup, successful OTP verification also marks the account verified. |
| Verify phone after staff review | Admin/developer override after checking phone ownership outside OTP. Requires an evidence/reason note. |
| Remove phone verification | Clears phone verification, expires pending codes and revokes sessions. |
| Verify / remove email verification | Records a staff email ownership check or removes it. This does not send email; the project has no email verification delivery workflow. |
| Reset password | Sets a new password and revokes sessions. Password values and hashes are not included in audit records. |
| Revoke login sessions | Deletes API tokens and database-backed sessions and clears the remember token. File/Redis browser-session records are not listed or directly deleted by this panel. |
| Delete account | Recoverable deletion, deactivation and session/OTP revocation. Posting, token and earnings history remain. |
| Restore account | Restores as inactive. Activation is a separate action. |

Selected users can be activated/deactivated, account-verified/unverified, deleted/restored, or assigned/removed a role in bulk. A failed account in a batch rolls back the batch; staff cannot deactivate/delete themselves.

Changing a phone clears its verification, expires pending OTPs for the old number and revokes sessions. Changing an email clears its verification. A code sent to an old phone cannot verify a new phone, including if another API changed the phone.

## Individual profile

- Profile: identity, location, contacts, referral codes and timestamps.
- Posting & referrals: started/completed tasks, verified views, referrals, qualified referrals and fraud counts.
- Tokens & earnings: available/reserved tokens, ledger balance and paid totals by currency, payout schedule, recent reward periods, links to filtered wallets/purchases/periods/payouts/ledger, and legacy billing records.
- Business & campaigns: business profile and counts/links for owned campaigns and submitted/designed/reviewed adverts.
- Permissions & sessions: role/direct permissions, reported login state, push-delivery readiness, API session count and recent database session device/IP metadata.
- History tabs: screenshots, referred users, notifications, OTP delivery/attempt/expiry history, API sessions with individual revocation, fraud flags and staff change history.

Financial information is visible only to admins/developers. User records never display raw API credentials, browser session identifiers/payloads, passwords, OTP codes/hashes or push tokens.

## Permissions and audit

Admins/developers can manage accounts and verification. Managers require a direct manage_users or users_roles permission and can update non-staff users and activation. They cannot create accounts, change roles/permissions, verify accounts/contacts, delete users or edit staff accounts.

The audit history records the staff member, action, time, reason, verification method and names of changed fields. It does not store password/OTP/token values.

## Apply and validate

Run the following from this project checkout, then refresh /admin/users:

~~~sh
php artisan migrate
php artisan optimize:clear
~~~

The new compatibility migration adds missing account/email verification fields and the account audit table. If account verification is missing, existing verified phones are backfilled as verified accounts, matching the signup flow; existing account flags are preserved. Rollback preserves verification state and audit history.

Tests in FilamentUserManagementTest cover filtering, search, creation controls, activation, separate verification controls, mocked TalkSasa OTP, contact/password changes, recoverable deletion/restoration, manager restrictions, atomic bulk operations, session secrecy, stale-phone OTP rejection and migration compatibility.
