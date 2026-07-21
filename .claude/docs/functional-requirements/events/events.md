# FR-6: Events (core)

**Summary:** The main event feature. Hosts and managers (WP users, in wp-admin) create, set up, publish, and manage events of **any kind** (meetup, wedding, conference, wordcamp…). Visitors register **anonymously** (no account) — pick a **ticket tier**, choose how many **tickets**, and enter details (name/email/phone + a dynamic form) **per ticket**. Events are online / offline / hybrid, free or paid, open or approval-gated, with per-tier capacity. Covers content, location/online, ticket types, multi-ticket registration, dynamic per-attendee forms, invites, guests, check-in, visibility, insights, embedding, cloning, deleting, and recurring events.

**Builds on FR-4** (settings + permissions), **FR-7** (media for cover/gallery), **FR-8** ([calendars](../calendar/calendar.md)). Registration needs **no account** — a registration + its guest rows are keyed by email, no `user_id` link.

## Approach

- **Four tables**: `wp_fevents_events` (the event) → `wp_fevents_ticket_types` (price tiers) → `wp_fevents_registrations` (one purchase: buyer + payment) → `wp_fevents_guests` (one row per ticket/attendee). Extra per-event data goes in `wp_fevents_meta` (`object_type='event'`).
- **Only host/managers manage events** (WP-admin capability `fevents_manage_events`). Visitors register.
- **Registration is anonymous, no account.** A visitor picks a tier + quantity, enters the buyer contact (name/email/phone) and one set of details **per ticket**, and submits → one `registrations` row + N `guests` rows. No login/signup/password. Invite links + imports pre-create these rows the same way. Keyed by `email`, no `user_id`.
- **One buyer, many tickets.** Buy 3 → **1 registration** (one payment) + **3 guests** (three attendees' info). Each ticket has its own name/email/phone + form answers.
- **Ticket tiers.** Each event has ≥1 `ticket_types` row. If the host defines none, one **"General"** tier is auto-created (price 0). Free event = one free tier.
- **Dynamic form.** The event defines fields in `events.form_fields` (JSON); each attendee's answers save to `guests.form_response` (JSON).
- **Mode** via `events.venue_type` (online / offline / hybrid); **policy** via `events.join_policy` (open / approval / invite_only).
- **Outside services** — Google Places (location + map), Google Meet / Zoom (online). Keys in settings.
- **Payments** — **Paymattic** (Stripe/PayPal). Payment is **per registration** (one payment covers all its tickets), not per guest.

## Data model

Chain: **event → ticket_types → registrations → guests**. Full DDL in [schema.sql](../../db/schema.sql); key columns below.

**Event = CPT hybrid.** Identity (title, description, slug, status, author, cover) lives
in **`wp_posts`** (`post_type=fevents_event`); config (`_fevents_form_fields`,
`_fevents_settings`, addon fields) in **`wp_postmeta`**; typed relational data in
**`wp_fevents_events`** (1:1 via `post_id`). Category / venue / organizer are **WP
taxonomies** on the post (`fevents_event_type` / `fevents_venue` / `fevents_organizer`) —
the physical place (address/map) is `fevents_venue` term meta, not an event column. See
[db/implementation.md](../../db/implementation.md).

**`wp_fevents_events`** — the typed extension row (what ticketing FKs to):

| Column | Type | Notes |
| ------ | ---- | ----- |
| `post_id` | BIGINT FK UNIQUE | 1:1 → the `wp_posts` event row + taxonomy anchor |
| `status` | VARCHAR | lifecycle: `active` \| `cancelled` \| `completed` (editorial draft/publish = `post_status`) |
| `start_at` / `end_at` / `timezone` | DATETIME / VARCHAR | indexed date window |
| `venue_type` | VARCHAR | `online` \| `offline` \| `hybrid` |
| `online_meeting_url` | VARCHAR | online/hybrid link |
| `join_policy` | VARCHAR | `open` \| `approval` \| `invite_only` |
| `calendar_id` | BIGINT FK | → calendar (grouping stays a custom table) |
| `currency` | VARCHAR NULL | ISO 4217 for the event (one currency per event) |
| `waitlist_enabled` | TINYINT | auto-waitlist when a tier is full |

*(the **dynamic form definition** now lives in `wp_postmeta._fevents_form_fields`, not a
table column.)*

**`wp_fevents_ticket_types`** — price tiers.

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT PK | |
| `event_id` | BIGINT FK | → event |
| `type` | VARCHAR | tier name; auto **"General"** if none defined |
| `price` | DECIMAL(10,2) | actual charge; `0` = free (currency on the event) |
| `compare_at_price` | DECIMAL(10,2) NULL | "was" price, shown struck-through (early-bird). Display-only; NULL = not on sale. Maps 1:1 to FluentCart `compare_price`. Coupons/discount codes stay in FluentCart. |
| `capacity` | INT NULL | seats for this tier; null = unlimited |
| `sold` | INT | cached — **remaining = capacity − sold**; sold-out is computed |
| `min_per_reg` / `max_per_reg` | INT | tickets-per-registration limits |
| `sale_start_at` / `sale_end_at` | DATETIME NULL | early-bird windows |
| `status` | VARCHAR | `active` \| `hidden` |
| `sort_order` | INT | |

Index: `(event_id, status)`.

**`wp_fevents_registrations`** — one purchase (buyer + payment, N tickets). **Payment lives here.**

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT PK | |
| `event_id` | BIGINT FK | → event |
| `buyer_name` / `buyer_email` / `buyer_phone` | VARCHAR | the registrant |
| `tickets` | INT | number of tickets in this registration |
| `status` | VARCHAR | `pending` \| `confirmed` \| `cancelled` \| `refunded` |
| `payment_status` | VARCHAR | `free` \| `pending` \| `paid` \| `refunded` \| `failed` |
| `payment_ref` | VARCHAR NULL | Paymattic txn id (thin reference — no card/gateway data our side) |
| `total_amount` | DECIMAL | order total (currency on the event) |
| `paid_at` | DATETIME NULL | |
| `source` | VARCHAR | `invite` \| `link` \| `import` \| `request` |

Index: `(event_id, status)`, `(buyer_email)`.

**`wp_fevents_guests`** — one row per **ticket** (each attendee).

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT PK | |
| `event_id` | BIGINT FK | → event (denormalized for queries) |
| `registration_id` | BIGINT FK | → registration |
| `ticket_type_id` | BIGINT FK | → ticket tier |
| `name` / `email` / `phone` | VARCHAR NULL | this attendee |
| `status` | VARCHAR | see status values below |
| `form_response` | JSON NULL | this attendee's answers |
| `ticket_code` | VARCHAR UNIQUE | QR / check-in code |
| `checked_in_at` | DATETIME NULL | offline check-in |

Index: UNIQUE `(ticket_code)`, `(event_id, status)`, `(registration_id)`, `(email)`.

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

> **Permissions are separate.** Who can *manage* the event = WP users + capabilities (`_fevents_roles` in `wp_fevents_meta`, FR-4). The organizer **taxonomy** is **display/credit only** — a term, no `user_id`, no permissions. A WP-user organizer who also manages appears as an organizer term **and** holds a capability there.

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

- **Ticket tiers** (`ticket_types`): each event has ≥1 tier (auto **"General"** if the host defines none). A tier sets `price` (0 = free), `capacity`, `min_per_reg` / `max_per_reg`, and an optional sale window.
- **Capacity is per tier** (total event capacity = sum of tier capacities). `remaining = capacity − sold`; a tier is **sold-out when `remaining ≤ 0`** (computed, not a status). On registration, check remaining; if **full**, the ticket is **auto-waitlisted** (`waitlisted`). Freeing a ticket auto-promotes the oldest waitlisted guest. _(Waitlist may ship post-v1; schema supports it.)_
- **Approval** via `events.join_policy = approval` — the registration/guests sit at `requested`/`pending` until the host approves.
- **Free or paid.** Paid events take payment through **Paymattic** before the registration is `confirmed`. Payment is **per registration** (one payment for all its tickets).
- **Payment record = thin reference on the registration.** Paymattic is the source of truth (gateways, receipts, refunds, cards). We store only `payment_status` / `payment_ref` / `total_amount` / `currency` / `paid_at` on `registrations`. Flow: pay → Paymattic → **webhook** → set `payment_status = paid` + `payment_ref` → registration `confirmed`, its guests `attending`. No card/gateway data our side (out of PCI scope).

### FR-6.5 — Visibility & discovery

- `public` (listed, anyone can find), `unlisted` (only people with the link), `private` (invite-only).
- Public events show in listings; they still follow calendar + space access rules. (unfiltered #12)

### FR-6.6 — Dynamic registration form

- The host defines custom fields (the form) on the event → stored in `events.form_fields` (JSON: field key, label, type, required, options). (unfiltered #18)
- On registration the form is asked **per ticket** — each attendee's answers save to that guest's `form_response` (JSON). Buy 3 → 3 sets of answers.
- Buyer contact (name/email/phone) is captured on the `registration`; per-attendee identity + answers on each `guest`.

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
