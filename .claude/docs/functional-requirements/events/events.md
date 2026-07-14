# FR-6: Events (core)

**Summary:** The main event feature. Hosts and managers (WP users, in wp-admin) create, set up, publish, and manage events. Visitors find, view, and join them — **anonymously, by entering their details (name, email)** — free or paid, with optional approval and a limit on spots. Covers content, location, online meetings, invites, guests, who-can-see, insights, embedding, copying, deleting, and recurring events.

**Builds on FR-4** (settings + permissions), **FR-7** (media for cover/gallery), **FR-8** ([calendars](../calendar/calendar.md) — an event can belong to a calendar). Joining needs **no account** — a guest is a `wp_fevents_guests` row keyed by email.

## Approach

- **New tables** `wp_fevents_events` + `wp_fevents_guests`. Extra per-event data goes in `wp_fevents_meta` (`object_type='event'`).
- **Only host/managers can manage events** (WP-admin capability `fevents_manage_events`). Visitors just join.
- **Joining is anonymous, no account.** A visitor enters their details (name, email, plus any custom form answers) and submits — this writes a `wp_fevents_guests` row. No login, signup, password, or account. Invite links and imports pre-create guest rows the same way. Guests are identified by `email` — there is **no** `user_id` link to WP users.
- **Outside services** — Google Places to pick a location + show a map; Google Meet or Zoom for online meetings. API keys are set in settings.
- **Payments** — go through **Paymattic** (our payment-forms plugin), which handles the gateways (Stripe, PayPal, etc.). Enabled in settings.

## Data model

**Table: `wp_fevents_events`**

| Column           | Type          | Notes                                                                                                              |
| ---------------- | ------------- | ------------------------------------------------------------------------------------------------------------------ |
| `id`             | BIGINT PK     |                                                                                                                    |
| `event_id`       | BIGINT        | FK → event                                                                                                         |
| `name`           | VARCHAR       | guest's provided name (anonymous entry)                                                                            |
| `email`          | VARCHAR       | guest's email — the identity key for an anonymous guest                                                            |
| `status`         | VARCHAR       | `invited` \| `requested` \| `approved` \| `attending` \| `waitlisted` \| `declined` \| `cancelled` \| `checked_in` |
| `source`         | VARCHAR       | `invite` \| `link` \| `import` \| `request`                                                                        |
| `form_response`  | JSON NULL     | answers to the join questions                                                                                      |
| `payment_status` | VARCHAR NULL  | `pending` \| `paid` \| `refunded` \| `failed` (paid events only; null = free)                                      |
| `payment_ref`    | VARCHAR NULL  | Paymattic transaction/submission id — link to the real record                                                      |
| `amount`         | DECIMAL NULL  | snapshot of what they paid                                                                                         |
| `currency`       | VARCHAR NULL  | snapshot ISO code                                                                                                  |
| `paid_at`        | DATETIME NULL |                                                                                                                    |
| `responded_at`   | DATETIME NULL |                                                                                                                    |
| `created_at`     | TIMESTAMP     |                                                                                                                    |
| `updated_at`     | TIMESTAMP     |                                                                                                                    |

Index: `(event_id, status)`, `(email)`.

**Table: `wp_fevents_guests`** — one row per person attached to an event.

| Column           | Type          | Notes                                                                                                              |
| ---------------- | ------------- | ------------------------------------------------------------------------------------------------------------------ |
| `id`             | BIGINT PK     |                                                                                                                    |
| `event_id`       | BIGINT        | FK → event                                                                                                         |
| `name`           | VARCHAR       | guest's provided name (anonymous entry)                                                                            |
| `email`          | VARCHAR       | guest's email — the identity key for an anonymous guest                                                            |
| `status`         | VARCHAR       | `invited` \| `requested` \| `approved` \| `attending` \| `waitlisted` \| `declined` \| `cancelled` \| `checked_in` |
| `source`         | VARCHAR       | `invite` \| `link` \| `import` \| `request`                                                                        |
| `form_response`  | JSON NULL     | answers to the join questions                                                                                      |
| `settings`       | JSON NULL     | reserved per-guest prefs/overrides; null = inherit. Future home of the per-event notification override (FR-9.3)     |
| `payment_status` | VARCHAR NULL  | `pending` \| `paid` \| `refunded` \| `failed` (paid events only; null = free)                                      |
| `payment_ref`    | VARCHAR NULL  | Paymattic transaction/submission id — link to the real record                                                      |
| `amount`         | DECIMAL NULL  | snapshot of what they paid                                                                                         |
| `currency`       | VARCHAR NULL  | snapshot ISO code                                                                                                  |
| `paid_at`        | DATETIME NULL |                                                                                                                    |
| `responded_at`   | DATETIME NULL |                                                                                                                    |
| `created_at`     | TIMESTAMP     |                                                                                                                    |
| `updated_at`     | TIMESTAMP     |                                                                                                                    |

Index: `(event_id, status)`, `(email)`.

**`status` values:**

| Value        | Meaning                                                        |
| ------------ | -------------------------------------------------------------- |
| `invited`    | Host invited them; not yet responded.                          |
| `requested`  | Asked to join; waiting on host approval.                       |
| `approved`   | Host said yes; spot not final yet (e.g. payment due).          |
| `attending`  | Confirmed; has a spot, counts toward capacity.                 |
| `waitlisted` | Event full; waiting for a spot (auto-promoted when one opens). |
| `declined`   | The join didn't happen — host rejected, or invite refused.     |
| `cancelled`  | Was in, then pulled out (or was removed).                      |
| `checked_in` | Showed up and checked in at the event.                         |

## Functional requirements

### FR-6.1 — Create & edit event

- A host/manager makes an event with: title, description, **special instructions**, cover image + gallery, start/end time, and timezone. (unfiltered #7, #18)
- Save as `draft`, then `publish`.
- **v1:** every event auto-files under the hidden **Default Calendar** (FR-8) — `calendar_id` is always set; picking a calendar arrives with multi-calendar later.

### FR-6.2 — Location & map

- Pick a physical address with **Google Places**; save the address + map coordinates.
- Show a **map preview** on the event page. (unfiltered #4, #6)

### FR-6.3 — Online meeting

- For online events, create or paste a **Google Meet or Zoom** link. (unfiltered #5)
- The link is shown only to people allowed to see it.

### FR-6.4 — Capacity, approval, free/paid

- Set a max number of spots (blank = unlimited). On join, check capacity; if **full**, the joiner is **auto-waitlisted** (`waitlisted`). When a spot frees (a guest cancels/declines), the oldest waitlisted guest is auto-promoted to `attending`. _(Waitlist may ship post-v1 — see Q5; the schema already supports it.)_
- Optionally require the **host to approve** who joins.
- Event can be **free or paid**; paid events take payment through **Paymattic** before the spot is confirmed.
- **Payment record = thin reference, not duplication.** Paymattic is the source of truth (gateways, receipts, refunds, card data). We store only `payment_status` / `payment_ref` / `amount` / `currency` / `paid_at` on the guest row. Flow: pay → Paymattic processes → **webhook** back → we set `payment_status = paid` + `payment_ref` → guest flips to `attending`. Receipts/refund details link out by `payment_ref`. No card/gateway data stored on our side (keeps us out of PCI scope).

### FR-6.5 — Visibility & discovery

- `public` (listed, anyone can find), `unlisted` (only people with the link), `private` (invite-only).
- Public events show in listings; they still follow calendar + space access rules. (unfiltered #12)

### FR-6.6 — Custom join form

- The host can add extra questions people answer when they join; answers are saved with the guest (`form_response`). (unfiltered #18)

### FR-6.7 — Invite guests, invite link, import

- Invite people by email, share an **invite link**, or **import** a guest list (CSV / Google Sheet). (unfiltered #9, #10, #11)
- Each creates a guest row (email, optional name). Invitees accept via an emailed event link — no account, no login.

### FR-6.8 — Guest list management

- Add or remove guests, change a guest's status, and see how many are in each status. (unfiltered #13)

### FR-6.9 — Request to join & approval

- A visitor asks to join and fills the form (name, email, custom questions); the host approves or declines. (joinee #1, #3)
- Once approved → the guest is `attending` and gets a confirmation email with the event link. No login.

### FR-6.10 — Event detail & listing

- The event page shows its details (based on who's allowed to see it). (joinee #2, #10)
- A listing page shows **upcoming** and **past** events. (joinee #9)

### FR-6.11 — Insights & custom share URL

- Per-event stats (views, sign-ups, conversions); the host can set a custom share link. (unfiltered #14)

### FR-6.12 — Embed

- A Gutenberg block + embed snippet to show an event somewhere else. (unfiltered #15)

### FR-6.13 — Clone event

- Copy an event (content + settings) into a new draft. (unfiltered #17)

### FR-6.14 — Delete event

- Delete an event (soft-delete, with confirmation). If it was published, tell the guests. (unfiltered #16)

### FR-6.15 — Reminders

- Send a reminder as the event date gets close (background job + email FR-4.7). (joinee #6)

### FR-6.16 — Recurring events

- The host can set a **repeat rule** when creating an event — daily / weekly / monthly, an interval, specific days, and an end (a date or a number of times). Stored as `recurrence_rule` on the **series master**.
- The system **generates occurrences** as separate event rows linked by `parent_event_id`. Each occurrence has its own date, guests, and capacity — people join one occurrence, not the whole series.
- Occurrences are materialized on a rolling window (background job), not all at once, so an open-ended series doesn't create infinite rows.
- **Edit / cancel scope:** this occurrence only, this + following, or the whole series.
- Deleting the master cancels the series (and its future occurrences).

**How it works:**

- **Rule** (RRULE-like JSON on the master, anchored to its `starts_at`):
  ```json
  {
  	"freq": "weekly",
  	"interval": 1,
  	"byday": ["MO", "WE"],
  	"until": "2026-09-01"
  }
  ```
  (`count: N` instead of `until` = "N times".)
- **Generation:** a background job (action-scheduler) computes occurrence dates from the rule — using a proven RRULE library (e.g. `simshaun/recurr`), not hand-rolled date math (DST + month-end edge cases) — and creates an occurrence row per date.
- **Rolling window:** generate only occurrences within a window (e.g. next 90 days / next N), not to infinity. The job re-runs and tops up as dates pass, so an open-ended series stays bounded. The master tracks `generated_until` (in `settings`/meta) so re-runs don't duplicate.
- **Timezone:** occurrence datetimes computed in the event's `timezone` (DST-aware), then stored as concrete `starts_at` per occurrence.
- **Edit semantics:** "this occurrence" marks that row overridden (regeneration won't overwrite it); "this + following" ends the master's rule before this date and spawns a new master from here; "whole series" updates the master + future non-overridden occurrences.

## Acceptance criteria

- A manager creates a draft, publishes it, and it shows in listings based on its visibility.
- Joining a free, no-approval event → the member is `attending` right away.
- Approval event → the member stays `requested` until the host approves; a paid event isn't confirmed until payment succeeds.
- When an event is full → new joiners are auto-`waitlisted`; freeing a spot auto-promotes the oldest waitlisted to `attending`.
- Invite by email, invite link, and import each create the right guest rows (email + optional name), no account flow.
- Cloning makes a separate draft; deleting removes the event and notifies confirmed guests.
- Event times show in each viewer's own timezone.

## Open questions

→ Tracked in [clarifying-questions.md](../../clarifying-questions.md) (events section).
