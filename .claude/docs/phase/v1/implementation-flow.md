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
| 2 | **Event CPT** | [db/implementation.md](../../db/implementation.md) | 1 | `fe_event` registered non-public via `CPTHandler` — no permalink, no REST, no admin list. **No taxonomies in v1** | [#4](https://github.com/WPManageNinja/fluent-event/pull/4) |

Chunk 2 registers the CPT as **not public**, so an event has no URL of its own. When a
front-end arrives it will be a page plus a query parameter we control — the way EventPrime
does it — rather than a WordPress permalink.

**The four classification taxonomies are deferred entirely.** v1 writes to none of them, and
registering an unused taxonomy only adds rewrite and query plumbing for nothing. Location is
`event_details.venue_type` plus a URL or address in `meta`.

**Models are not a chunk.** Each one lands with the feature that first needs it — `Calendar`
in 6, `Event` in 11, `Guest` in 18, `Meta` in 20 — because `$fillable` is a security
decision that cannot be justified before a controller writes to the model, and casts and
scopes are shaped by real queries. A later feature extends an existing model rather than
creating it.

---

## Stage 2 — Shell

The admin surface, before any feature lives in it.

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 3 | **Menu registration** | [scaffolding.md §5](scaffolding.md) | — | Sidebar shows Dashboard · Add New Event · Calendars · Events · Settings | [#5](https://github.com/WPManageNinja/fluent-event/pull/5) |
| 4a | **Design tokens** | [patterns/admin-layout.md](../../patterns/admin-layout.md) | — | `variables.scss` with the nine tokens in light and dark; every later style reads a variable, never a hex | |
| 4b | **Topbar + navigation styles** | [patterns/admin-layout.md](../../patterns/admin-layout.md) | 4a | `navigation.scss` ported; topbar renders brand + nav + Settings + theme toggle, and the three known defects are fixed | |
| 5 | **Bootstrap payload** | [scaffolding.md §4](scaffolding.md) | — | `window.fluentFrameworkAdmin.me` carries the current user, plus the settings the SPA needs at first paint | |

Chunk 4a depends on nothing and can start any time — the most parallelisable work in v1.

Chunk 4b also fixes the three known topbar defects — the hardcoded `beta` span, the
`href="/"` pointing at site root, and the Settings/theme ordering. All three are listed in
[scaffolding.md §2](scaffolding.md).

> **Permissions are not built until Stage 6.** Until chunk 22b lands, every gate is
> `current_user_can('manage_options')` — the menu registers for administrators only, every
> policy admits administrators only, and no permission flags reach the SPA. That is correct
> behaviour for an unfinished plugin, not a gap.
>
> Write each gate as a **single-line call** so 22b is a find-and-replace rather than a
> redesign. A check inlined into controller logic is the thing that makes that chunk
> expensive.

---

## Stage 3 — Calendars

A calendar must exist before any event can, so this precedes events entirely.

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 6 | **Calendar model + write API** | [patterns/backend.md](../../patterns/backend.md) · [rules/routes.md](../../../rules/routes.md) | 1 | `Calendar` model with `$fillable`; `POST`/`PUT` create and update; slug uniqueness surfaces as a field error, not a 500 | |
| 7a | **Create calendar form** | [calendar-create.md](screens/calendar-create.md) | 5, 6 | `#/calendars/new` renders title, slug, description and visibility, and creates on submit | |
| 7b | **Slug derivation + collision** | [calendar-create.md](screens/calendar-create.md) | 7a | Slug auto-fills from the title until edited; a duplicate returns a field error on the slug input, not a 500 | |
| 8 | **Calendar API — read** | [calendars.md](screens/calendars.md) | 6 | List and single endpoints; event counts from one grouped aggregate, not a query per row | |
| 9 | **Calendars grid** | [calendars.md](screens/calendars.md) | 7a, 8 | Three-column card grid; empty state routes a new user to create their first calendar | |
| 10 | **Calendar delete** | [calendars.md](screens/calendars.md) | 8, 9 | Confirmed via `Confirm.vue`; a calendar with events handled per open question 4 — the FK will not stop it | |

**Create before list** — 7a before 9 — because the list has nothing to show until something
can be created, and building it first means testing against hand-inserted rows.

---

## Stage 4 — Events

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 11 | **Event model + write API** | [event-detail.md](screens/event-detail.md) · [patterns/backend.md](../../patterns/backend.md) | 2, 6 | `Event` model plus `Calendar hasMany`. Creating an event writes the `wp_posts` row and the `event_details` row in one transaction; `post_status` and `status` agree | |
| 12a | **Create event form** | [event-create.md](screens/event-create.md) | 7a, 11 | `#/events/new` renders calendar, title, description, start, end, timezone and location type, and creates on submit | |
| 12b | **Calendar context + entry points** | [event-create.md](screens/event-create.md) | 12a | `?calendar_id` preselects the calendar; Add Event appears on the calendar detail, the Events screen and the sidebar; no calendars routes to create one | |
| 12c | **Conditional location fields** | [event-create.md](screens/event-create.md) | 12a | Location type switches between meeting URL and address; the URL is protocol-filtered | |
| 13 | **Event API — read** | [calendar-detail.md](screens/calendar-detail.md) | 11 | List endpoint supporting both pagination and a date-range window, calendar name joined in | |
| 14a | **Events control bar** | [calendar-detail.md](screens/calendar-detail.md) | 12a | Header with upcoming/past toggle, timeline·calendar view switch and Add Event. Emits filter changes; body is a placeholder | |
| 14b | **Timeline list** | [calendar-detail.md](screens/calendar-detail.md) | 13, 14a | Paginated rows under one calendar, driven by the bar's filters | |
| 14c | **View-choice persistence** | [calendar-detail.md](screens/calendar-detail.md) | 14a | The timeline·calendar choice survives reload per user via `localStorage`, seeded by the site default | |
| 14d | **Combined events screen** | [events.md](screens/events.md) | 14b | Same components unscoped; timeline rows gain a calendar-name prop; Add Event asks for a calendar | |
| 15a | **Month grid shell** | [calendar-detail.md](screens/calendar-detail.md) | 14a | `<el-calendar>` with a custom `#header` slot for month navigation. Empty cells, correct first day from `start_of_week` | |
| 15b | **Day-cell rendering** | [calendar-detail.md](screens/calendar-detail.md) | 13, 15a | `#date-cell` paints that day's events, with an `el-popover` per entry for detail | |
| 15c | **Range-bound fetching** | [calendar-detail.md](screens/calendar-detail.md) | 15b | Month navigation recomputes `start_date`/`end_date` and refetches, so the grid has no holes | |
| 16 | **Event detail — Overview** | [event-detail.md](screens/event-detail.md) | 13 | Breadcrumb, header, tab shell, Overview tab, more menu with edit and delete. Guests tab is chunk 19 | |
| 17 | **Event delete** | [event-detail.md](screens/event-detail.md) | 11, 16 | Removes the post, the details row and any guests atomically | |

The timeline and grid are **one component pair used twice** — scoped and unscoped differ
only by whether `calendar_id` is in the query. Two month-grid implementations will drift.

14a ships with a placeholder body on purpose: the control bar is reviewable on its own, and
it unblocks both 14b and 15a, which can then proceed in parallel.

---

## Stage 5 — RSVP

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 18 | **Guest model + API** | [event-detail.md](screens/event-detail.md) | 11 | `Guest` model plus `Event hasMany`. A response is recorded as a guest row; the same person answering twice updates rather than duplicates | |
| 19 | **Guests tab** | [event-detail.md](screens/event-detail.md) | 16, 18 | Per-event list with going / not-going counts | |

Blocked on open question 1 — `guests.status` has no going/not-going vocabulary — and
question 2, where a response arrives from.

---

## Stage 6 — Settings, permissions and dashboard

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 20 | **Meta model + settings API** | [settings.md](screens/settings.md) | 1 | `Meta` model. Four settings read and written as one `customization` row in `wp_fe_meta` | |
| 21 | **Settings UI** | [settings.md](screens/settings.md) | 5, 20 | The four settings render and persist; dark-mode gate hides the theme toggle when off | |
| 22a | **Permission layer** | [permissions.md](permissions.md) | 20 | `PermissionManager` with `allPermissionSets()`, `getUserPermissions()`, `userCan()`, and the record-scoped `canReadCalendar()` / `canWriteCalendar()`; admins bypass via `manage_options` | |
| 22b | **Apply permissions** | [permissions.md](permissions.md) | 22a | Every `manage_options` placeholder replaced: policies call `userCan()` or the record-scoped methods, each submenu registers under its own capability per the table below, and `me.permissions` reaches the SPA | |
| 22c | **Permissions section** | [settings.md](screens/settings.md) · [permissions.md](permissions.md) | 21, 22a | Checkbox list assigns any combination of the nine permission sets; every key validated against `allPermissionSets()` | |
| 23 | **Reports API** | [dashboard.md](screens/dashboard.md) | 6, 11, 18 | Three endpoints — tiles, series, recent — under a `reports` prefix | |
| 24a | **Growth chart component** | [dashboard.md](screens/dashboard.md) | 23 | A chart taking an endpoint as a prop, rendering a date-ranged series | |
| 24b | **Dashboard screen** | [dashboard.md](screens/dashboard.md) | 5, 23, 24a | Tiles and recent RSVPs around the chart, with a loading skeleton | |

**Permissions land here deliberately.** The vocabulary is shaped by what the screens turned
out to need, and fixing it before they exist means guessing — it has already changed several
times on paper. Until 22b runs, everything is administrator-only.

22b is the retrofit, and it is the one chunk whose cost depends on discipline earlier: if
every gate was a single-line call, it is mechanical.

### Submenu capabilities are wired in 22b

Chunk 3 shipped the seam — `addSubmenu($label, $route, $capability)` already takes a
per-entry capability, defaulting to `manage_options`. WordPress filters each submenu on its
own capability, so entries can differ; nothing about the menu code needs to change here,
only what gets passed.

**The rule:** Settings is gated on `manage_settings` alone. Every other entry is gated on
the data permissions that make its screen non-empty.

| Submenu | Visible when the user has any of |
|---|---|
| Dashboard | any of the eight non-`manage_settings` keys |
| Add New Event | `manage_own_calendars` · `manage_other_calendars` · `manage_all_events` |
| Calendars | `create_calendars` · `manage_own_calendars` · `read_other_calendars` · `manage_other_calendars` |
| Events | the four calendar keys · `read_all_events` · `manage_all_events` |
| Settings | `manage_settings` |

Worked examples, matching the personas in [permissions.md](permissions.md):

- `manage_own_calendars` → Dashboard, Add New Event, Calendars, Events. **No Settings.**
- `manage_own_calendars` + `manage_settings` → all five.
- `manage_settings` alone → Settings only. The top-level entry then points at Settings,
  because WordPress promotes the first surviving submenu to parent when the parent slug
  itself is not accessible.

**Guests have no submenu.** They are reached through an event, so `read_all_guests` /
`manage_all_guests` grant no menu entry of their own — they only widen Dashboard. A
front-desk user (`read_other_calendars` + `manage_all_guests`) navigates Events → an event
→ Guests.

**One mechanism decision belongs to this chunk.** `add_submenu_page()` takes a *capability
string*, and our permission keys live in `wp_fe_meta`, not in `WP_User::$caps`. Two bridges:

1. Map each key to a synthetic capability through a `user_has_cap` filter, then pass
   `'fluent_event_manage_settings'` and let core do the rest. `current_user_can()` then
   works identically in menus, policies and the SPA payload.
2. FluentAffiliate's shortcut — resolve the check first, then return the user's own role
   name when allowed and `''` when denied (`current_user_can('editor')` is true for an
   editor, since roles are stored as caps). Shorter, but it only works for the menu, and it
   reads `$roles[0]` so it misbehaves for multi-role users.

Prefer (1). Note that `add_submenu_page()` evaluates the capability at **registration**
time, on `admin_menu` — a denied entry never enters `$submenu`, it lands in
`$_wp_submenu_nopriv`. `PermissionManager` must therefore be able to answer by then.

Chunk 24a is the only genuinely unknown work in v1 — there is no chart component in our
scaffold, and affiliate's `AffiliateGrowth.vue` is ~205 lines. Worth spiking earlier if you
want to de-risk it.

---

## Stage 7 — Release

| # | Chunk | Spec | Dep | Done when | PR |
|---|---|---|---|---|---|
| 25a | **i18n pass** | [a11y-i18n.md](../../../rules/a11y-i18n.md) | all UI | Every string through `$t()` / `esc_html__()`; no hardcoded English in templates | |
| 25b | **Accessibility pass** | [a11y-i18n.md](../../../rules/a11y-i18n.md) | all UI | Icon buttons labelled, modal focus managed, contrast holds in both themes | |
| 25c | **Static analysis** | [rules/](../../../rules/) | all | `./wpf check` clean, plugin-check clean | |
| 26 | **Release build** | [deps-build.md](../../../rules/deps-build.md) | 25a–c | `npm run build` produces `assets/`, `config/app.php` back to `production` | |

---

## Chunk size

The two heaviest screens are split so no chunk inherits a whole sibling file:

| Sibling file | Lines | Split across |
|---|---|---|
| `AllSchedules.vue` | 536 | 14a–14d |
| `CalendarView.vue` | 422 | 15a–15c |
| `Dashboard.vue` + `AffiliateGrowth.vue` | 415 | 24a, 24b |
| `navigation.scss` + `variables.scss` | 258 | 4a, 4b |

Each split lands on a boundary you can actually see working: a control bar that filters
nothing yet, a month grid with empty cells, a chart with no dashboard around it. That is the
test for whether a split is real — if the halfway state cannot be demonstrated, it is one
chunk, not two.

Target is roughly 250 lines a chunk. Past that, look for a seam.

## What can run in parallel

Most of this is a chain, but several things are not:

- **Chunks 3, 4a and 5** depend on nothing. The whole shell can be built before any feature.
- **Chunk 2 (CPT)** is independent of everything in Stage 2.
- **Every API chunk** can precede its UI chunk by a long way — the API is testable with
  `wp eval` or curl before any screen exists.
- **14b and 15a** both unblock from 14a and can run side by side.

## Blocked chunks

Two open questions gate specific work. From [v1_docs.md §7](v1_docs.md):

| Question | Blocks |
|---|---|
| `guests.status` RSVP vocabulary | 18, 19 |
| Where an RSVP arrives from | 18, 19 |

Everything through chunk 17 can proceed without answering either.
