# FR-6: Events (core)

**Summary:** The main event feature. Hosts and managers (WP users, in wp-admin) create, set up, publish, and manage events of **any kind** (meetup, wedding, conference, wordcamp…). Visitors register **anonymously** (no account) — pick a **ticket tier**, choose how many **tickets**, and enter details (name/email + a dynamic form) **per ticket**. Events are online / offline / hybrid, free or paid, open or approval-gated, with per-tier capacity. Covers content, location/online, ticket types, multi-ticket registration, dynamic per-attendee forms, invites, guests, check-in, visibility, insights, embedding, cloning, deleting, and recurring events.

**Builds on FR-4** (settings + permissions), **FR-7** (media for cover/gallery), **FR-8** ([calendars](../calendar/calendar.md)). Registration needs **no account** — guest rows (grouped by `group_id`) are keyed by email; a nullable `user_id` **optionally** links a row to a WP account when the attendee happens to be logged in.

## Approach

- **Three tables**: `wp_fe_event_details` (the event) → `wp_fe_ticket_types` (price tiers) → `wp_fe_guests` (one row per ticket/attendee; the buyer's row also carries the order + payment). Extra per-event data goes in `wp_fe_meta` (`object_type='event'`).
- **Only host/managers manage events** (WP-admin capability `fevents_manage_events`). Visitors register.
- **Registration is anonymous, no account.** A visitor picks a tier + quantity, enters details **per ticket**, and submits → **N `guests` rows** sharing one `group_id` (the purchase group), **one flagged `is_buyer=1`**. No login/signup/password. Invite links + imports pre-create the same rows. Keyed by `email`; `user_id` is set only when the registrant is a logged-in WP user (else NULL).
- **Merged order + attendee (no separate registrations table).** Most orders are 1 ticket, so a split order-row would double the writes for the common case. Instead a purchase is a **group of guest rows** sharing `group_id`; the **`is_buyer=1` row** carries the order/payment fields (`payment_status`, `payment_ref`, `price`, `paid_at`, `source`) — **NULL on companion rows**. Buyer contact = that row's own `name`/`email`.
  - **Why this stays clean:** `SUM(price)` ignores the NULL companions → correct revenue with no `WHERE` guard; a payment webhook/refund updates **one** row (`WHERE group_id=? AND is_buyer=1`); ticket count = `COUNT(*)` of the group; a full order = `SELECT * WHERE group_id=?`.
- **One buyer, many tickets.** Buy 3 → **3 guest rows**, one `is_buyer=1` (holds the single payment), each with its own name/email + form answers.
- **Ticket tiers.** Each event has ≥1 `ticket_types` row. If the host defines none, one **"General"** tier is auto-created (price 0). Free event = one free tier.
- **Dynamic form.** The event defines fields in `event_details.form_fields` (JSON column); each attendee's answers save to `guests.form_response` (JSON).
- **Mode** via `event_details.venue_type` (online / offline / hybrid); **policy** via `_fevents_settings.join_policy` (open / approval / invite_only) in `wp_postmeta`.
- **Entry mode** via `_fevents_settings.registration_type` (`wp_postmeta`) — three ways in, one set of tables:
  - **`ticketed`** — buy tickets. Guest rows **require** `ticket_type_id` (paid or free tier), payment on the `is_buyer=1` row. (`ticket_type_id` is now nullable at the DB level — ticketed mode enforces it in the service layer.)
  - **`rsvp`** — free "I'm coming". Guest rows with **`ticket_type_id` = NULL** (no tier at all). Going / not-going tracked purely via `status` (`attending` / `declined`). Headcount = `COUNT(*) WHERE status='attending'`.
  - **`open`** — no registration. **Zero guest rows, zero ticket_types.** Just a published listing (unlimited, free, show up). The event = its `wp_posts` + `wp_fe_event_details` row, no children.
  - These compose with `join_policy` (an `rsvp` event can be approval-gated) and capacity (unlimited when there's no tier / `ticket_types.slot IS NULL`).
- **Outside services** — Google Places (location + map), Google Meet / Zoom (online). Keys in settings.
- **Payments** — **Paymattic** (Stripe/PayPal). Payment is **per order** (one payment covers all tickets in a `group_id` group), recorded on the `is_buyer=1` row — not per guest.

## Data model

Chain: **event → ticket_types → guests** (a purchase = guest rows sharing `group_id`). Full DDL in [schema.sql](../../db/schema.sql); key columns below.

**Event = CPT hybrid.** Identity (title, description, slug, status, author) lives
in **`wp_posts`** (`post_type=fevents_event`); addon config in **`wp_postmeta`**; typed
relational data + the per-guest form in **`wp_fe_event_details`** (1:1 via `post_id`).
Category / venue / organizer / performer are **WP taxonomies** on the post — the physical
place (address/map) is `fevents_venue` term meta, not an event column. See
[db/implementation.md](../../db/implementation.md).

**`wp_fe_event_details`** — the typed extension row (what ticketing FKs to). Also carries
**recurrence occurrences** (see FR-6.16):

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT PK | internal PK — what ticketing FKs to (`event_id`) |
| `post_id` | BIGINT **NOT NULL** UNIQUE | 1:1 → the `wp_posts` event row + taxonomy anchor. **Every** details row has its own post — including each recurrence occurrence. |
| `status` | VARCHAR NOT NULL | lifecycle: `active` \| `cancelled` (upcoming/live/past = derived from dates; editorial draft/publish = `post_status`) |
| `start_at` / `end_at` | DATETIME **NOT NULL** (**UTC**) | stored in UTC; the indexed date window |
| `timezone` | VARCHAR NOT NULL | IANA tz (e.g. `Asia/Dhaka`) — display, DST, ICS, reminders |
| `calendar_id` | BIGINT NOT NULL FK | → calendar (`RESTRICT`) |
| `parent_event_id` | BIGINT NULL FK→self | NULL = master/standalone; set = a recurrence **occurrence** of that master (`ON DELETE CASCADE`) |
| `venue_type` | VARCHAR NOT NULL | event **mode**: `online`/`offline`/`hybrid`. Place = `fevents_venue` term (address/map in term meta) |
| `form_fields` | JSON NULL | **per-guest dynamic form definition** (fields to collect from each attendee) |
| `created_at` / `updated_at` | TIMESTAMP NOT NULL | |

**Every row is self-contained — no inheritance.** There is no `settings` column, no
`is_overridden`, and no NULL-means-inherit rule. Each details row (master *or* occurrence)
carries its own complete typed values, and reads are a plain `SELECT` — no `COALESCE`
against a parent, no `array_merge` of JSON blobs.

**Per-event config JSON lives in `wp_postmeta`** under `_fevents_settings`, on the event's own
post — available for every row precisely because `post_id` is now NOT NULL. It holds what
used to be the `settings` column:

```json
// wp_postmeta: _fevents_settings
{ "registration_type": "ticketed",        // open | rsvp | ticketed  (entry mode)
  "join_policy":       "open",            // open | approval | invite_only
  "cover_url":         "https://…/cover.jpg",
  "online_meeting_url": "https://meet…",
  "waitlist_enabled":  true,
  "recurrence":        { "rrule": "…", "generated_until": "…", "window_days": 90 } }
```

*(Venue place data → `fevents_venue` term meta; the online link is per-event in
`_fevents_settings`, never term meta.)*

**`wp_fe_ticket_types`** — price tiers.

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT PK | |
| `event_id` | BIGINT FK | → event |
| `tier` | VARCHAR | tier name (`General`, `VIP`, `Early Bird`); auto **"General"** if none defined |
| `price` | DECIMAL(10,2) | actual charge; `0` = free. **No currency column** — currency is a single global plugin setting (FR-4), not per event or per tier |
| `slot` | INT NULL | seats for this tier; null = unlimited |
| `sold` | INT | stored counter — **remaining = slot − sold**; reserved via atomic `UPDATE … WHERE sold+q≤slot` (CAS, no oversell); display sold/remaining = O(1) |
| `min_per_reg` / `max_per_reg` | INT | tickets-per-registration limits |
| `sale_start_at` / `sale_end_at` | DATETIME NULL | early-bird windows |
| `status` | VARCHAR | `active` \| `hidden` |
| `sort_order` | INT | admin display order of tiers |
| `settings` | JSON NULL | **view-only / cosmetic fields** — holds `compare_at_price` (the struck-through "was" price, maps to FluentCart `compare_price`) + future per-tier config. Nothing here is queried; real discount codes stay in FluentCart. |

Index: **none declared** — `event_id` is a FK, so InnoDB auto-creates `(event_id)` and that's
all this table needs. The row count is large (events × 3–5 tiers → tens of thousands), so the
`event_id` filter is highly selective and *must* be indexed — but with only 3–5 tiers per
event, adding `status` as a second column saves about one row fetch, which doesn't justify the
wider index. *(Contrast `calendars`, where the whole table is 10–100 rows and indexes beyond
the slug unique are pure overhead: index need = table size ÷ result size, not result size.)*

Only queried columns are real (event_id, status, slot, sold, sale window, price, sort_order);
display-only fields live in `settings`. `min_per_reg` defaults to `1`.

**`wp_fe_guests`** — one row per **ticket** (each attendee). A purchase is a **group** of these rows sharing `group_id`; the **`is_buyer=1`** row also carries the order + payment (NULL on companions). No separate registrations table.

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT PK | |
| `event_id` | BIGINT FK | → event (denormalized for queries) |
| `calendar_id` | BIGINT FK | → calendar (denormalized from the event's effective calendar; direct calendar-scoped queries) |
| `group_id` | VARCHAR | **order group key** — same value across all tickets of one purchase (generated at checkout; `random_bytes`/`wp_generate_password`) |
| `is_buyer` | TINYINT(1) | `1` on the payer's row (carries order/payment); `0` on companions. Exactly one `1` per group |
| `ticket_type_id` | BIGINT NULL FK | → ticket tier. Set in **ticketed** mode; **NULL for RSVP** (no tier). `RESTRICT` — can't delete a tier that has tickets |
| `name` / `email` | VARCHAR NULL | this attendee (name + email; no phone). The `is_buyer` row's = the payer's contact |
| `user_id` | BIGINT NULL | → WP user, **optional** — set when the attendee is logged in; NULL for anonymous. Enables "my tickets" + in-app notifications. FK enforced in app (core ref) |
| `status` | VARCHAR | attendee status — see values below |
| `form_response` | JSON NULL | this attendee's answers |
| `hash` | VARCHAR UNIQUE | QR / check-in code (per ticket) |
| `checked_in_at` | DATETIME NULL | offline check-in |
| **— order/payment (only on `is_buyer=1`, else NULL) —** | | |
| `payment_status` | VARCHAR NULL | `free` \| `pending` \| `paid` \| `refunded` \| `failed` |
| `payment_ref` | VARCHAR NULL | Paymattic txn id (thin reference — no card/gateway data our side) |
| `price` | DECIMAL NULL | **order total** — sum of all tickets in the group (currency = the global plugin setting); NULL companions → `SUM` = revenue, no guard. (≠ `ticket_types.price`, the unit tier price) |
| `paid_at` | DATETIME NULL | |
| `source` | VARCHAR NULL | `invite` \| `link` \| `import` \| `request` |

Index: UNIQUE `(hash)`, `(event_id, status)`, `(calendar_id, status)`, `(group_id)`, `(email)`.

- **Revenue** = `SUM(price)` (companions NULL, no `WHERE` guard). **Orders** = `COUNT(*) WHERE is_buyer=1`. **Order total tickets** = `COUNT(*)` of the `group_id` group. **Payment update** (webhook/refund) = `UPDATE … WHERE group_id=? AND is_buyer=1` (1 row). **Whole order** = `SELECT … WHERE group_id=?`.

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

**Per entry mode:** `ticketed` uses the payment-driven flow (`approved` → `attending` after pay, `waitlisted` when full). `rsvp` reuses the **same column, no new values** — `attending` = "going", `declined` = "not going", `waitlisted` = capped-RSVP overflow. `open` writes no guest rows at all, so `status` never applies. Keep one vocabulary; the mode decides which subset is in play.

### Classification + roster

**Organizer, venue, event type, performer = WP taxonomies** on the `fevents_event` post
(`fevents_organizer` / `fevents_venue` / `fevents_event_type` / `fevents_performer`) —
reusable across events, filterable, each with a free public archive + auto event count.
Extra fields live in `wp_termmeta` (organizer: email/phone/website/logo; venue:
address/city/map; type: color/icon; performer: photo/designation/social). Assign with
`wp_set_object_terms($post_id, $termIds, $tax)`; query with `tax_query`. Full flows in
[db/implementation.md](../../db/implementation.md).

> **Performer is intentionally thin** — a name + photo you tag events with, no bio/profile
> page. If rich speaker profiles are ever needed it'd migrate to a CPT; for now taxonomy
> is enough. There is **no `people` table**.

**Sponsors = deferred** — no sponsor table in this milestone. If sponsors return they'll
be a taxonomy (`fevents_sponsor`, like organizer), not a custom table.

> **Permissions are separate.** Who can *manage* the event = WP users + capabilities (`_fevents_roles` in `wp_fe_meta`, FR-4). The organizer **taxonomy** is **display/credit only** — a term, no `user_id`, no permissions. A WP-user organizer who also manages appears as an organizer term **and** holds a capability there.

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

### FR-6.4 — Ticket tiers, capacity, approval, free/paid

- **Ticket tiers** (`ticket_types`): each event has ≥1 tier (auto **"General"** if the host defines none). A tier sets `price` (0 = free), `slot` (seats; null = unlimited), `min_per_reg` / `max_per_reg`, and an optional sale window.
- **Capacity is per tier** — the `slot` column (total event capacity = sum of tier slots). `remaining = slot − sold`; a tier is **sold-out when `remaining ≤ 0`** (computed, not a status). On registration, check remaining; if **full**, the ticket is **auto-waitlisted** (`waitlisted`). Freeing a ticket auto-promotes the oldest waitlisted guest. _(Waitlist may ship post-v1; schema supports it.)_
- **Approval** via `_fevents_settings.join_policy = approval` — the registration/guests sit at `requested`/`pending` until the host approves.
- **Free or paid.** Paid events take payment through **Paymattic** before the order is confirmed. Payment is **per order** (one payment for all tickets in a `group_id` group).
- **Payment record = thin reference on the `is_buyer=1` row.** Paymattic is the source of truth (gateways, receipts, refunds, cards). We store only `payment_status` / `payment_ref` / `price` / `paid_at` on that row (currency is a global plugin setting, stored nowhere per-record), NULL on companion tickets. Flow: pay → Paymattic → **webhook** → `UPDATE … WHERE group_id=? AND is_buyer=1` sets `payment_status = paid` + `payment_ref` → the group's guests go `attending`. No card/gateway data our side (out of PCI scope).

### FR-6.5 — Visibility & discovery

- `public` (listed, anyone can find), `unlisted` (only people with the link), `private` (invite-only).
- Public events show in listings; they still follow calendar + space access rules. (unfiltered #12)

### FR-6.6 — Dynamic registration form

- The host defines custom fields (the form) on the event → stored in `events.form_fields` (JSON: field key, label, type, required, options). (unfiltered #18)
- On registration the form is asked **per ticket** — each attendee's answers save to that guest's `form_response` (JSON). Buy 3 → 3 sets of answers.
- Buyer contact (name/email) is the `is_buyer=1` guest row's own name/email; per-attendee identity + answers on each `guest` row of the group.

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

- The host sets a **repeat rule** — daily / weekly / monthly / yearly, an interval, specific days, and an end (a date or N times). Stored as a standard **iCal RRULE string** on the **series master** (`_fevents_settings.recurrence.rrule`).
- Occurrences are **fully materialized** — each is its own `wp_posts` row **plus** its own `wp_fe_event_details` row with `parent_event_id` = master. Every value is copied at generation time; nothing is inherited at read time. People join one occurrence, not the whole series.
- Materialized on a **rolling window** by a daily cron — not all at once, so an open-ended series stays bounded.
- **Edit / cancel scope:** this occurrence, this + following, or the whole series.
- Deleting the master **cascades** its occurrences (`parent_event_id` FK ON DELETE CASCADE).

**How it works:**

- **Rule = an iCal RRULE string** on the master (`_fevents_settings.recurrence`, in `wp_postmeta`). The standard already covers everything, incl. future nth-weekday patterns — **no schema change ever**:
  ```json
  { "recurrence": {
      "rrule": "FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE;UNTIL=20260901",
      "generated_until": "2026-06-30",
      "window_days": 90 } }
  ```
  `FREQ=MONTHLY;BYDAY=1SU` = *first Sunday of each month*; `BYDAY=-1FR` = *last Friday*. Prefer the RRULE string over bespoke JSON — the vocabulary is free.
- **Generation:** a daily **Action Scheduler** job expands the RRULE with a proven library (**`rlanvin/php-rrule`**, what FluentBooking uses — never hand-rolled date math / DST bugs) and creates the missing occurrences up to `now + window_days`, advancing `generated_until`. Each occurrence = **one `wp_posts` insert** (title/body/taxonomies copied from the master) **+ one `wp_fe_event_details` insert** with `parent_event_id` set and every typed value copied. **Dedup** by checking `parent_event_id` + `start_at` before insert, so re-runs never double-create. `window_days` = master override else a global plugin default.
- **Occurrence = a full standalone event.** Own post, own taxonomies, own complete details row. Reads never touch the master. The generator copies once at creation; after that the two are independent.
- **Timezone:** occurrence `start_at` computed from the rule in the master's `timezone` (DST-aware via the library), stored as concrete UTC per occurrence.
- **Override one occurrence** (e.g. "this Monday moved online"): just edit that occurrence — it owns all its fields (`venue_type='online'`, its own `_fevents_settings.online_meeting_url`). No override flag is needed: the generator only ever *creates future missing* occurrences, it never rewrites existing rows, so an edited occurrence is safe by construction. This is why `is_overridden` was dropped.
- **Edit semantics:** *this occurrence* → edit that row/post directly; *this + following* → set `UNTIL` on the master's RRULE before this date, spawn a new master from here; *whole series* → update the master **and fan the change out to future occurrences explicitly** (they no longer inherit, so a series-wide edit is a real bulk update — queue it via Action Scheduler for large series).
- **Trade-off of full materialization:** heavier writes and more rows (one post + one details row per occurrence) and series-wide edits become explicit bulk updates. In exchange, every read is a plain indexed `SELECT` with no parent join or `COALESCE`, an occurrence can differ from its master in *any* way (including title/body/taxonomies, impossible under the old shared-post model), and the whole inheritance-resolution layer disappears from the codebase.

### FR-6.17 — Classification & organizers

- Assign **organizers**, **venue**, **event type**, and **performers** by picking/creating **taxonomy terms** (`fevents_organizer` / `fevents_venue` / `fevents_event_type` / `fevents_performer`) — reusable across events, each surfaced on a filterable public archive with an auto event count; details (email/phone/address/map/logo/photo) come from term meta.
- Performer terms are **thin** (name + photo + designation) — no bio/profile page this milestone. **Sponsors** are deferred (future taxonomy `fevents_sponsor`).
- Any event type uses as many or few as it needs (a wedding: none; a conference: many organizers + speakers).

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
