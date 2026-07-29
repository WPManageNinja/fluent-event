# v1 implementation flow

> The build order. Each row is one chunk — small enough to be a single PR, and shippable
> without the rows below it existing.
>
> Every chunk links its own spec. Read that before starting it; do not work from this table
> alone. Mark a chunk done by filling its PR column.

**Ordering rule:** whatever depends on nothing comes first. Data before API, API before UI,
create before list — you cannot list what you cannot create.

---

## Stage 1 — Foundation

Nothing user-visible. Everything else waits on these.

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 1 | **Database migrations** | [migrations.md](migrations.md) · [patterns/database.md](../../patterns/database.md) | — | Seven `wp_fe_*` tables created on activation | [#2](https://github.com/WPManageNinja/fluent-event/pull/2) |
| 2 | **Models** | [patterns/backend.md](../../patterns/backend.md) | 1 | `Calendar`, `Event`, `Guest`, `Meta` with explicit `$fillable`, relationships, casts | |
| 3 | **Permission layer** | [permissions.md](permissions.md) | — | `PermissionManager` with `getAllPermissions()`, `getAllRoles()`, `hasPermission()`; admins bypass via `manage_options` | |
| 4 | **CPT + taxonomies** | [db/implementation.md](../../db/implementation.md) | 1 | `fevent_event` registered via `CPTHandler`, four taxonomies attached, both visible in wp-admin | |

**2 and 3 are independent of each other** — either order, or in parallel.

Chunk 4 needs open question 4 answered first: is the CPT `public`? It fixes a rewrite slug
that is painful to change once events exist.

---

## Stage 2 — Shell

The admin surface, before any feature lives in it.

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 5 | **Menu registration** | [scaffolding.md §5](scaffolding.md) | 3 | Sidebar shows Dashboard · Add New Event · Calendars · Events · Settings, gated by permission; no role means no menu | |
| 6a | **Design tokens** | [patterns/admin-layout.md](../../patterns/admin-layout.md) | — | `variables.scss` with the nine tokens in light and dark; every later style reads a variable, never a hex | |
| 6b | **Topbar + navigation styles** | [patterns/admin-layout.md](../../patterns/admin-layout.md) | 6a | `navigation.scss` ported; topbar renders brand + nav + Settings + theme toggle, and the three known defects are fixed | |
| 7 | **Bootstrap payload** | [scaffolding.md §4](scaffolding.md) | 3 | `window.fluentFrameworkAdmin.me.permissions` populated; first-paint settings included | |

Chunk 6 depends on nothing and can start any time — the most parallelisable work in v1.

Chunk 5 also fixes the three known topbar defects — the hardcoded `beta` span, the `href="/"`
pointing at site root, and the Settings/theme ordering. All three are listed in
[scaffolding.md §2](scaffolding.md).

---

## Stage 3 — Calendars

A calendar must exist before any event can, so this precedes events entirely.

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 8 | **Calendar API — write** | [patterns/backend.md](../../patterns/backend.md) · [rules/routes.md](../../../rules/routes.md) | 2, 3 | `POST`/`PUT` create and update; slug uniqueness surfaces as a field error, not a 500 | |
| 9a | **Create calendar form** | [calendar-create.md](screens/calendar-create.md) | 7, 8 | `#/calendars/new` renders title, slug, description and visibility, and creates on submit | |
| 9b | **Slug derivation + collision** | [calendar-create.md](screens/calendar-create.md) | 9a | Slug auto-fills from the title until edited; a duplicate returns a field error on the slug input, not a 500 | |
| 10 | **Calendar API — read** | [calendars.md](screens/calendars.md) | 2, 3 | List and single endpoints; event counts from one grouped aggregate, not a query per row | |
| 11 | **Calendars grid** | [calendars.md](screens/calendars.md) | 9a, 10 | Three-column card grid; empty state routes a new user to create their first calendar | |
| 12 | **Calendar delete** | [calendars.md](screens/calendars.md) | 10, 11 | Confirmed via `Confirm.vue`; a calendar with events handled per open question 5 — the FK will not stop it | |

**Create before list** — 9a before 11 — because the list has nothing to show until something
can be created, and building it first means testing against hand-inserted rows.

---

## Stage 4 — Events

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 13 | **Event API — write** | [event-detail.md](screens/event-detail.md) · [patterns/backend.md](../../patterns/backend.md) | 2, 4, 8 | Creating an event writes the `wp_posts` row and the `event_details` row in one transaction; `post_status` and `status` agree | |
| 14a | **Create event form** | [event-create.md](screens/event-create.md) | 9a, 13 | `#/events/new` renders calendar, title, description, start, end, timezone and location type, and creates on submit | |
| 14b | **Calendar context + entry points** | [event-create.md](screens/event-create.md) | 14a | `?calendar_id` preselects the calendar; Add Event appears on the calendar detail, the Events screen and the sidebar; no calendars routes to create one | |
| 14c | **Conditional location fields** | [event-create.md](screens/event-create.md) | 14a | Location type switches between meeting URL and address; the URL is protocol-filtered | |
| 15 | **Event API — read** | [calendar-detail.md](screens/calendar-detail.md) | 2, 4 | List endpoint supporting both pagination and a date-range window, calendar name joined in | |
| 16a | **Events control bar** | [calendar-detail.md](screens/calendar-detail.md) | 14a | Header with upcoming/past toggle, timeline·calendar view switch and Add Event. Emits filter changes; body is a placeholder | |
| 16b | **Timeline list** | [calendar-detail.md](screens/calendar-detail.md) | 15, 16a | Paginated rows under one calendar, driven by the bar's filters | |
| 16c | **View-choice persistence** | [calendar-detail.md](screens/calendar-detail.md) | 16a | The timeline·calendar choice survives reload per user via `localStorage`, seeded by the site default | |
| 16d | **Combined events screen** | [events.md](screens/events.md) | 16b | Same components unscoped; timeline rows gain a calendar-name prop; Add Event asks for a calendar | |
| 17a | **Month grid shell** | [calendar-detail.md](screens/calendar-detail.md) | 16a | `<el-calendar>` with a custom `#header` slot for month navigation. Empty cells, correct first day from `start_of_week` | |
| 17b | **Day-cell rendering** | [calendar-detail.md](screens/calendar-detail.md) | 15, 17a | `#date-cell` paints that day's events, with an `el-popover` per entry for detail | |
| 17c | **Range-bound fetching** | [calendar-detail.md](screens/calendar-detail.md) | 17b | Month navigation recomputes `start_date`/`end_date` and refetches, so the grid has no holes | |
| 18 | **Event detail — Overview** | [event-detail.md](screens/event-detail.md) | 15 | Breadcrumb, header, tab shell, Overview tab, more menu with edit and delete. Guests tab is chunk 21 | |
| 19 | **Event delete** | [event-detail.md](screens/event-detail.md) | 13, 18 | Removes the post, the details row and any guests atomically | |

The timeline and grid are **one component pair used twice** — scoped and unscoped differ
only by whether `calendar_id` is in the query. Two month-grid implementations will drift.

16a ships with a placeholder body on purpose: the control bar is reviewable on its own, and
it unblocks both 16b and 17a, which can then proceed in parallel.

---

## Stage 5 — RSVP

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 20 | **Guest API** | [event-detail.md](screens/event-detail.md) | 2, 13 | A response is recorded as a guest row; the same person answering twice updates rather than duplicates | |
| 21 | **Guests tab** | [event-detail.md](screens/event-detail.md) | 18, 20 | Per-event list with going / not-going counts | |

Blocked on open question 1 — `guests.status` has no going/not-going vocabulary — and
question 2, where a response arrives from.

---

## Stage 6 — Settings and dashboard

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 22 | **Settings storage + API** | [settings.md](screens/settings.md) | 2, 3 | Four settings read and written as one `customization` row in `wp_fe_meta` | |
| 23 | **Settings UI** | [settings.md](screens/settings.md) | 7, 22 | The four settings render and persist; dark-mode gate hides the theme toggle when off | |
| 24 | **Roles section** | [settings.md](screens/settings.md) · [permissions.md](permissions.md) | 3, 23 | Promote and revoke `fevent_manager` / `fevent_viewer`; role validated against `getAllRoles()` | |
| 25 | **Reports API** | [dashboard.md](screens/dashboard.md) | 2 | Three endpoints — tiles, series, recent — under a `reports` prefix | |
| 26a | **Growth chart component** | [dashboard.md](screens/dashboard.md) | 25 | A chart taking an endpoint as a prop, rendering a date-ranged series | |
| 26b | **Dashboard screen** | [dashboard.md](screens/dashboard.md) | 7, 25, 26a | Tiles and recent RSVPs around the chart, with a loading skeleton | |

Chunk 26a is the only genuinely unknown work in v1 — there is no chart component in our
scaffold, and affiliate's `AffiliateGrowth.vue` is ~205 lines. Worth spiking earlier if you
want to de-risk it.

---

## Stage 7 — Release

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 27a | **i18n pass** | [a11y-i18n.md](../../../rules/a11y-i18n.md) | all UI | Every string through `$t()` / `esc_html__()`; no hardcoded English in templates | |
| 27b | **Accessibility pass** | [a11y-i18n.md](../../../rules/a11y-i18n.md) | all UI | Icon buttons labelled, modal focus managed, contrast holds in both themes | |
| 27c | **Static analysis** | [rules/](../../../rules/) | all | `./wpf check` clean, plugin-check clean | |
| 28 | **Release build** | [deps-build.md](../../../rules/deps-build.md) | 27a–c | `npm run build` produces `assets/`, `config/app.php` back to `production` | |

---

## Chunk size

Sizes below are the sibling files each chunk ports from — the closest thing to an estimate
available before starting.

The two heaviest screens are split so no chunk inherits a whole sibling file:

| Sibling file | Lines | Split across |
|---|---|---|
| `AllSchedules.vue` | 536 | 16a, 16b, 16c, 16d |
| `CalendarView.vue` | 422 | 17a, 17b, 17c |
| `Dashboard.vue` + `AffiliateGrowth.vue` | 415 | 26a, 26b |
| `navigation.scss` + `variables.scss` | 258 | 6a, 6b |

Each split lands on a boundary you can actually see working: a control bar that filters
nothing yet, a month grid with empty cells, a chart with no dashboard around it. That is the
test for whether a split is real — if the halfway state cannot be demonstrated, it is one
chunk, not two.

Target is roughly 250 lines a chunk. Past that, look for a seam.

## What can run in parallel

Most of this is a chain, but three things are not:

- **Chunk 6 (layout)** depends on nothing. Start it whenever.
- **Chunks 2 and 3** are independent of each other.
- **Every API chunk** can precede its UI chunk by a long way — the API is testable with
  `wp eval` or curl before any screen exists.

## Blocked chunks

Three open questions gate specific work. From [v1_docs.md §7](v1_docs.md):

| Question | Blocks |
|---|---|
| `guests.status` RSVP vocabulary | 20, 21 |
| Where an RSVP arrives from | 20, 21 |
| Is the CPT `public`? | 4, and everything downstream |

Everything through chunk 19 can proceed without answering any of them.
