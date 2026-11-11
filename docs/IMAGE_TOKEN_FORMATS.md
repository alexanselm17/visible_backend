# Full-image and attached-image tokens

## Setup

Run `php artisan migrate` and `php artisan optimize:clear` after deploying this change.

In **Admin → Tokens → Pricing & reach rules**, configure **Emerald · Attached image** and enable **Available for purchase & new submissions**. Its price is deliberately unset and the type starts disabled: no selling price has been invented. The existing Platinum ID, unit price, wallets, reserved balances, purchases and previous submissions are preserved. The migration only gives the default “Platinum” name a clearer label and adds the new type; rerunning it does not reset configured prices or balances. Rollback preserves financial records.

If the attached-image token was already installed as `PLATINUM_ADS`, the follow-up migration renames that same row to `EMERALD`. Its ID, configured price, activation, reach rules, balances, reservations and purchase/submission history stay unchanged. Default display names become **Emerald · Attached image**; custom names are preserved. Clients must now send `EMERALD` for token quotes/purchases/adjustments; the advert format remains `ADS_IMAGE`. Historical transaction descriptions are not rewritten.

On older MySQL installations with an `ENUM`/`SET` format column, the migration widens `advert_images.type` / `advert_submissions.type` to a 20-character string while retaining values, nullability and the default. Existing string columns are left alone. Back up production and test the migration on a staging copy first.

| Advert format | API submission/usage `type` | Purchase/wallet token code | Delivery |
| --- | --- | --- | --- |
| Full image / standalone poster | `IMAGE` | `PLATINUM` | Personalized full-image download |
| Image advert attached to a member's own image | `ADS_IMAGE` | `EMERALD` | Member image + advert through `/api/v1/image/stamp` |
| Video | `VIDEO` | `GOLD` | Existing video flow |
| Text | `TEXT` | `SILVER` | Existing text flow |

Legacy `image` and the alias `FULL_IMAGE` normalize to `IMAGE` on quote/submission input; `ads` normalizes to `ADS_IMAGE`. Existing published `ads` records are recognized as attached-image adverts without rewriting their history. Existing image submissions remain assigned to their existing Platinum token; no balances are converted or duplicated into the new wallet.

Both image formats currently require **3 submission tokens + ceil(target_reach / people_per_token)**. Each has its own price per token and reach coverage. Video/text submission charges remain 5/1. This change separates service-credit types; it does not switch to a per-advert member reward model. Member earnings remain performance based.

Prices for the two image tokens must differ. Gold must remain above each image price and Silver below each. There is no forced ordering between the two image prices. Those existing price-tier safeguards are validated through the API and Filament, including updates to Gold/Silver or the original Platinum. Pending purchases retain their recorded purchase price. Already submitted adverts retain their token type and captured reach pricing, even if current prices/reach coverage change.

## API / Flutter integration

1. Read `GET /api/v1/tokens/types` and show both image choices by their names/codes. `purchasable` is false until an admin supplies a price and enables the type. Do not hardcode three token types or combine the two image balances.
2. For purchase quotes, purchases or staff wallet adjustments, send `token_type: "PLATINUM"` or `token_type: "EMERALD"` to the existing token endpoints. Wallet responses, transaction history and purchase confirmations remain separated by token type.
3. For `POST /api/v1/tokens/usage-quote` and `POST /api/v1/campaign-owner/campaign/submission`, send `type: "IMAGE"` or `type: "ADS_IMAGE"`. Both image submissions require `images[]`, a target reach and the existing brief fields. The backend selects the wallet from the format; a client cannot override it by sending another token code. Available full-image tokens cannot pay for an attached-image submission, or vice versa.
4. Advert-list responses now include `media_type`, `posting_format`, `token_type`, `requires_personal_image`, `download_url` and `stamp_url`. Use these flags rather than assuming every advert has a standalone download. For attached-image ads, `download_url` is null and `stamp_url` points to `/api/v1/image/stamp`.
5. Full images use `GET /api/v1/image/advert/{advertId}/download` as before. Attached-image ads use authenticated multipart `POST /api/v1/image/stamp` with `advert_id`, the member's `image` and optional `layout`. **Send the bearer token to `/stamp` too.** `identifier` is now optional; it is derived from the signed-in member, and a supplied different identifier is rejected. The generated file contains the same verifiable user/advert-bound ownership proof used by screenshot verification. No visible QR/disclaimer was added.

Example usage quote:

```json
{
  "type": "ADS_IMAGE",
  "target_reach": 100
}
```

Example token purchase:

```json
{
  "token_type": "EMERALD",
  "quantity": 20
}
```

The standalone download rejects attached-image ads. The stamp endpoint and admin layout studio reject full-image adverts. These checks enforce the supported generation workflow; images distributed to users cannot be made impossible to copy or edit. This is not a claim that a public media URL or screenshot can prove every aspect of the user's final post layout.

## Workflow and admin coverage

- Submission approval and image design support both formats; both require a final image. Image-token submissions cannot publish a video as their final media.
- Reservations, settlement, extra-token checks and rejection refunds use the captured token wallet, never a combined Platinum balance. A mismatch between a submission and its token type is rejected.
- Paid published adverts keep their funded format. To change formats, submit and fund a new advert. Direct staff adverts can select either format without pretending to be an owner-funded submission.
- Reposts retain the original format and submission association. The existing repost billing behavior is unchanged; no extra image-token charge or automatic conversion was introduced.
- Pricing/reach rules, estimator, purchases, owner wallets, transactions/reservations, submission details/filters, published-advert filters and the layout studio all distinguish the formats. Legacy `ads` records are included in the attached-image filter.
- Original creative/source files are still available in the authorized design/admin workflow. Display/download routing controls are not a DRM or screenshot-layout classifier.

There is no Flutter project in this repository. Update its token picker and format handling to consume the above contract, and ensure the stamp request sends authentication. No Google Play billing declaration or payment-provider integration is changed by this backend token split.

## Validation for this change

- Full automated suite: 108 tests, 1,049 assertions passed, including the existing layout pixel/visibility tests, new image-format workflows and Emerald rename preservation/conflict checks.
- Isolated SQLite databases passed console migration install, rollback and reapply checks for both the image-token split and Emerald rename, preserving wallet balances, token IDs, configured prices and purchase records. A repeated migration run also correctly reported nothing to migrate.
- Formatting, PHP syntax checks, compiled views and route registration passed.
- A separate temporary MySQL instance could not complete startup in this environment, so MySQL execution (including the legacy-enum conversion) was not verified here. No live application database was migrated or connected to for these checks.
