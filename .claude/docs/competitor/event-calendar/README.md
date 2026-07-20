# The Events Calendar + Event Tickets — DB design

Competitor teardown of the two StellarWP / Modern Tribe plugins that pair up to do
what Fluent Events does in one: **The Events Calendar** (events) + **Event Tickets**
(ticketing).

- [schema-combined.svg](schema-combined.svg) — **combined model** across both plugins
  (Event / Venue / Organizer / Category + Ticket / Attendee / Order) shown **over the
  WordPress core tables they actually live in** (`wp_posts`, `wp_postmeta`, `wp_users`,
  the taxonomy trio) with every connection drawn. Start here.
- [schema.svg](schema.svg) — TEC-only **physical** ER diagram (wp_posts, wp_postmeta,
  tec_events, tec_occurrences, taxonomy).
- [db-flow.md](db-flow.md) + [db-flow.svg](db-flow.svg) — **end-to-end DB write flow**:
  create event → add tickets → browse → buy → check-in/refund, every row traced +
  source-verified. Shows the post/meta row explosion vs our custom-table design.

## One-line summary

Both plugins are **CPT-primary**: almost every entity is a row in `wp_posts`, its fields
in `wp_postmeta` (EAV), relationships stored as **meta pointers** — not foreign keys.
Real SQL tables show up only as a *derived query layer* (TEC dates) or *auxiliary
subsystems* (fees, seating, webhooks).

## The combined model (schema-combined.svg)

Two bands. **Top (amber)** = the logical CPT/taxonomy entities. **Bottom (blue)** = the
real SQL tables they live in — WordPress core + the plugins' custom tables. Every link
is drawn so you can see exactly how a "ticket" or a "venue" is actually stored.

- **Amber box** = a CPT entity — one row in `wp_posts` (`post_type` shown); its `_meta`
  fields are rows in `wp_postmeta`.
- **Blue box** = a real SQL table.
- **Green line** = meta-pointer (entity → entity). **Grey dashed** = real foreign key.
  **Purple dotted** = "this entity is one row in `wp_posts`". **Red diamond** = pointer/FK.

```
Venue ──┐
Organizer ──┤ (meta _EventVenueID / _EventOrganizerID)
Category ───┴─► Event ◄── Ticket ◄── Attendee ──► Order
 (taxonomy)                (_..event) (_..ticket) (_..order)
   │  every amber entity above ─ purple ─► wp_posts (1 row each, post_type distinguishes)
   │  every _meta field ───────────────► wp_postmeta.post_id → wp_posts.ID
   │  post_author / attendee / purchaser ─► wp_users.ID
   └─ Category ─► wp_terms + wp_term_taxonomy, event↔term via wp_term_relationships.object_id → wp_posts
       Event ─► tec_events / tec_occurrences (real: mirrored dates), post_id → wp_posts
       Order ─► tec_order_modifiers (real: fees/coupons ↔ posts)
```

**The takeaway the diagram makes visual:** there is almost nothing these two plugins
store *outside* `wp_posts` + `wp_postmeta`. Six different "entities" are all just post
rows; their every attribute is an EAV meta row; the only real tables are WP's taxonomy
trio plus a few plugin-added query/aux tables (`tec_events`, `tec_occurrences`,
`tec_order_modifiers`).

### The Events Calendar side

| Entity | Storage | Notes |
|--------|---------|-------|
| **Event** | CPT `tribe_events` | dates/cost/relations in postmeta (`_EventStartDate`, `_EventVenueID`…) |
| **Venue** | CPT `tribe_venue` | own post; `_VenueAddress`, `_VenueCity`, `_VenueZip` |
| **Organizer** | CPT `tribe_organizer` | own post; `_OrganizerEmail`, `_OrganizerPhone` |
| **Category** | taxonomy `tribe_events_cat` | `wp_terms` + `wp_term_taxonomy`, linked via `wp_term_relationships` |
| `tec_events` | **real** custom table | 1 row/event, dates as real columns, `post_id` UNIQUE → event |
| `tec_occurrences` | **real** custom table | 1 row/occurrence (recurrence); powers calendar date queries |

Event → venue/organizer is a **postmeta pointer** (`_EventVenueID` holds the venue's post
ID), not an FK. The two custom tables exist because date-range queries over `wp_postmeta`
are slow — v6.0 mirrors dates into real columns. postmeta stays canonical; the tables are
a rebuildable index. (Recurring **series** ship in TEC **Pro**, not shown.)

### Event Tickets side — everything is a post, links in meta

Tickets Commerce (the modern module) stores **tickets, attendees, AND orders as CPTs**;
all relationships are postmeta pointers, no join tables:

| Entity | Storage | Links |
|--------|---------|-------|
| **Ticket** | CPT `tec_tc_ticket` | `_tec_tickets_commerce_event` → Event; `_price`, `_stock`, sale window in meta |
| **Attendee** | CPT `tec_tc_attendee` | three meta pointers: `_..event`, `_..ticket`, `_..order`; name/email/checked-in in meta |
| **Order** | CPT `tec_tc_order` | `post_status` = order state; `_tec_tc_order_items` = **serialized** array of ticket→qty (the only order→ticket link) |
| `tec_order_modifiers` (+ `_relationships`) | **real** custom tables | fees / coupons, linked to posts polymorphically |

Also real (not drawn): seating (`tec_slr_*`), series ticket-groups
(`tec_ticket_groups`), webhooks, gateway sync. There are 3 ticket providers — RSVP,
legacy PayPal, Tickets Commerce — each with its own CPTs + meta namespace.

## Contrast with Fluent Events (ours)

| | TEC + Event Tickets | Fluent Events |
|---|---|---|
| Event | CPT + postmeta (+ mirror table) | custom table `wp_fevents_events` |
| Ticket tier | CPT `tec_tc_ticket` + meta | custom table `ticket_types` |
| Attendee | CPT `tec_tc_attendee`, 3 meta pointers | custom table `guests`, real FKs |
| Order | CPT `tec_tc_order`, serialized `_items` | custom table `registrations`, real FKs |
| Venue / Organizer | separate CPTs, meta-pointer link | flat columns + `people` / `sponsors` |
| Category | taxonomy | `calendar_id` FK |
| Attribute storage | EAV postmeta | typed columns |
| Relationships | meta pointers (no FK) | real foreign keys |

**Trade-off.** They get WP-native goodies free (permalinks, block editor, search, REST,
revisions) but pay with EAV query pain — proven by TEC bolting custom date tables *back
on*, and by orders storing line-items as an unqueryable serialized blob. We chose
custom-tables-first (matching sibling plugins FluentCRM / FluentBooking / FluentCart):
normalized, indexed, real FKs, DECIMAL money, no meta bloat — at the cost of building our
own admin/render layer.

The instructive middle path: **event as a CPT** (title/desc/slug → free WP page) while
**heavy relational data sits in custom tables keyed by `post_id`** (what TEC does for
dates). Worth weighing if we ever want native WP event pages.

---
*Diagrams generated to match [`../../db/schema.svg`](../../db/schema.svg) visual style
(Workbench-style EER). Facts from plugin scans: `the-events-calendar/src/Events/
Custom_Tables/V1/Tables/`, `Tribe/{Main,Venue,Organizer}.php`; `event-tickets/src/
Tickets/Commerce/{Ticket,Attendee,Order}.php`, `Tribe/RSVP.php`.*
