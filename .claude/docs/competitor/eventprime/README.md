# EventPrime — DB schema & data flow

> Competitor teardown of **EventPrime** (`eventprime-event-calendar-management`).
> Source-verified: post types, taxonomies, custom tables (dbDelta), and the
> event-create + booking/purchase write paths grepped + adversarially checked.

Diagrams: [schema.svg](schema.svg) — data model over the WP core tables (CPT
entities + the 3 ticket tables + wp_posts/postmeta/users/taxonomy, wired).
[db-flow.svg](db-flow.svg) — create → browse → book → pay → after write flow.

### 1. Storage model verdict

**Hybrid, strongly CPT-primary.** EventPrime stores every primary domain object — events, performers, and bookings/orders — as WordPress **Custom Post Types** with heavy `postmeta` (`em_*` keys), not custom tables. `register_post_type` is called exactly three times (`em_event`, `em_performer`, `em_booking`) and `register_taxonomy` three times (`em_event_type`, `em_venue`, `em_event_organizer`, all attached to `em_event`). The **only** `CREATE TABLE`/`dbDelta` statements in the core activator are three **ticketing** tables (price options, ticket categories, checkout fields). There is **no** custom table for events, bookings, attendees, or payments/transactions — those all live in `wp_posts` + `wp_postmeta`. Net: canonical records = CPT + postmeta; type/venue/organizer = taxonomy terms; only ticket *definitions* are custom-table.

Evidence: `register_post_type` at `admin/class-eventprime-event-calendar-management-admin.php:882/929/985`; `register_taxonomy` at `:703/759/815`; the three `CREATE TABLE`s at `includes/class-eventprime-event-calendar-management-activator.php:582/622/638`.

### 2. Custom tables (only confirmed)

| Table | Identifier / prefix | Purpose | Key columns (confirmed) |
|---|---|---|---|
| `wp_em_price_options` | `TICKET` → `wp_em_` (alternate prefix) | Ticket TYPES / price variations, 1-to-many per event. Core ticketing table. | `id` (PK), `event_id` (FK→`em_event` post), `category_id` (FK→ticket_categories), `name`, `description`, `start_date`, `end_date`, `price`, `special_price`, `capacity` (static total), `is_default`, `is_event_price`, `priority`, `status`, `seat_data` (longtext), `parent_price_option_id`, `additional_fees` (JSON), `visibility` (JSON), `offers` (JSON), `booking_starts`/`booking_ends` (JSON), `min_ticket_no`, `max_ticket_no`, `ticket_template_id`, `created_at`, `updated_at` |
| `wp_eventprime_ticket_categories` | `TICKET_CATEGORIES` → `wp_eventprime_` | Ticket category grouping per event; hierarchical via `parent_id`. | `id` (PK), `event_id` (FK→`em_event` post), `parent_id`, `name` (varchar 100), `capacity`, `priority`, `status`, `created_by`, `last_updated_by`, `created_at`, `updated_at` |
| `wp_eventprime_checkout_fields` | `CHECKOUT_FIELDS` → `wp_eventprime_` | **Global** custom checkout/attendee field *definitions* (not per-event). | `id` (PK), `type`, `label`, `option_data` (longtext), `priority`, `status`, `created_by`, `last_updated_by`, `created_at`, `updated_at` |

**Prefix quirk (confirmed):** `get_db_table_name()` maps `TICKET` to the `wp_em_` prefix but the other two to `wp_eventprime_` — both prefixes coexist (`activator.php:76–104`).

**Not a core table:** `wp_em_events` is *referenced* (not created — no `dbDelta`) in `includes/class-eventprime-rest-api.php:2834`, written only if it already exists (guarded by `class_exists('EP_DBhandler')` + `SHOW TABLES LIKE`). It is an optional denormalized date/time mirror keyed by `post_id`, presumably from an add-on. Events stay CPT-canonical.

### 3. Post types / taxonomies

| Post type | Visibility | Role |
|---|---|---|
| `em_event` | public (`capability_type=em_event`) | The event (canonical). Attributes in postmeta. |
| `em_performer` | public (`capability_type=em_performer`) | Performers/artists. Contact/social in postmeta. |
| `em_booking` | private (`public=false`, `create_posts=false`, `map_meta_cap=false`) | Booking/order. Order+attendee+payment all in postmeta. |

| Taxonomy | Attached to | Notes |
|---|---|---|
| `em_event_type` | `em_event` | hierarchical, `public=false`/`show_ui=true` |
| `em_venue` | `em_event` | Venue is a **taxonomy**, not a CPT/table |
| `em_event_organizer` | `em_event` | Organizer is a **taxonomy**, not a CPT/table |

### 4. Event-create flow (admin saves an event)

Two-phase write: WP core first, then EventPrime on `save_post`.

| # | Actor | Writes |
|---|---|---|
| 1 | WP core `wp_insert_post`/`wp_update_post` | `wp_posts`: `em_event` row (title→`post_title`, editor→`post_content`, status→`post_status` — adjusted by `ep_respect_requested_post_status` on `wp_insert_post_data`, slug→`post_name`) |
| 2 | WP core `tax_input` | `wp_term_relationships` (event ↔ `em_event_type`/`em_venue`/`em_event_organizer` terms) + `wp_term_taxonomy.count` bump |
| 3 | WP core | `wp_postmeta`: `_thumbnail_id` = featured-image attachment ID |
| 4 | `Admin::ep_save_event_meta_boxes()` on `save_post` (`admin:1588`) | none — guards only. Guards: `post_type==='em_event'`, `!DOING_AUTOSAVE`, a `$this->processing` re-entrancy flag, `current_user_can('edit_post')`, and nonce. **Correction:** the POST nonce *field* is `ep_event_meta_nonce`, verified against *action* `ep_save_event_data`; there is **no** `wp_is_post_revision()` check (revision is only a comment). |
| 5 | `EP_DBhandler::eventprime_save_event_metadata()` (`dbhandler:1432`) | `wp_postmeta` via `update_post_meta`: dates/times as `em_start_date`/`em_end_date`/`em_start_date_time` timestamps, mirrored term IDs `em_event_type`/`em_venue`/`em_organizer`, `em_performer` (array of `em_performer` post IDs), `em_gallery_image_ids`, `em_enable_booking`, `em_fixed_event_price`, `em_social_links`, etc. (Note: runs `maybe_unserialize()` on `em_event_type` at `:1440` — object-injection surface per `fix-patterns.md`, though data originates from admin `tax_input`.) |
| 6 | `eventprime_update_event_tickets_and_category()` (`dbhandler:577`) | `wp_eventprime_ticket_categories` INSERT/UPDATE (`event_id=post_id`); `wp_em_price_options` INSERT/UPDATE/DELETE (`event_id=post_id`, `category_id` FK); `wp_postmeta`: `ep_ticket_order_arr` |
| 7 | `eventprime_update_event_custom_fields()` (`dbhandler:924`) | `wp_postmeta`: `em_event_checkout_attendee_fields`/`_fixed_fields`/`_booking_fields` |
| 8 | `eventprime_update_event_recurring_events()` (conditional, `dbhandler:1006`) | `wp_postmeta` recurrence config; conditional child `em_event` posts in `wp_posts` |
| 9 | Hooks | **Correction:** `do_action('ep_after_save_event_metadata')` fires *earlier* at `:1403` (before tickets/custom-fields); only `do_action('ep_after_save_event_data')` fires last at `:1426`. |

**Linkage:** type/venue/organizer are linked *both* as term relationships *and* mirrored postmeta term IDs; performer only via the `em_performer` postmeta ID array; tickets only via the `event_id` FK column in their custom tables.

### 5. Booking / purchase flow (visitor books/buys)

Bookings, attendees, and payments are **all** `em_booking` CPT + postmeta — **no** bookings/attendees/transactions table. Inventory is **count-based** (no stock column, no decrement).

| # | Actor | Writes |
|---|---|---|
| 1 | Visitor (browser) | none — reads ticket types/prices/remaining from `wp_em_price_options` |
| 2 | AJAX `save_event_booking` (`class-ep-ajax.php:310`; nonce `ep_save_event_booking`, registered **priv + nopriv** so guests can book) | none — validate cart, capacity check by **counting** booked qty across existing `em_booking` posts (`eventprime_check_remaining_tickets_in_event`, `functions.php:8308`, remaining = capacity − booked at `:8342`); seated: reject seat already `type=sold` in `em_seat_data` |
| 3 | Guest branch (~`:480`) | conditional INSERT `wp_users`/`wp_usermeta` (find-or-create buyer for guest checkout) |
| 4 | `save_event_booking` (`:485–491`) | `wp_posts`: ONE `em_booking` post, `post_status=failed` (initial), `post_author=user_id` |
| 5 | `save_event_booking` (`:493–527`) | `wp_postmeta`: `em_event`, `em_user`, `em_status=failed`, `em_payment_method`, `em_order_info` (serialized tickets[] qty/price/subtotal + `booking_total` + `event_fixed_price` = the ORDER), `em_attendee_names` (serialized nested per-attendee rows = the ATTENDEES), `em_booking_fields_data`, `em_payment_log=[]` (placeholder), `em_booked_seats=[]`, `ep_order_key`, `em_random_order_id`, `ep_gdpr_consent` |
| 6 | Free path (`:532`) | if `payment_method=none` && `booking_total==0`: immediate `confirm_booking` → `post_status`/`em_status`→`completed` |
| 7 | PayPal JS + capture AJAX (`~:590–663`) | none — ownership check (nonce / `ep_order_key` / `hash_equals` on `em_random_order_id`) then **server-side** `verify_paypal_order()` (`wp_remote` to PayPal `/v2/checkout/orders/{id}`) before any write |
| 8 | `EventPrime_Bookings::confirm_booking` (`class-ep-bookings.php:105`) | `wp_update_post` `post_status`→`completed`/`pending`; `wp_postmeta`: `em_status`, `em_order_info` (payer email/name, `:143`), `em_payment_log` (gateway/txn record, `:148` = the TRANSACTION) |
| 9 | Inventory | **No decrement.** Remaining always recomputed at read. Seated venues: seats flipped to `type=sold` inside `em_event` postmeta `em_seat_data` by the separate **Live Seating** paid extension — the only inventory-state write, still postmeta not a table |
| 10 | Offline (`update_offline_payment_status`, `:420`) | `wp_postmeta` `em_payment_log[offline_status]`; `em_status`/`post_status` on cancel |

*(unverified)* Secondary `em_booking` insert entrypoints cited in trace notes — `class-ep-ajax.php:2339` and `class-eventprime-rest-api.php:3043` (admin/REST/integration) — were not directly verified; the primary frontend AJAX path is fully confirmed. Also note `load_booking_detail` reads meta via a bare `maybe_unserialize` (`class-ep-bookings.php:50`) — object-injection surface per `fix-patterns.md`.

### 6. How it compares (3-way)

On the CPT-vs-custom-table spectrum, **EventPrime sits in the middle but leans hard toward the CPT pole** — closer to The Events Calendar than to a custom-schema design. **The Events Calendar + Event Tickets** is fully CPT+meta/EAV: events, venues, organizers, tickets, attendees, and orders are all post types with attributes scattered across `wp_postmeta` (an EAV pattern that scales poorly for filtered date queries). **EventPrime** keeps the same CPT+postmeta backbone for its *records* — events, performers, and bookings are all posts, attendees and payments are serialized postmeta on the booking post, venue/organizer are taxonomy terms — but carves out **three custom tables purely for ticket *definitions*** (price options, categories, checkout fields), a partial normalization TEC reserves for its premium tiers. **Fluent Events** (our design) is the opposite pole: purpose-built `wp_fevents_*` custom tables (events, ticket_types, registrations, guests, people, sponsors, …) as the canonical store, with WP posts/meta used only where WP-native (media library). So: TEC = CPT/EAV-maximal; EventPrime = CPT records + custom *ticketing* tables (records still CPT-canonical, no bookings/attendees/inventory tables); Fluent Events = custom-table-canonical.