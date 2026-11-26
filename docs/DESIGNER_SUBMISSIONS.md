# Designer submission history

```http
GET /api/v1/campaign-owner/submissions/designer
Authorization: Bearer <designer-access-token>
Accept: application/json
```

Omit the ID to retrieve the authenticated user's history, or provide it explicitly:

```http
GET /api/v1/campaign-owner/submissions/designer/{designerId}
```

Active designers may retrieve only their own history. Active administrators, developers and managers may inspect another designer's ID, including an archived account. Other roles are denied. A missing staff-selected account returns 404. This is read-only and never assigns work or changes a submission.

## Filters and pagination

| Query parameter | Meaning |
| --- | --- |
| `status` | `all` (default), `PENDING_DESIGN`, `DESIGN_DONE`, `PUBLISHED`, `REJECTED` or `PENDING_APPROVAL` |
| `campaign_id` | Optional campaign UUID |
| `submitted_by` | Optional campaign-owner user UUID |
| `from`, `to` | Inclusive submission creation dates, `YYYY-MM-DD`, in the application's East Africa Time zone. Either boundary may be used alone. |
| `per_page` | 1–100, default 20 |
| `page` | Page number, minimum 1 |

Examples:

```http
GET /api/v1/campaign-owner/submissions/designer?status=PENDING_DESIGN
GET /api/v1/campaign-owner/submissions/designer?status=DESIGN_DONE
GET /api/v1/campaign-owner/submissions/designer?status=PUBLISHED
GET /api/v1/campaign-owner/submissions/designer/{designerId}?from=2026-11-01&to=2026-11-26&per_page=20&page=1
```

The response retains the existing pending-list `ok`, `data` and `meta` shape, and adds `designer_id` and `summary`:

- `data`: paginated submissions, newest first. Includes campaign, submitter ID/name, business branding/logos, original media, final media URLs and the designer's ID/name. Owner phone/email, push token, M-Pesa phone and other account fields are not included.
- `meta`: `current_page`, `per_page`, filtered `total` and `last_page`.
- `summary.total`: count across all statuses after applying campaign, submitter and date filters.
- `summary.by_status`: counts for every workflow status, including zeros. The status filter and pagination affect `data`/`meta`, not these counts, so status tabs can display stable totals.

## Assignment matters

Personal history is scoped strictly by `advert_submissions.designed_by`. A `PENDING_DESIGN` submission appears here only if that field already identifies this designer. The current final-upload workflow normally writes the designer ID when the design is finished; existing unassigned pending submissions therefore do not belong to any personal history yet.

The existing `/api/v1/campaign-owner/submissions/pending` endpoint remains the shared pending queue and is unchanged by this addition. `DESIGN_DONE` means ready for rollout, whereas `PUBLISHED` means already rolled out. Both remain in the designer's unfiltered history. No migration or historical attribution backfill is performed.
