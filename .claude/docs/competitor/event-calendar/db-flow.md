# The Events Calendar + Event Tickets — end-to-end DB flow

> Source-verified trace of every database write/read across one full lifecycle:
> admin creates a fully-populated event (event + venue + organizer + tickets),
> visitors browse and buy, then check-in and refund. Traced against **The Events
> Calendar 6.17.0** + **Event Tickets (Tickets Commerce)** source; exact meta_key
> and status-slug strings grepped + adversarially verified.

Visual summary: [db-flow.svg](db-flow.svg). Schema: [schema-combined.svg](schema-combined.svg). Teardown: [README.md](README.md).


This traces the exact database writes across a full lifecycle: an admin creates a fully-populated event (event + venue + organizer + tickets), then anonymous visitors browse and buy tickets, followed by check-in and refund. The through-line: **everything is `wp_posts` + `wp_postmeta`.** Events, venues, organizers, ticket types, orders, and every individual attendee are each their own `wp_posts` row, and virtually all of their fields are `wp_postmeta` rows. The only relational custom tables in play are TEC's date/occurrence index (`tec_events` / `tec_occurrences`) and the order-modifier (fee/coupon) tables.

Versions traced: The Events Calendar 6.17.0; Event Tickets (Tickets Commerce).

---

## 1. Admin Creates the Event (event + venue + organizer)

Saving one New Event form produces **three separate `wp_posts` rows** — the event (`tribe_events`), its venue (`tribe_venue`), and its organizer (`tribe_organizer`) — plus a large block of `_Event*` / `_Venue*` / `_Organizer*` postmeta, taxonomy rows, and a deferred upsert into the custom date tables.

### The meta engine
WP core inserts the event post first. Then `save_post` (priority 15) fires `Tribe__Events__Meta__Save::maybe_save()` → `Tribe__Events__API::saveEventMeta()`, which (a) computes all date/UTC/duration/timezone meta, (b) creates + links the venue and organizer via `Tribe__Events__Linked_Posts::handle_submission()`, and (c) loops `$tec->metaTags` writing every `_Event*` key.

### Ordered writes

| Step | Actor | Table(s) written | Rows / keys |
|------|-------|------------------|-------------|
| Submit form | admin | — | POST carries title, dates, timezone, cost, nested Venue[]/Organizer[], tax_input, featured_media. No DB write. |
| Insert event post | core | `wp_posts` | 1 row — `post_type='tribe_events'`, title/content/status |
| Assign taxonomy | core | `wp_terms`, `wp_term_taxonomy`, `wp_term_relationships` | term rows (if new) for `tribe_events_cat` + `post_tag`; one relationship row per term; `term_taxonomy.count` incremented |
| Featured image | core | `wp_postmeta` | `_thumbnail_id` = attachment ID |
| Post origin | system | `wp_postmeta` | `_EventOrigin` = `'events-calendar'` |
| Map flags | system | `wp_postmeta` | `_EventShowMapLink`, `_EventShowMap` |
| Insert **venue** post | system | `wp_posts` | 1 row — `post_type='tribe_venue'` (post #2) |
| Venue meta | system | `wp_postmeta` | `_VenueAddress`, `_VenueCity`, `_VenueProvince`, `_VenueState`, `_VenueStateProvince`, `_VenueZip`, `_VenuePhone`, `_VenueURL`, `_VenueCountry`, `_VenueShowMap`, `_VenueShowMapLink`, `_VenueOrigin` |
| Link venue → event | system | `wp_postmeta` | `_EventVenueID` = venue post ID (on the event) |
| Insert **organizer** post | system | `wp_posts` | 1 row — `post_type='tribe_organizer'` (post #3) |
| Organizer meta | system | `wp_postmeta` | `_OrganizerEmail`, `_OrganizerWebsite`, `_OrganizerPhone`, `_OrganizerOrigin` |
| Link organizer → event | system | `wp_postmeta` | `_EventOrganizerID` = organizer post ID (on the event) |
| metaTags loop — dates | system | `wp_postmeta` | `_EventStartDate`, `_EventEndDate`, `_EventStartDateUTC`, `_EventEndDateUTC`, `_EventDuration`, `_EventTimezone`, `_EventTimezoneAbbr`, `_EventAllDay` |
| metaTags loop — cost/misc | system | `wp_postmeta` | `_EventCost`, `_EventCurrencySymbol`, `_EventCurrencyCode`, `_EventCurrencyPosition`, `_EventCostMin`, `_EventCostMax`, `_EventURL`, `_EventPhone`, `_tribe_featured` |
| Publish linked posts | system | `wp_posts` | venue/organizer `post_status` → `publish` (only if newly created as drafts) |

### The custom-table upsert (deferred to end of request)
Each write of a **tracked date key** (`_EventStartDate`, `_EventStartDateUTC`, `_EventEndDate`, `_EventEndDateUTC`, `_EventDuration`, `_EventTimezone`, `_EventAllDay`) fires `added_post_meta`/`updated_postmeta`, caught by `Meta_Watcher::mark_for_update()` — which only **earmarks** the post_id in memory. The actual upsert commits at end-of-request via a trigger that varies by editor:

- **Classic Editor** → `redirect_post_location` filter (priority 100)
- **Block Editor / REST** → `rest_after_insert_tribe_events`
- **Programmatic saves** → `shutdown`

That trigger runs `Updates\Events::update()` → `Event::data_from_post()` re-reads the `_Event*` date meta → `Event::upsert(['post_id'])`:

| Table | Op | Row |
|-------|-----|-----|
| `tec_events` | upsert (UNIQUE on `post_id`) | 1 row — `event_id` PK, `post_id`, `start_date`, `end_date`, `timezone`, `start_date_utc`, `end_date_utc`, `duration`, `hash`, `updated_at` |
| `tec_occurrences` | insert | 1 row (single non-recurring event) — `occurrence_id` PK, `event_id`, `post_id`, dates, `hash` (unique), `updated_at` |
| `wp_options` | upsert | `tribe_events_calendar_options` — earliest/latest known date range, via `rebuild_known_range()` |

`saveEventMeta` ends by firing `do_action('tribe_events_update_meta')`. **Key truth:** the `tec_events`/`tec_occurrences` write is driven by the tracked postmeta writes, *not* directly by `save_post` — one `tribe_events` post = one `tec_events` row = one (or more, for recurring) `tec_occurrences` rows.

---

## 2. Admin Adds Ticket Types

*(The submitted trace for this phase was a stub; the verifier supplied the real Tickets Commerce facts, used here.)*

An admin (a logged-in user with edit capability — **not** "system") saving a ticket type produces a **new `wp_posts` row of `post_type='tec_tc_ticket'`** (Tickets Commerce; legacy providers use `tribe_tpp_tickets`/RSVP types), plus its capacity/stock/sale meta.

| Step | Actor | Table(s) written | Rows / keys |
|------|-------|------------------|-------------|
| Insert ticket post | admin | `wp_posts` | 1 row — `post_type='tec_tc_ticket'` |
| Price / capacity / stock | admin | `wp_postmeta` | `_price`, `_capacity`, `_stock` (**stored counter**, decremented on sale — not live-counted), `_stock_status` (in/out of stock) |
| Sale window | admin | `wp_postmeta` | `_ticket_start_date`, `_ticket_start_time`, `_ticket_end_date`, `_ticket_end_time` |
| Description flag + relation | admin | `wp_postmeta` | `_tribe_ticket_show_description`; ticket→event relation meta (Tickets Commerce event-relation key; RSVP/PayPal analogues are `_tribe_rsvp_for_event` / `_tribe_tpp_for_event`) |
| Shared/global capacity | admin | `wp_postmeta` (on the **event**) | tribe-common shared-capacity meta (`_tribe_ticket_global_stock_level` for the running level), separate from per-ticket `_capacity` |
| Provider record | admin | `wp_postmeta` (on the **event**) | `_tribe_default_ticket_provider` = which commerce provider handles this event's tickets |

**Key truth:** stock is a **stored counter** (`_stock` + `_stock_status`), not derived live at save time. The fixed `_capacity` is set here and never decremented by sales.

---

## 3. Visitor Browses (read path)

Loading the public single-event page is **read-only** against `wp_posts`/`wp_postmeta` + `tec_events`/`tec_occurrences`. The **only** write in this flow is the cart, and it happens **only on form submit**, never on page view.

### Event render — which tables join

| Step | Reads | Notes |
|------|-------|-------|
| Resolve event by slug | `wp_posts` (`post_name`, `post_type=tribe_events`) | standard WP single-post resolution |
| Custom Tables query redirect | `wp_posts` **JOIN `tec_occurrences`** ON `wp_posts.ID = tec_occurrences.post_id` | dates/order come from `tec_occurrences.start_date`, **not** `_EventStartDate` postmeta |
| Hydrate Event model | `tec_events` (+ `wp_postmeta` only as fallback), `wp_options` | `tribe_get_event()` reads timezone/duration/dates from the custom table |

### Ticket form (server-rendered Gutenberg block)
`Tickets::get_all_event_tickets($post_id)` runs the tickets ORM: `tribe_tickets('tickets-commerce')->by('event',$post_id)` — a `WP_Query` over `wp_posts` (`post_type=tec_tc_ticket`) filtered by `meta_key=_tec_tickets_commerce_event`. Each ticket is hydrated by `Commerce\Ticket::get_ticket()` from `wp_postmeta` (`_price`, `_stock`, `_manage_stock`, `_global_stock_mode`, `total_sales` (**no leading underscore**), `_tribe_ticket_capacity`, sale-price + date meta), memoized in `wp_cache` group `tec_tickets`.

### Availability is a HYBRID (stored counter + live count)
`available() = max(0, min(inventory(), capacity(), stock()))`:
- **`stock()`** reads the **stored** `_stock` postmeta counter (+ event `_tribe_ticket_global_stock_level` for shared mode) — maintained by order flag-actions.
- **`capacity()`** reads the fixed `_tribe_ticket_capacity`.
- **`inventory()`** and **`get_qty_pending()`** are **counted LIVE** from `tec_tc_attendee` posts — a real `WP_Query`/`found_posts` over `post_type=tec_tc_attendee` filtered by `_tec_tickets_commerce_ticket = ticket_id` and status meta `_tec_tickets_commerce_status = tec-tc-pending`.

So "sold/remaining" is **neither purely stored nor purely live** — it is the **lower of** a stored `_stock`/`total_sales` counter and a live `tec_tc_attendee` post count. (When stock/capacity is not managed, `available()` short-circuits to `-1` = unlimited before the `min()`.)

### Where the cart lives (write, on submit only)
The cart is **not** a post or meta row. On form submit (`Cart::parse_request` → `Unmanaged_Cart::process → save`):

| Table | Op | Row / key |
|-------|-----|-----------|
| `wp_options` | upsert | `_transient_tc-cart-<md5(cart_hash)>` + `_transient_timeout_tc-cart-<md5(cart_hash)>` — the cart items array (`ticket_id → {quantity, extra}`), `DAY_IN_SECONDS` TTL. **Only lands in `wp_options` when there is no persistent object cache**; otherwise it lives in the object cache. |
| (HTTP cookie) | set | `tec-tickets-commerce-cart` = 12-char `cart_hash` from `wp_generate_password(12, false)` — the key linking browser → transient |

**Verifier corrections applied:** the transient prefix is **`tc-cart-`**, *not* `tec-tc-cart-` (`Commerce::ABBR='tc'`). The pending attendee status value is **`tec-tc-pending`**, *not* `pending`, queried against status meta_key `_tec_tickets_commerce_status`. No attendee/order rows are created on browse — so browsing never changes `sold`/`_stock`.

---

## 4. Visitor Buys (checkout → pay → complete)

A purchase writes **one order post**, flips its status through a slug chain, generates **one attendee post per ticket**, and decrements stock via flag-actions.

### Order status slugs
All Tickets Commerce statuses are stored with a `tec-tc-` prefix (`'tec-' . Commerce::ABBR('tc') . '-' . SLUG`): `tec-tc-created` → `tec-tc-pending` → `tec-tc-completed` (and later `tec-tc-refunded`). **The order is INSERTED at `tec-tc-created`, not pending.** *(The `$insert_status = Created::class` property lives on `Status_Handler`, not `Order_Repository` — the repo reads it at runtime; resulting `post_status='tec-tc-created'` is correct.)*

### Ordered writes

| Step | Actor | Table(s) written | Rows / keys |
|------|-------|------------------|-------------|
| Create order from cart | visitor | `wp_posts` | 1 row — `post_type='tec_tc_order'`, `post_status='tec-tc-created'`, `post_parent=0` |
| Order meta | system | `wp_postmeta` | **`_tec_tc_order_items`** (full cart array, WP-serialized — each item: `event_id`, `ticket_id`, `quantity`, `price`, `sub_total`, `type` ∈ `ticket`/`fee`/`coupon`, `extra`); `_tec_tc_order_total_value`, `_tec_tc_order_subtotal_value`, `_tec_tc_order_currency`, `_tec_tc_order_hash`, `_tec_tc_order_gateway`; purchaser: `_tec_tc_order_purchaser_full_name`/`_first_name`/`_last_name`/`_email`/`_user_id` (=0 for guest) |
| Denormalized refs | system | `wp_postmeta` | `_tec_tc_order_tickets_in_order` (repeated row per distinct ticket_id), `_tec_tc_order_events_in_order` (repeated row per event_id) — `add_post_meta unique=false` |
| Attach gateway intent | system | `wp_postmeta` | `_tec_tc_order_gateway_order_id`, `_tec_tc_order_gateway_payload_created` (status-suffixed), `_tec_tc_order_on_checkout_screen_hold_timeout` |
| **created → pending** | system | `wp_posts` | `post_status` → `tec-tc-pending` |
| Status log + payload | system | `wp_postmeta` | `_tec_tc_order_status_log_pending` (ms timestamp), `_tec_tc_order_gateway_payload_pending` |
| Order lock | system | **`wp_posts.post_content_filtered` column** | `ORDER_LOCK_KEY = 'post_content_filtered'` — held during transition, released after |

**Order-lock correction applied:** the lock is **not** a `wp_postmeta` `_tec_tc_order_lock` row. `Order::ORDER_LOCK_KEY = 'post_content_filtered'` — it is written to the `wp_posts.post_content_filtered` **column** (the transition query is `... WHERE ID=%d AND post_content_filtered=%s`).

### Attendee generation + stock decrement fire at the INVENTORY-DECREASE status (default: PENDING)
The inventory-decrease status is `tribe_get_option('tickets-commerce-stock-handling', Pending::SLUG)` — **by default the PENDING transition above, not completed.** On that transition:

| Step | Actor | Table(s) written | Rows / keys |
|------|-------|------------------|-------------|
| Generate attendees | system | `wp_posts` | **ONE `tec_tc_attendee` post per ticket** (N rows) — `post_status='publish'`, **`post_parent = order ID`** (the order↔attendee link is `post_parent`, NOT a meta) |
| Attendee meta | system | `wp_postmeta` | `_tec_tickets_commerce_event`, `_tec_tickets_commerce_ticket`, `_tec_tickets_commerce_security_code`, `_tec_tickets_commerce_full_name`, `_tec_tickets_commerce_email`, `_tec_tickets_commerce_optout`, `_tec_tickets_commerce_price_paid`, `_tec_tickets_commerce_currency`; `_tribe_tickets_attendee_user_id` **only if buyer is a logged-in WP user** |
| Decrement stock | system | `wp_postmeta` (on **ticket** post) | `total_sales` += qty (**always**); `_stock` −= qty (own-capacity, only if `manage_stock()`) |
| Decrement shared stock | system | `wp_postmeta` (on **event** post) | `_tribe_ticket_global_stock_level` −= qty (shared/global mode). Fixed `_tribe_ticket_capacity` is **never** touched. |
| Coupon usage (if any) | system | `wp_tec_order_modifiers_meta` | upsert `coupons_uses += qty` for the coupon; guard `_tec_tickets_coupon_usage_calculated='calculated'` on the order post |
| **pending → completed** | system | `wp_posts` | `post_status` → `tec-tc-completed` (gateway return endpoint or async webhook) |
| Completed status log/payload | system | `wp_postmeta` | `_tec_tc_order_status_log_completed`, `_tec_tc_order_gateway_payload_completed` |
| Completed flag actions | system | `wp_postmeta` | `Backfill_Purchaser` may rewrite `_tec_tc_order_purchaser_email`; `count_*` flags update sales/attendee counters; `Send_Email*` dispatch **async** (Action Scheduler / tribe async) — not a DB write |

**Notes on the create path:** `_tec_tickets_commerce_checked_in` is **NOT** written at creation — an attendee is "not checked in" by *absence* of the key (read defaults to 0). `_tec_tickets_commerce_status` and purchaser_name/email are read-with-fallback keys, not written by create. Idempotency: attendees are upserted by `(post_parent=order, ticket_id)`, so webhook retries don't duplicate. Fee/coupon **definition** rows in `tec_order_modifiers` / `tec_order_modifier_relationships` are admin-time data — only **read** during checkout; the applied amounts ride inside the serialized `_tec_tc_order_items`.

**Key truths:** the order↔ticket link is the **serialized `_tec_tc_order_items` blob**; the order↔attendee link is **`post_parent`**; there is **one `tec_tc_attendee` post per ticket**; stock is decremented via a **stored counter** (`_stock`/`total_sales`) at the pending (inventory-decrease) transition by default.

---

## 5. Post-Purchase (confirmation, my-tickets, check-in, refund)

Everything here is driven by `post_status` transitions on the `tec_tc_order` post fanning out `do_action('tec_tickets_commerce_order_status_flag_{flag}')` per flag.

### Confirmation email (idempotent)
`Send_Email` dispatches one async `SendTicketEmail` job per event. On send, it reads attendees by `post_parent=order`, **skips** any attendee whose `_tec_tickets_commerce_attendee_ticket_sent` is set, then bumps that counter (`prev+1`) — the counter is the replay-safe idempotency gate. Write: `wp_postmeta._tec_tickets_commerce_attendee_ticket_sent`.

### My-tickets (read-only)
Two read paths over `tec_tc_attendee`: by order (`post_parent = order ID`) or by logged-in user (`_tribe_tickets_attendee_user_id`). **Both are `post_status='publish'` only** — so trashed/archived attendees drop out. The "Checked in" badge = `get_post_meta(_tec_tickets_commerce_checked_in)`. No writes.

### Check-in (meta flip)

| Step | Actor | Table(s) written | Rows / keys |
|------|-------|------------------|-------------|
| Check-in write | admin | `wp_postmeta` | `_tec_tickets_commerce_checked_in = 1`; `_tec_tickets_commerce_checked_in_details` (date/source/author/device); `_tribe_qr_status = 1` **only when `$qr` is truthy** (the free QR redirect path passes `qr=false`, so it does NOT set it) |
| Un-check-in | admin | `wp_postmeta` | `delete_post_meta` on `_checked_in`, `_checked_in_details`, `_tribe_qr_status` |

QR entry requires `current_user_can('edit_posts')` and verifies the scanned `_tec_tickets_commerce_security_code` before flipping. **Check-in is a pure meta flip** on the attendee post — no order/status change.

### Refund (status flip + stock restore + archive)
Gateway refund webhook or admin calls `Order::modify_status($order_id, 'refunded')`:

| Step | Actor | Table(s) written | Rows / keys |
|------|-------|------------------|-------------|
| Order → refunded | system | `wp_posts` | `post_status` → `tec-tc-refunded` (final status; refunded→refunded allowed for partials) |
| Status log | system | `wp_postmeta` | `_tec_tc_order_status_log_refunded` |
| **Restore stock** (`increase_stock`) | system | `wp_postmeta` (ticket post) | `total_sales = max(prev − qty, 0)`; `_stock = min(orig + qty, capacity)` — reverses the purchase decrement; shared level restored via `update_global_stock` |
| **Archive attendees** (`archive_attendees`) | system | `wp_posts` | each `tec_tc_attendee.post_status → 'trash'` via `wp_trash_post` (soft archive) |
| Trash bookkeeping | core | `wp_postmeta` | `_wp_trash_meta_status`, `_wp_trash_meta_time` (enables restore) |

**Notes:** the refund does **not** clear `_tec_tickets_commerce_checked_in` and does **not** delete the attendee — it only trashes the post, which removes it from the publish-only my-tickets/roster reads. `count_refunded` increments refund counters; `backfill_purchaser` preserves purchaser identity on the order for reporting. A `..._archive_attendee_delete_permanently` filter returning `false` hard-deletes instead of archiving.

---

## Key Structural Truths

- **Everything is `wp_posts` + `wp_postmeta`.** Event, venue, organizer, each ticket type, each order, and every individual attendee is its own post row; nearly every field is a meta row. The only relational tables are `tec_events`/`tec_occurrences` (a deferred date index) and the order-modifier tables.
- **"Sold / remaining" is a hybrid, and the lower value wins.** `available() = min(inventory, capacity, stock)`: `stock()`/`total_sales` are **stored counters** maintained by order flag-actions, while `inventory()`/pending are **counted live** from `tec_tc_attendee` posts.
- **The order↔ticket link is the serialized `_tec_tc_order_items` blob**; the order↔attendee link is `post_parent`. One attendee post exists per purchased ticket.
- **Stock decrement + attendee generation default to the PENDING transition**, not completed (`tickets-commerce-stock-handling` option). Refund reverses them (`total_sales`↓, `_stock`↑) and trashes attendees rather than deleting.

---

## Contrast with a Custom-Table Design (Fluent Events)

The same purchase in our schema is a **handful of typed rows** instead of dozens of post + meta rows. A buyer purchasing N tickets is: **one `INSERT` into `wp_fevents_registrations`** (a single row carrying buyer + payment + order status as real columns), **N `INSERT`s into `wp_fevents_guests`** (one typed row per ticket, the attendee identity), and **one `UPDATE wp_fevents_ticket_types SET sold = sold + N`** — a single authoritative counter, no `min()` reconciliation of a stored counter against a live post count. Where Tickets Commerce writes one `tec_tc_order` post + ~15 order-meta rows + one `tec_tc_attendee` post + ~8 attendee-meta rows *per ticket* + serialized item blobs, plus status-log and gateway-payload meta on every transition, the custom-table design expresses the entire order as a small set of columns across three narrow, indexed tables. Reads are direct `SELECT`s with real foreign keys and typed status columns rather than `meta_query` joins over an EAV `wp_postmeta` and slug-string `post_status` filtering — cheaper to query, cheaper to index, and impossible to leave in the split-brain state where a stored counter and a live count disagree.