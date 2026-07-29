# Fluent Event — v1 Build Plan

> Status: draft, 2026-07-28. Scope + phasing for the first shippable release.
> Grounded in [functional-requirements.md](../../functional-requirements/functional-requirements.md),
> [db/implementation.md](../../db/implementation.md) and
> [db/fevent_db_schema.sql](../../db/fevent_db_schema.sql).
>
> **Every phase below follows [docs/patterns/](../../patterns/README.md)** — where code
> lives, how hooks are named, when a component earns extraction, how migrations are
> written. Read it before writing code, not after review.
>
> Build order and progress: [implementation-flow.md](implementation-flow.md) — every chunk
> is one PR, with its spec linked and a column to record the PR when it lands.
>
> The shell is built in [scaffolding.md](scaffolding.md) before any feature work.
>
> Who may do what is in [permissions.md](permissions.md) — three scopes, zero new tables.
> It was designed in July and had never been written down until now, so treat it as the
> record rather than a proposal.

---

## 1. What v1 is

**Calendars, and simple RSVP events filed under them, managed from a Vue SPA in wp-admin.**

That is the whole of it. One event type — RSVP. One question asked of an attendee — going
or not going. Nothing is priced, nothing repeats, nothing is emailed.

The bar for "done": a host opens **Fluent Event** in wp-admin, creates a calendar, creates
an event under it, and RSVPs are recorded against that event as going / not going.

---

## 2. Scope

### In

| Area | v1 delivers |
|---|---|
| Admin SPA | Vue 3 SPA in wp-admin under a **Fluent Event** menu with logo + sub-pages |
| Calendars | Create, edit, list, delete. Title, slug, description, visibility, status |
| Events | Create, edit, list, delete — **always under a calendar**. Title, description, start/end, timezone, cover image, and a location that is either an online URL or an address in `settings` — no venue taxonomy |
| RSVP | One mode only. A guest row per response, going / not going |

### Out

Everything else. Explicitly named as out by the scope decision:

| Deferred | Schema impact in v1 |
|---|---|
| **Ticket types / tiers** | `wp_fe_ticket_types` created and indexed, no rows written; `guests.ticket_type_id` stays NULL |
| **Payment** | `guests.payment_status`/`payment_ref`/`price`/`paid_at` stay NULL; `ticket_types.price` unused |
| **Recurring events** | `event_details.parent_event_id` stays NULL |
| **Notifications — in-app and email** | `wp_fe_notifications` + `wp_fe_notification_users` created and indexed, no rows written. **No `wp_mail()` call ships in v1** |
| **FluentCommunity integration** | Nothing in v1. Comes next; see §8 |
| **Venue / organizer / performer taxonomies** | `fevent_venue`, `fevent_organizer`, `fevent_performer` registered but never written. Location is `venue_type` + `settings` instead |
| Check-in, waitlist, approval, custom form fields, sponsors | untouched columns |
| Event insights, invites, guest export | no screens |

### Why the unused tables still get created

**All 7 migrations are written in Phase 1 and all 7 tables are created**, exactly as the
locked schema defines them — the database is complete in v1 even where the feature is not. The schema is
settled, migrations are idempotent, and this costs nothing now versus a migration and a
version bump later.

> An earlier draft justified this by the foreign key from `guests` to `ticket_types`. That
> reasoning is void — **no foreign keys will exist**: `Blueprint` cannot express them,
> `dbDelta` does not support them, and no sibling plugin uses any. See
> [migrations.md](migrations.md).

---

## 3. Locked decisions

Carried in from design; not reopened during v1.

- **Event = CPT hybrid.** Identity in `wp_posts` (`post_type=fevent_event`), open-ended
  config in `wp_postmeta`, typed data in `wp_fe_event_details` (1:1, `post_id` NOT NULL +
  UNIQUE). Buys permalinks, block editor, admin list, search, and REST for free.
- **Calendar = custom table.** `wp_fe_calendars`, not a CPT.
- **Every event belongs to a calendar.** `event_details.calendar_id` is NOT NULL with an
  FK — there is no orphan event, so calendars must exist before events can.
- **Classification = 4 WP taxonomies** on the event CPT — `fevent_event_type`,
  `fevent_venue`, `fevent_organizer`, `fevent_performer`. No `people` table.
- **No registrations table.** An RSVP is a `guests` row. In v1 every RSVP is its own order:
  `group_id` unique per row, `is_buyer = 1`, `ticket_type_id` NULL.
- **No attendee accounts.** Guests are `name` + `email`; `user_id` is a nullable
  convenience when a logged-in WP user responds.
- **Images are URLs** in the owning record's `settings` JSON. Never a column, never an
  attachment FK.

### Known divergence

Every sibling plugin — fluent-booking, fluent-community, fluent-affiliate — is
**zero-CPT**; their equivalents of our events and calendars are all custom tables. Our CPT
hybrid for events is deliberate, but it means **no sibling supplies a copyable pattern for
event identity**. Expect to write the CPT + taxonomy + `event_details` join by hand, and to
carry the `post_status` ⟷ `event_details.status` sync ourselves.

---

## 4. Admin SPA

Event management happens in a **Vue 3 single-page app rendered inside wp-admin**. In v1
this is the *only* surface — there is no front-end page yet.

> **Design reference: fluent-affiliate.** Take layout, spacing, card treatment, and screen
> composition from `fluent-affiliate/resources/admin/`, not fluent-booking — with one
> deliberate exception: the **Calendars list follows fluent-booking's `AllCalendars.vue`**,
> since affiliate has no equivalent screen.
>
> Keep design and mechanism separate, though — fluent-affiliate has **no `vue.php`**. It
> declares every screen as a PHP `add_submenu_page`, which is the older approach. Our
> scaffold's server-declared nav (§ *The plugin topbar*) is newer than either sibling, so
> **copy affiliate's look, not its routing**.
>
> One concrete trap: affiliate uses `resources/admin/Modules/` with a capital M. Our vite
> glob is `resources/admin/modules/**/*.vue`, lowercase. Matching its casing breaks
> component resolution silently.

### Menu shape

A top-level **Fluent Event** menu with the plugin logo, plus sub-pages beneath it.

The house pattern — from `fluent-booking/app/Hooks/Handlers/AdminMenuHandler.php` — is
**one WordPress page, many menu entries**:

- One `add_menu_page('fluent-event', …, [$this, 'render'], $this->getMenuIcon(), $position)`.
- One `add_submenu_page` per sub-page, **every one pointing at the same `render()`
  callback**, with the hash route encoded in the slug:

  ```php
  add_submenu_page('fluent-event', __('Events', 'fluent-event'), __('Events', 'fluent-event'),
      $capability, 'fluent-event#/events', [$this, 'render']);
  ```

- The first submenu re-registers the parent slug bare (`'fluent-event'`), so the menu's own
  entry reads "Dashboard" instead of repeating the plugin name.

WordPress paints one page; `vue-router` reads the hash and swaps the view. Sub-pages are
therefore **router entries, not PHP pages** — adding one is a route + a component, never a
new render callback.

### What the scaffold already provides

This infrastructure exists and should not be rebuilt:

| Piece | Where | State |
|---|---|---|
| Menu registration + logo | [AdminMenuHandler.php](../../../../app/Hooks/Handlers/AdminMenuHandler.php) | `add_menu_page` + `getMenuIcon()` wired; base URL `admin.php?page=fluent-event#/` |
| SPA mount point | `app/Views/admin/menu.php` via `$this->app->view->render('admin.menu')` | wired |
| Asset enqueue + `localizeScript` | `AdminMenuHandler::enqueueAssets()` | wired, with `_admin_booting` / `_admin_booted` extension hooks |
| Router | [resources/admin/router/](../../../../resources/admin/router/) | `createWebHashHistory()`, static `routes.js`, middleware pipeline |
| Component auto-loading | `resources/admin/router/index.js` | vite globs `resources/admin/modules/**/*.vue` |

A v1 sub-page therefore costs: one `add_submenu_page` line, one entry in `routes.js`, one
directory under `resources/admin/modules/`.

### Rules that apply

- **Options API only** — no `<script setup>`, no Composition API. Scanner-enforced.
- REST calls go through the app's `$get`/`$post`/`$put`/`$delete` mixin, never ad-hoc
  `axios`/`fetch` — the wrapper adds `X-WP-Nonce` and nonce renewal.
- Every string through the `$t()` mixin. No hardcoded English in templates.
- Capability-gate the menu; the same capability gates the matching REST policy. A hidden
  menu item is not access control.
- No hardcoded colours — theme CSS variables, contrast holding in light and dark.

Full detail in [vue.md](../../../rules/vue.md) and [a11y-i18n.md](../../../rules/a11y-i18n.md).

### The plugin topbar

Separate from the WordPress sidebar. Inside the SPA there is a navbar of our own, styled
after fluent-affiliate's:

| Region | Contents |
|---|---|
| **Left** | Logo + brand name |
| **Middle** | Dashboard · Calendars · Events |
| **Right** | Settings · theme toggle |

**This is already built.** [Application.vue](../../../../resources/admin/components/Application.vue)
renders `navbar-left` (brand + `menu.get('primary')`) and `navbar-right`
(`<ThemeSwitcher />` + `menu.get('secondary')`). Left and middle are one flex row — the
brand block then the primary nav — which produces the layout above.

Nav items are **declared server-side**, not in the Vue app.
[app/Http/Routes/vue.php](../../../../app/Http/Routes/vue.php) is the single source:

```php
$router->menu('primary', function ($router) {
    $router->add('/', 'modules/dashboard')
        ->name('dashboard')
        ->icon('HomeFilled')
        ->title(__('Dashboard', 'fluent-event'))
        ->middleware('auth');
});
```

`createAdminRouter()` pulls these over, groups them, and hands them to the `MenuApi` —
group name `primary` renders middle, `secondary` renders right. So **one nav item = one
`->add()` call plus one directory under `resources/admin/modules/`.** The vite glob
resolves `modules/events` to the component; no `routes.js` edit, no new PHP page.

### v1 nav items

| Group | Item | Module | Backing store |
|---|---|---|---|
| `primary` | Dashboard | `modules/dashboard` | — (scaffolded) |
| `primary` | Calendars | `modules/calendars` | `wp_fe_calendars` |
| `primary` | Events | `modules/events` | CPT + `wp_fe_event_details` |
| `secondary` | Settings | `modules/settings` | `wp_fe_meta` + `wp_options` |
| — | Theme toggle | `ThemeSwitcher.vue` | scaffolded; palettes in `config/theme.php` |

Three primary items, one secondary. Calendars sits beside Events rather than nested under
it — they are peers in the data model, and every event needs a calendar to already exist.

**No Organizers or Venues screens in v1.** The `fevent_organizer` and `fevent_venue`
taxonomies are still registered in Phase 1 (they are locked design, and the CPT declares
them), so WordPress supplies its own term-management screens for free under the CPT menu.
What v1 skips is building *custom SPA screens* for them. Nothing is lost by waiting.

The theme toggle needs nothing built: `ThemeSwitcher.vue` exists and
[config/theme.php](../../../../config/theme.php) already defines named light/dark palettes.

### Screens

Each screen has its own file — layout, data sources, sibling reference, and its own open
questions.

| Screen | Route | Nav | Phase | Spec |
|---|---|---|---|---|
| Dashboard | `#/` | primary | 5 | [screens/dashboard.md](screens/dashboard.md) |
| Calendars | `#/calendars` | primary | 2 | [screens/calendars.md](screens/calendars.md) |
| Create calendar | `#/calendars/new` | — | 2 | [screens/calendar-create.md](screens/calendar-create.md) |
| Calendar detail | `#/calendars/:id` | — | 2–3 | [screens/calendar-detail.md](screens/calendar-detail.md) |
| Events (all calendars) | `#/events` | primary | 3 | [screens/events.md](screens/events.md) |
| Create event | `#/events/new` | — | 3 | [screens/event-create.md](screens/event-create.md) |
| Event detail | `#/events/:id` | — | 3–4 | [screens/event-detail.md](screens/event-detail.md) |
| Settings | `#/settings` | secondary | 2 | [screens/settings.md](screens/settings.md) |

Shared conventions and the design-reference rules live in
[screens/README.md](screens/README.md).

### Topbar gaps to close

Three one-liners in `Application.vue`:

1. **Brand shows a hardcoded `beta` span, not the brand name.** Wants replacing with
   "Fluent Event".
2. **Brand links to `/`** — site root, wrong from inside wp-admin. Should point at the
   plugin dashboard route.
3. **Right-hand order is `[ThemeSwitcher][Settings]`**, spec says Settings then theme
   icon. One-line swap.

## 5. Phases

Scaffolding first, then data. Calendars precede events because `calendar_id` is NOT NULL.

### Phase 0 — Scaffolding

The shell: menu, permissions layer, bootstrap payload, directory skeleton, and the handful
of defects already sitting in the scaffold. Full checklist:
[scaffolding.md](scaffolding.md).

**Done when:** an administrator sees the menu with all four entries, a `fevent_viewer` sees
it without Settings, a user with no role cannot reach it at all, and the SPA boots with
`me.permissions` populated.

### Phase 1 — Data layer

All 7 Blueprint migrations, models, CPT + taxonomy registration.

- 7 files in `database/Migrations/` using the **Blueprint API** from
  [DBMigrator.php](../../../../database/DBMigrator.php) — `$table` property + `create()` +
  `alter()`. Do **not** copy fluent-booking's raw `CREATE TABLE` strings; our migrator is
  the newer devtools generation with idempotent guards.
- Models in `app/Models/` with explicit `$fillable` on every one. v1 needs `Calendar`,
  `Event`, `Guest` fleshed out; the rest can be thin.
- `app/CPT/EventPost.php` registered through the existing
  [CPTHandler](../../../../app/Hooks/Handlers/CPTHandler.php) `$customPostTypes` array.
- Taxonomy registration handler.

**Done when:** `./wpf migrate:refresh` builds all 7 tables clean, `./wpf db:show` lists
them, the CPT and 4 taxonomies appear in wp-admin, `./wpf check` passes.

### Phase 2 — Calendars

`CalendarController` + `CalendarPolicy` + `CalendarService`, routes, admin SPA screens.

- Controller validates and delegates; business logic in the service.
- A policy method per destructive action — never let `delete*`/`update*` fall through to
  `verifyRequest()`.
- Slug is unique (`uq_calendars_slug`) — collisions need handling at write, not a 500.

**Done when:** calendars can be created, edited, listed, and deleted from the SPA.

### Phase 3 — Events under a calendar

`EventController` + `EventPolicy` + `EventService`, routes, admin SPA screens.

- The write path is one transaction: `wp_insert_post` + the `event_details` row together.
  Half-written events are the main risk of the hybrid model.
- Keep `post_status` and `event_details.status` in step on every write.
- Event creation requires a calendar — the UI should not offer "new event" with no
  calendar available.

**Done when:** an event can be created under a calendar, edited, listed, and deleted, and
the `wp_posts` row and `event_details` row never disagree.

### Phase 4 — RSVP

Going / not-going recorded against an event.

- One `guests` row per response. `ticket_type_id` NULL, `is_buyer = 1`, `group_id` unique
  per row.
- Identity is the email — the same address responding twice updates its row rather than
  inserting a second. Needs a uniqueness rule per `(event_id, email)`; see §7.
- Attendee list per event in the SPA, with going / not-going counts.

**Done when:** a response is recorded, changing the answer updates rather than duplicates,
and the counts are right.

### Phase 5 — Dashboard

Stat tiles, growth overview, recent RSVPs. Last, because every number it shows depends on
Phases 2–4 existing.

- `ReportController` + `ReportPolicy` + a reports service, three endpoints under a
  `reports` prefix.
- Read `fluent-affiliate/app/Services/Reports/ReportService.php` before writing the series
  generator — `getMultiChartStatistics()` already does model-agnostic date bucketing.
- Settle the "Total guests" definition (see §7) before building the tile.

**Done when:** the three tiles are correct against seeded data, the chart redraws on a
range change without refetching tiles or list, and the skeleton shows while loading.

### Phase 6 — Hardening

`./wpf check` clean, plugin-check clean, i18n pass, release build.

- Every user-facing string through `__()`/`esc_html__()` with text domain `fluent-event`;
  Vue strings through the `$t()` mixin.
- Asset version is `FLUENT_EVENT_PLUGIN_VERSION` — never `time()`.

---

## 6. Conventions for every phase

| Thing | Value |
|---|---|
| Namespace | `FluentEvent\` |
| Text domain | `fluent-event` |
| REST | `fluent-event/v2` (from `config/app.php`) |
| Hook prefix | `do_action('fluent_event/<noun>/<verb>', …)` |
| Tables | `wp_fe_*` |
| CPT / taxonomies | `fevent_event`, `fevent_*` |
| Frontend | Vue 3 **Options API only** |

Three layers, and they answer different questions:

| Layer | Question it answers | Enforcement |
|---|---|---|
| [docs/patterns/](../../patterns/README.md) | *How is this code shaped?* Where files live, hook naming, extraction threshold, migration style | review discipline |
| [.claude/rules/](../../../rules/) | *Is this code safe and correct?* Input handling, SQL, XSS, crypto, mass assignment | scanner hard-blocks a subset |
| this file + [screens/](screens/README.md) | *What are we building?* Scope, phasing, per-screen layout | — |

When a phase says "controller + policy + service", the shape of those three is
[patterns/backend.md](../../patterns/backend.md). When it says "admin SPA screens", the
shape is [patterns/frontend.md](../../patterns/frontend.md). When it says "Blueprint
migrations", it means [patterns/database.md](../../patterns/database.md) — and specifically
**not** the sibling plugins' hand-written `CREATE TABLE` style.

---

## 7. Open questions

**Blocking Phase 1:**

1. **`guests.status` has no RSVP vocabulary.** The column comments
   `approved | checked_in | cancelled` — going / not-going is absent, and it is v1's only
   feature. Either extend the vocabulary to include `going` / `not_going`, or add a
   separate `rsvp_response` column. The schema is otherwise locked, so this needs a
   deliberate decision, not a silent widening.
2. **Who can RSVP, and from where?** v1 ships no public page and no fcom bridge, so as
   written there is no surface for a response to arrive through. Is v1 admin-only (host
   records attendance manually), or does a minimal public event page ship too? This decides
   whether Phase 4 is a form or an admin table.
3. **`wp_fe_meta` vs `wp_postmeta`.** Both exist. The dividing line — settings sections in
   `wp_fe_meta`, per-event open-ended config in `wp_postmeta` — needs writing down before
   models are written, or it will drift.

**Blocking Phase 1 — CPT registration:**

4. **Is the event CPT `public`?** Registering `fevent_event` with `public => true` makes
   every event reachable on the front-end immediately, rendered by the active theme's
   template, at a rewrite slug we choose now. `public => false` keeps v1 strictly
   admin-only. This interacts with question 2 — if events are front-end visible, that is
   where an RSVP could arrive from. The rewrite slug is also hard to change later without
   breaking every published URL, so it wants deciding before the CPT is written, not after.

**Blocking screen work, not the API:**

5. **Deleting a calendar that has events.** The FK refuses a plain delete. Block while
   events exist, cascade, or reassign them? Surfaces behind the calendar card's settings
   icon.
6. **Does upcoming/past apply in calendar grid view?** A period filter is meaningless once
   the user is navigating month by month. Hide the toggle in grid view, or define its
   behaviour.
7. **Calendars empty state.** A fresh install has no calendars, and `calendar_id` is NOT
   NULL, so the Events screen is dead until one exists. The empty grid is the onboarding
   entry point and needs designing as such, not left as a bare "no results".
**Blocking release, not development:**

8. **Plugin header domain.** [fluent-event.php:7](../../../../fluent-event.php) and `:13`
   point at `https://fluentevent.com`. PR #1 changed these from the plural form while its
   own body claimed it preserved them. Confirm which domain is owned — a dead URL in the
   header gets flagged in plugin review.

---

## 8. Immediately after v1

**FluentCommunity integration** (FR-10) is the next increment, not part of v1. It registers
an `event` space type bound 1:1 to a calendar. Two things to keep in view while building v1
so the bridge doesn't force a rewrite:

- Events always live in `wp_fe_*`; fcom stores none of them.
- In integrated mode fcom becomes the canonical identity for the space.

Full spec: [fluentcommunity-integration.md](../../functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md).
