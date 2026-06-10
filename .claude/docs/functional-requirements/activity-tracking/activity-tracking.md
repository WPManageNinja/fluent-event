# FR-5: Activity Tracking (Audit Log)

**Summary:** An append-only activity log recording who did what to which object, with structured context — for audit, debugging, and member/admin activity views.

**Depends on FR-1** (identity) and the `wp_fevents_meta` / migration infra (FR-4 / FR-1.9).

## Design principles

- **Typed action registry** — actions are defined constants, never free text → no typos, consistent reporting.
- **Polymorphic typed object** — `object_type` + `object_id` → always know exactly what was acted on.
- **Actor / object split** — record who did it separately from what/whom it affected.
- **Structured context** — `context` JSON holds before/after values, amounts, reasons.
- **Audit fields** — capture ip, user_agent, category for a real audit trail.
- **Append-only** — rows are immutable; never updated after creation.
- **Broad coverage** — every meaningful flow logs, via the registry.

## Data model

**Table: `wp_fevents_activities`** — append-only.

| Column        | Type         | Notes                                                    |
| ------------- | ------------ | -------------------------------------------------------- |
| `id`          | BIGINT PK    | auto-increment                                           |
| `actor_id`    | BIGINT NULL  | who performed it; `NULL` = system/cron                   |
| `actor_type`  | VARCHAR      | `user` \| `system` \| `guest`                            |
| `action`      | VARCHAR      | from the registry (e.g. `event.created`)                 |
| `category`    | VARCHAR      | `auth` \| `event` \| `member` \| `admin` \| `security` … |
| `object_type` | VARCHAR NULL | `event` \| `calendar` \| `member` \| `profile` …         |
| `object_id`   | BIGINT NULL  | the acted-on entity                                      |
| `context`     | JSON NULL    | structured detail — before/after, amounts, reason        |
| `ip`          | VARCHAR NULL | actor IP (audit)                                         |
| `user_agent`  | VARCHAR NULL | actor UA (audit)                                         |
| `created_at`  | TIMESTAMP    | set once — **no `updated_at`**                           |

Indexes: `(actor_id)`, `(object_type, object_id)`, `(action)`, `(category)`, `(created_at)`.

Display text is **rendered at read time** from `action` + `context` (i18n-friendly) — not stored as a frozen string.

## Action registry (typed)

Namespaced `category.action`:

- **auth** — `login`, `logout`, `login_failed`, `signup`, `email_verified`, `password_reset`
- **profile** — `updated`, `email_changed`, `password_changed`, `deactivated`, `deleted`
- **event** — `created`, `updated`, `published`, `cancelled`, `deleted`, `cloned`
- **join** — `requested`, `accepted`, `declined`, `joined`, `left`
- **invite** — `sent`, `accepted`
- **member** — `status_changed`, `role_assigned`, `role_removed`
- **calendar** — `created`, `updated`, `deleted`
- **settings** — `updated`
- **payment** — `succeeded`, `refunded`

(Registry is the single source of truth; code references constants, not raw strings.)

## Functional requirements

### FR-5.1 — Record activity

- A single `Activity::log($action, $object, $context)` entry point writes one immutable row.
- Actor + ip + user_agent captured automatically from the request; `system` for cron/background.
- Only registry actions accepted — unknown action = error, not a silent free-text row.
- Logging failure must not break the underlying action (fail-soft, but logged as an error).

### FR-5.2 — Query & view

- Filter by actor, object, action, category, date range.
- **Member activity view** — a member's own recent activity.
- **Admin audit view** — all activity, searchable, for managers with the audit permission.

### FR-5.3 — Retention

- Configurable retention (e.g. keep N days/months); a scheduled cleanup prunes older rows.
- `security`/`admin` category rows may have a longer (or infinite) retention than `info`.

## Acceptance criteria

- Every registry action, when it occurs, writes exactly one row with correct actor + object + context.
- Rows are never updated after creation.
- An unknown/typo action is rejected at the API, not stored.
- Admin audit view filters correctly by actor / object / action / date.
- Retention cleanup removes only rows past the configured age.

## Open questions

→ Tracked in [clarifying-questions.md](../../clarifying-questions.md) (Q4).
