# The Events Calendar (v6.17) — DB design

Competitor teardown. How Modern Tribe / StellarWP's **The Events Calendar** stores
events. See [schema.svg](schema.svg) for the physical ER diagram.

## Model: CPT-primary + custom-table query layer (hybrid)

Almost everything is a **WordPress post**. TEC bolts on **two custom tables** only —
`tec_events` and `tec_occurrences` — as a *derived* fast-date-query layer. The CPT +
postmeta stay the source of truth.

## Physical tables (in the diagram)

| Table | Kind | Purpose |
|-------|------|---------|
| `wp_posts` | WP core | **Hub.** Holds every event, venue, organizer as a post row (distinguished by `post_type`). Title / slug (`post_name`) / description (`post_content`) / status live here → free permalink + WP page render. |
| `wp_postmeta` | WP core | Where the actual event fields live (EAV). One row per attribute. |
| `tec_events` | custom | 1 row / event post. Canonical dates as real columns for fast range queries. `post_id` UNIQUE → `wp_posts`. |
| `tec_occurrences` | custom | 1 row / occurrence (recurrence expansion). What powers calendar date-range views. |
| `wp_terms` / `wp_term_taxonomy` / `wp_term_relationships` | WP core | Taxonomy — event categories (`tribe_events_cat`) + tags. |
| `wp_users` | WP core | `post_author`. |

## The CPT entities hidden inside `wp_posts`

`post_type` is the discriminator — no separate tables:

- **`tribe_events`** — the event. Dates/cost/relations in postmeta:
  `_EventStartDate`, `_EventEndDate`, `_EventTimezone`, `_EventCost`, `_EventVenueID`,
  `_EventOrganizerID`.
- **`tribe_venue`** — venue, its own post. Address in postmeta: `_VenueAddress`,
  `_VenueCity`, `_VenueZip`, `_VenueCountry`.
- **`tribe_organizer`** — organizer, its own post. `_OrganizerEmail`, `_OrganizerPhone`,
  `_OrganizerWebsite`.

Event → venue / organizer is a **postmeta pointer** (`_EventVenueID` holds the venue's
post ID), not a foreign key. Category is a **taxonomy term**, not a column.

## Why the two custom tables exist

Date-range queries over `wp_postmeta` (`WHERE meta_key='_EventStartDate' AND
meta_value BETWEEN ...`) are slow — every filter is another self-join. So v6.0 mirrors
dates into `tec_events` (real `start_date`/`end_date` columns) and expands recurrence
into `tec_occurrences` (one queryable row per instance). postmeta remains canonical;
the tables are a rebuildable index.

> Recurring **series** (`tec_series_relationships`, a Series CPT) ship with Events
> Calendar **Pro**, not the free core — not in this diagram.

## Contrast with Fluent Events (ours)

| | The Events Calendar | Fluent Events |
|---|---|---|
| Event | CPT + postmeta (+ mirror table) | custom table `wp_fevents_events` |
| Venue / Organizer | separate CPTs, meta-pointer link | flat columns + `people` / `sponsors` tables |
| Category | taxonomy | `calendar_id` FK |
| Attribute storage | EAV postmeta | typed columns, real FKs |

**Trade-off.** TEC gets WP-native goodies free (permalinks, editor, search, REST) but
pays with EAV query pain — proven by the fact they had to *add custom tables back* for
dates. We chose custom-tables-first (matching sibling plugins FluentCRM / FluentBooking
/ FluentCart): normalized, indexed, real FKs, no meta bloat — at the cost of building
our own admin/render layer.

The instructive middle path TEC demonstrates: **event as a CPT** (title/desc/slug →
free WP page) while **heavy relational data sits in custom tables keyed by `post_id`**.
That is the hybrid worth weighing if we ever want native WP event pages.

---
*Diagram generated to match [`../../db/schema.svg`](../../db/schema.svg) visual style
(Workbench-style EER). Source of facts: plugin scan of `the-events-calendar/src/` —
`Custom_Tables/V1/Tables/Events.php`, `Occurrences.php`, `Tribe/Main.php`,
`Tribe/Venue.php`, `Tribe/Organizer.php`.*
