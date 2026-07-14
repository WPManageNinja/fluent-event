# FR-9: Notifications

**Summary:** A notification system that tells people about things they care about — join requests, approvals, reminders, event changes. Two audiences, two reach: **logged-in WP users** (hosts / managers) get **in-app** (a feed with unread state) **+ email**; **anonymous event guests** (no account) get **email only**, addressed by `event_guests.email`. WP-user recipients control what they get and where.

**Builds on FR-4** (prefs storage, email channel FR-4.7). Producers: FR-6 (events), FR-8 (calendars). In-app rows exist only for WP users; guest-facing notifications never create in-app rows (guests have no login/feed).

## Design principles

- **One row per recipient** (not one shared row) — clean per-user read state.
- **Typed action** from a registry (constants), not free text.
- **Polymorphic object** (`object_type` + `object_id`) — know what it's about.
- **Render at read time** from `action` + `data` (i18n-friendly) — don't freeze title/content.
- **Channels via prefs** — the dispatcher decides in-app + email per the user's settings.
- **User-facing, not a system log.** FR-9 is per-recipient, read/unread, with deep links — for the person, not an immutable admin audit trail.

## Data model

**Table: `wp_fevents_notifications`** — one row per recipient per notification.

| Column         | Type          | Notes                                        |
| -------------- | ------------- | -------------------------------------------- |
| `id`           | BIGINT PK     |                                              |
| `recipient_id` | BIGINT        | who sees it                                  |
| `actor_id`     | BIGINT NULL   | who triggered it; null = system              |
| `action`       | VARCHAR       | typed registry (e.g. `event.join_requested`) |
| `object_type`  | VARCHAR NULL  | `event` \| `calendar` …                      |
| `object_id`    | BIGINT NULL   | what it's about                              |
| `data`         | JSON NULL     | render context — names, event title, etc.    |
| `route`        | TEXT NULL     | deep link in the portal                      |
| `read_at`      | DATETIME NULL | null = unread                                |
| `created_at`   | TIMESTAMP     |                                              |

Indexes: `(recipient_id, read_at)`, `(object_type, object_id)`, `(action)`. **Unique `(recipient_id, action, object_id)`** — makes the background fan-out idempotent: a re-run `INSERT IGNORE`s, so a retried worker never double-creates rows.

**Preferences** — no new table. Stored in `wp_fevents_meta` (`object_type='user'`, `meta_key='notification_prefs'`):

```json
{
	"event.join_requested": { "in_app": true, "email": true },
	"event.reminder": { "in_app": true, "email": false }
}
```

## Functional requirements

### FR-9.1 — Dispatch (background batch queue)

Dispatch is **always asynchronous** — the triggering request never resolves recipients or writes notification rows. It only enqueues, so a host action (approve, publish, cancel) returns immediately regardless of audience size.

**1. Enqueue (in-request, O(1)).** On a trigger the producer builds a *descriptor* — `action`, `actor_id`, `object_type`, `object_id`, `data`, `route`, and an **audience spec** (not a resolved list — e.g. `{ "type": "event_guests", "event_id": 9 }`). It enqueues one async job and returns. Fail-soft: the enqueue is wrapped so a queue failure never breaks the triggering action.

- Tech: Action Scheduler (`as_enqueue_async_action`), group `fluent-events`. An **async** action runs on the next loopback ASAP (seconds), not on the cron interval — so even a single-recipient notification appears near-instantly.

**2. Fan-out worker (background, batched, cursor-paginated).** The job `fluent_events/notify_fanout` processes the audience in pages:

- **Two audience kinds.** A **WP-user audience** (hosts/managers) is keyed by `user_id` → in-app rows **+** email. A **guest audience** (`event_guests`) is keyed by `email` only (anonymous, no account) → **email channel only, no in-app rows**.
- Resolve the next page from the audience spec — cursor ascending (`user_id` for WP-user audiences, guest row `id` for guest audiences), page ~1000. Re-derive from the spec each chunk; never carry a 100k-id list in the payload.
- **WP-user audiences:** drop recipients whose `in_app` pref for this `action` is off; **bulk-insert thin rows** (~500–1000 per insert) into `wp_fevents_notifications` with `INSERT IGNORE` against the unique `(recipient_id, action, object_id)` key.
- Hand the `email`-eligible slice to the email channel (FR-4.7) — its own chunked queue. Guest audiences go straight here (email is their only channel).
- Advance the cursor. If runtime exceeds a budget (~25s) reschedule self with the cursor; else continue until the audience is exhausted.

**3. Thin rows + render-at-read.** Rows store `action` + `object_id` + a small `data` blob — never a frozen content string. Title/content is rendered at read time (see design principles) by resolving the object, so a 100k-recipient fan-out is 100k skinny rows, not 100k HTML blobs.

**4. Idempotency.** The cursor lives in the action args and the unique index makes re-runs safe: a worker crash mid-fan-out re-runs from the persisted cursor, and duplicate inserts bounce off the key. At-least-once delivery, exactly-once rows.

**5. Inline fast-path (optional).** Default is always-queue. A producer that targets exactly one recipient (e.g. a direct approval) and wants instant feedback may write the single row inline instead of enqueuing — same row shape, same unique key. Not required.

Producers fire `do_action('fluent_events/notify', $descriptor)` and never insert notification rows directly; the notification module owns the single subscriber that enqueues the fan-out.

### FR-9.2 — In-app feed

- A notification list (bell) with an **unread count**; mark one or **all** read (`read_at`).
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
- Unread count reflects rows with `read_at IS NULL`; mark-all-read clears it.
- Clicking a notification lands on the right page via `route`.
- Turning off a notification type stops both its in-app and email delivery (per channel toggle).
- Old read notifications are pruned on schedule.

## Open questions

→ Tracked in [clarifying-questions.md](../../clarifying-questions.md).
