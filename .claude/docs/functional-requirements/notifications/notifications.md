# FR-9: Notifications

**Summary:** A notification system that tells people about things they care about — join requests, approvals, reminders, event changes. Two audiences, two reach: **logged-in WP users** (hosts / managers) get **in-app** (a feed with unread state) **+ email**; **anonymous event guests** (no account) get **email only**, addressed by `event_guests.email`. WP-user recipients control what they get and where.

**Builds on FR-4** (prefs storage, email channel FR-4.7). Producers: FR-6 (events), FR-8 (calendars). In-app rows exist only for WP users; guest-facing notifications never create in-app rows (guests have no login/feed).

## Design principles

- **Record + delivery split (fcom)** — content stored **once** in `notifications`; a **thin per-recipient row** in `notification_users` carries read-state. Reading the inbox is a join; content isn't duplicated across recipients.
- **Typed action** from a registry (constants), not free text.
- **Polymorphic object** (`object_type` + `object_id`) — know what it's about.
- **Store rendered `title` + `content`** (like fcom, once on the record) — read is a plain select, no object re-resolve, and display survives the source object being deleted. In-app audience is logged-in wp_users, so per-viewer i18n isn't a concern.
- **Prefs on the same `notification_users` table** (`object_type='notification_pref'`) — no separate prefs table.
- **Channels via prefs** — the dispatcher decides in-app + email per the user's settings.
- **User-facing, not a system log.** FR-9 is per-recipient, read/unread, with deep links — for the person, not an immutable admin audit trail.

## Data model — 2 tables (fcom split)

**`wp_fe_notifications`** — the notification **record**, one row per event (render-once).

| Column        | Type          | Notes                                        |
| ------------- | ------------- | -------------------------------------------- |
| `id`          | BIGINT PK     |                                              |
| `user_id`     | BIGINT NULL   | who **triggered** it (the actor); null = system. (Recipients are in `notification_users`, not here.) |
| `action`      | VARCHAR       | typed registry (e.g. `event.join_requested`) |
| `object_type` | VARCHAR NULL  | `event` \| `calendar` …                      |
| `object_id`   | BIGINT NULL   | what it's about                              |
| `title`       | VARCHAR NULL  | rendered title (stored once)                 |
| `content`     | TEXT NULL     | rendered body (stored once)                  |
| `route`       | TEXT NULL     | deep link in the portal                      |
| `created_at`  | TIMESTAMP     |                                              |

Indexes: **`PRIMARY(id)` only.** The inbox join fetches by `n.id` (the PK), retention (FR-9.5)
deletes by `created_at`, and notifications deliberately **outlive** their source object — the
rendered `title`/`content` are stored precisely so a deleted event still renders — so there is
no delete-by-object cleanup query to index for. Add `(object_type, object_id)` only if an
"activity for this object" screen ever ships.

No `recipient_id` — recipients live in the next table. `action` is `VARCHAR(128)` to leave room
for vendor-prefixed add-on actions (widening a VARCHAR costs zero storage; overflow would
silently truncate the routing key).

**`wp_fe_notification_users`** — per-recipient **delivery** AND per-user **prefs** in one table, split by `object_type` (fcom's model):

| Column              | delivery (`object_type='notification'`) | pref (`object_type='notification_pref'`) |
| ------------------- | --------------------------------------- | ---------------------------------------- |
| `notification_type` | channel (`web`)                         | the pref key                             |
| `object_id`         | the `notifications.id`                  | scope (calendar / event id, 0=global)    |
| `user_id`           | recipient (wp_user)                     | pref owner (wp_user)                     |
| `is_read`           | read / unread                           | enabled (0/1) — *repurposed*             |

- **Indexes: `PRIMARY(id)` + the one UNIQUE — nothing else.** `UNIQUE (user_id, object_type,
  object_id, notification_type)` does **four** jobs at once:
  1. **idempotent fan-out** — delivery rows go in with `INSERT IGNORE`, so a worker retry
     can't double-deliver;
  2. **one pref per (user, scope, key)**;
  3. **the inbox seek** — leftmost `(user_id, object_type)` (see the query below);
  4. **covers the `user_id` FK**, so InnoDB creates no duplicate.

  Every column is load-bearing for the uniqueness semantics, so it can't be narrowed.

- **`(user_id, object_type, is_read, object_id)`** — the **unread** path. The badge is
  `COUNT(*) … AND is_read = 0` with **no `LIMIT`**, so without `is_read` in an index it must
  test every one of that user's rows (with a back-to-table fetch each) just to return a number
  — on every page load. Three equality columns then the sort column makes the count
  **index-only** and the unread list a backward walk with no filesort.

  *Why this is needed even though fan-out is small:* fan-out width (~1–10 recipients per
  trigger) is not inbox depth. A busy host accumulates thousands of rows over a year of
  events, so this scales with **time**, not recipient count.

- **Dropped** `(object_id, is_read, object_type, notification_type)` — "who received
  notification X" is an fcom-scale concern (thousands of recipients per post); our fan-out is
  tiny and nothing in FR-9 queries it.

**Canonical inbox query** — no filesort, no join until the content fetch:

```sql
SELECT * FROM wp_fe_notification_users
WHERE user_id = ? AND object_type = 'notification'
ORDER BY object_id DESC LIMIT 20
```

`object_id` **is** the notification id, and ids ascend with time — and it's the third column of
the unique index, right after the two we filter on. So MySQL walks the index backwards and
returns newest-first directly.

> ⚠️ Do **not** write `ORDER BY n.created_at DESC` against the joined `notifications` table.
> Sorting on a joined column forces a materialize + filesort. Order by `nu.object_id DESC` —
> same chronology, fully indexed.

`notification_type` is `VARCHAR(128)` to match `notifications.action` (pref keys mirror action
strings); the unique index key stays ≈788 bytes, well inside InnoDB's 3072-byte limit.
`is_read` defaults to `0` — a delivery row is unread by definition.

Reading the inbox = `notification_users` (the user's `object_type='notification'` rows) **JOIN** `notifications` (the content). Content stored **once** on the record; delivery rows are thin. **Prefs live here too** (`object_type='notification_pref'`) — no separate prefs table, no `wp_fe_meta`. **Email → guests only, no rows in either table** (non-negotiable, no DB trace). In-app = logged-in wp_users only.

## Functional requirements

### FR-9.1 — Dispatch (background batch queue)

Dispatch is **always asynchronous** — the triggering request never resolves recipients or writes notification rows. It only enqueues, so a host action (approve, publish, cancel) returns immediately regardless of audience size.

**1. Enqueue (in-request, O(1)).** On a trigger the producer builds a *descriptor* — `action`, `user_id` (the actor), `object_type`, `object_id`, rendered `title` + `content`, `route`, and an **audience spec** (not a resolved list — e.g. `{ "type": "event_guests", "event_id": 9 }`). It enqueues one async job and returns. Fail-soft: the enqueue is wrapped so a queue failure never breaks the triggering action.

- Tech: Action Scheduler (`as_enqueue_async_action`), group `fluent-events`. An **async** action runs on the next loopback ASAP (seconds), not on the cron interval — so even a single-recipient notification appears near-instantly.

**2. Fan-out worker (background, batched, cursor-paginated).** The job `fluent_events/notify_fanout` processes the audience in pages:

- **Two audience kinds.** A **WP-user audience** (hosts/managers) is keyed by `user_id` → in-app rows **+** email. A **guest audience** (`event_guests`) is keyed by `email` only (anonymous, no account) → **email channel only, no in-app rows**.
- Resolve the next page from the audience spec — cursor ascending (`user_id` for WP-user audiences, guest row `id` for guest audiences), page ~1000. Re-derive from the spec each chunk; never carry a 100k-id list in the payload.
- **WP-user audiences:** on the first chunk, insert the **one** `wp_fe_notifications` record (rendered `title`/`content`), keep its `id`. Drop recipients whose `in_app` pref for this `action` is off (read from their `notification_users` `object_type='notification_pref'` rows); **bulk-insert thin delivery rows** (~500–1000 per insert) into `wp_fe_notification_users` (`object_type='notification'`, `notification_type='web'`, `object_id`=the record id, `is_read=0`) with `INSERT IGNORE` against the unique `(user_id, object_type, object_id, notification_type)` key.
- Hand the `email`-eligible slice to the email channel (FR-4.7) — its own chunked queue. Guest audiences go straight here (email is their only channel).
- Advance the cursor. If runtime exceeds a budget (~25s) reschedule self with the cursor; else continue until the audience is exhausted.

**3. Render once, fan out thin.** The producer renders `title` + `content` once (at enqueue) → **one** `notifications` record. The fan-out writes **thin** `notification_users` delivery rows pointing at it (no content copied per recipient). Read is a join, but content lives in a single row — so wide fan-out stays cheap and a later-deleted source object still renders from the stored record.

**4. Idempotency.** The cursor lives in the action args and the unique index makes re-runs safe: a worker crash mid-fan-out re-runs from the persisted cursor, and duplicate inserts bounce off the key. At-least-once delivery, exactly-once rows.

**5. Inline fast-path (optional).** Default is always-queue. A producer that targets exactly one recipient (e.g. a direct approval) and wants instant feedback may write the record + its one delivery row inline instead of enqueuing — same shape, same unique key. Not required.

Producers fire `do_action('fluent_events/notify', $descriptor)` and never insert notification rows directly; the notification module owns the single subscriber that enqueues the fan-out.

### FR-9.2 — In-app feed

- A notification list (bell) with an **unread count**; mark one or **all** read (`is_read` on the user's `notification_users` rows).
- Clicking a notification follows its `route` to the relevant page.

### FR-9.3 — Preferences

- Per-user, per-action, per-channel toggles (in-app / email). Sensible defaults; required/system ones may be locked.

### FR-9.4 — Trigger registry

Typed, namespaced action registry. At least:

- **event** — `join_requested`, `join_accepted`, `join_declined`, `updated`, `cancelled`, `reminder`, `payment_received`

### FR-9.5 — Retention

- Scheduled cleanup of old read notifications (configurable window) so the table stays lean.

## Acceptance criteria

- A trigger creates an in-app row only for recipients whose prefs allow it; email goes only where the email channel is on.
- Triggering a notification does not block on fan-out — the request returns immediately and rows are written by the background batch worker (near-instant for small audiences, chunked for large); a worker retry never creates duplicate rows.
- Unread count reflects `notification_users` rows with `is_read = 0` (`object_type='notification'`); mark-all-read sets `is_read = 1`.
- Clicking a notification lands on the right page via `route`.
- Turning off a notification type stops both its in-app and email delivery (per channel toggle).
- Old read notifications are pruned on schedule.

## Open questions

→ Tracked in [clarifying-questions.md](../../clarifying-questions.md).
