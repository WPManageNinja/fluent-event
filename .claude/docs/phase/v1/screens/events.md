# Screen — Events (all calendars)

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/events` · **Nav** primary · **Phase** 3 · **Module**
`resources/admin/modules/events/`

---

## Purpose

Every event from every calendar, unscoped. The same screen as
[calendar-detail.md](calendar-detail.md) minus the calendar header.

## Layout

Identical control bar — "Events" left; upcoming / past, the timeline · calendar view
switch, and **Add Event** right. No calendar header above it, because no single calendar
owns this view.

## Build it once

This screen and the calendar detail view differ by **whether `calendar_id` is in the
query**. Same timeline component, same grid component, same endpoint with an optional
filter. Two implementations of a month grid will drift, and the grid is the most intricate
piece of UI in v1.

## Two consequences of being unscoped

### Timeline rows carry the calendar name

Inside a calendar that context is ambient; here it is the one thing a user cannot infer.
This is the only field the two screens don't share, so make it a **prop on the shared row
component** rather than grounds for a second component.

In grid view the day cells are too cramped for it — calendar identity there belongs in the
`el-popover` that already opens on an entry.

### Add Event needs a calendar picker

From inside a calendar the target is implied. From here there is none, and
`event_details.calendar_id` is NOT NULL. So:

- the create form needs a **required calendar field** when opened from this screen;
- the *Default calendar* setting (if built) preselects it;
- with **no calendar existing at all**, creation cannot proceed — route the user to
  [calendars.md](calendars.md) rather than showing a form that cannot submit.

## Data

`wp_posts` (`post_type=fevent_event`) joined 1:1 to `wp_fe_event_details` on `post_id`,
joined to `wp_fe_calendars` on `calendar_id` for the name.

Fetch the calendar name in the same query — a lookup per row is an N+1 that grows with the
list.

## Behaviour

- Upcoming / past filters on `event_details.start_at` against now.
- Draft and archived events must be treated consistently here, in the grid, and in the
  dashboard counts, or the three disagree and it reads as a bug.
