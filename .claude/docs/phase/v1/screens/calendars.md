# Screen — Calendars

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/calendars` · **Nav** primary · **Phase** 2 · **Module**
`resources/admin/modules/calendars/`

---

## Purpose

Lists every calendar. The entry point to everything else in the plugin — an event cannot
exist without a calendar (`event_details.calendar_id` is NOT NULL), so this screen gates
the whole product.

## Reference

`fluent-booking/resources/admin/Modules/Calendars/AllCalendars.vue` — for the concept
(cards, not an `el-table`) and the section header with a filter behind an `el-popover`.

**We diverge on layout.** Booking renders full-width stacked blocks
(`v-for` → `.fcal_each_cal`), which waste horizontal space and get long fast. We use a
three-column grid.

## Layout

- **Section header** — screen title left; create button and a filter popover right.
- **Grid** — `el-row` of `<el-col :xs="24" :sm="12" :md="8">`. Element Plus is a 24-column
  grid, so `8` is one third: one column on phones, two on tablets, three on desktop.
- **Card per calendar**, deliberately sparse:

| Element | Source |
|---|---|
| Title | `calendars.title` |
| Meta line | event count (`COUNT` of `event_details` on `calendar_id`), `visibility`, `status` |
| Settings icon | opens the calendar detail screen |

**One icon, not an action cluster.** Booking hangs a Settings button, a primary button and
an overflow dropdown off each block; at three-up that crowds the card. Edit, delete and
archive belong on the calendar's own screen.

## Data

`wp_fe_calendars` — `id`, `title`, `slug`, `description`, `visibility`, `status`,
`meta`, `created_by`, `created_at`.

Event counts must come from a grouped aggregate, not a per-card request. A count query per
card is an N+1 that scales with the grid.

## Behaviour

- **Public URL is only meaningful when `visibility == 'public'`.** If a card surfaces a
  link at all, show it for public calendars only — unlisted and private have no URL to
  advertise. Booking gets this right; keep it.
- **`slug` is UNIQUE** (`uq_calendars_slug`). Collisions surface as a field error on
  create, never a 500.

## Empty state

**This is the onboarding path, not a nicety.** A fresh install has no calendars, and
because `calendar_id` is NOT NULL the Events screen can do nothing until one exists. A bare
"no results" here dead-ends the entire plugin. Treat the empty grid as the first-run
experience.

## Open questions

- **Deleting a calendar that has events** — the FK refuses a plain delete. Block while
  events exist, cascade, or reassign? Surfaces behind the settings icon on the detail
  screen. Tracked as §7.5 in [v1_docs.md](../v1_docs.md).
