# Screen — Calendar detail

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/calendars/:id` · **Nav** none (reached from the grid) · **Phase** 2–3 ·
**Module** `resources/admin/modules/calendars/`

---

## Purpose

One calendar, and the events filed under it. Where events are created in the normal flow —
the calendar is ambient here, so **Add Event** needs no calendar picker.

## Reference

`fluent-booking/resources/admin/Modules/Schedules/AllSchedules.vue` for the control bar and
view switching; `Schedules/parts/CalendarView.vue` for the month grid.

## Layout

1. **Calendar header** — `title` and `description`, editable in place.
2. **Events bar** — "Events" left; right, in order:
   - upcoming / past toggle
   - view switch: timeline · calendar
   - **Add Event**
3. **The view** — timeline or calendar grid.

Both toggles are segmented controls, as booking does it:

```vue
<el-radio-group v-model="viewType">
    <el-radio-button label="timeline">…</el-radio-button>
    <el-radio-button label="calendar">…</el-radio-button>
</el-radio-group>
```

## The calendar grid needs no new dependency

`CalendarView.vue` builds the whole month grid from Element Plus's own `<el-calendar>`,
overriding the `#header` slot for month navigation and `#date-cell` to paint each day's
entries, with an `el-popover` per entry for detail. No FullCalendar, nothing added to
`package.json`. We are already on Element Plus, so this transfers directly — see
[deps-build.md](../../../../rules/deps-build.md).

## The two views need different queries

- **Timeline** — paginated over `event_details.start_at`.
- **Grid** — bounded by the visible month. Booking derives `start_date` / `end_date` with
  dayjs `startOf` / `endOf` on every navigation and refetches.

Reusing the paginated endpoint for the grid produces a month with arbitrary holes in it.
Design the endpoint for both from the start.

## Shared with the Events screen

This view and [events.md](events.md) differ by exactly one thing: whether `calendar_id` is
in the query. **Same timeline component, same grid component, same endpoint with an
optional filter.** Two month-grid implementations will drift, and the grid is the most
intricate UI in v1.

The one field that differs is the calendar name on timeline rows — a prop, not a second
component. Shown on the combined Events screen, omitted here as redundant.

## Behaviour

- Persist the view choice per user, as booking does
  (`localStorage.setItem('fcal_view_type', …)` on watch), so the grid survives a reload.
  A site-wide default seeds users with no stored choice; the stored choice wins after that.
- Read `get_option('start_of_week')` for the grid's first column. WordPress already knows.

## Open questions

- **Does upcoming / past apply in grid view?** Once the user navigates month by month a
  period filter is meaningless — a "past" filter on a future month renders empty. Hide the
  toggle in grid mode, or define its behaviour. Booking sidesteps this because its period
  filter belongs to the list only. Tracked as §7.6 in [v1_docs.md](../v1_docs.md).
