# v1 screens

One file per screen. Scope, phasing, and cross-cutting decisions live in
[../v1_docs.md](../v1_docs.md) — these files cover only what each screen is and how it is
built.

| Screen | Route | Nav | Phase | File |
|---|---|---|---|---|
| Dashboard | `#/` | primary | 5 | [dashboard.md](dashboard.md) |
| Calendars | `#/calendars` | primary | 2 | [calendars.md](calendars.md) |
| Create calendar | `#/calendars/new` | — | 2 | [calendar-create.md](calendar-create.md) |
| Calendar detail | `#/calendars/:id` | — | 2–3 | [calendar-detail.md](calendar-detail.md) |
| Events (all calendars) | `#/events` | primary | 3 | [events.md](events.md) |
| Create event | `#/events/new` | — | 3 | [event-create.md](event-create.md) |
| Event detail | `#/events/:id` | — | 3–4 | [event-detail.md](event-detail.md) |
| Settings | `#/settings` | secondary | 2 | [settings.md](settings.md) |

## Follow the coding patterns

Layout of these screens is specified here; **how the code is written is specified in
[docs/patterns/](../../../patterns/README.md)** — in particular
[frontend.md](../../../patterns/frontend.md) for where components live, the `_` / `__`
visibility convention, the do-not-extract-before-the-third-use rule, and how modals are
built.

## Conventions every screen follows

- **Options API only.** No `<script setup>`, no Composition API — scanner-enforced.
- **REST through the wrapper.** `$get` / `$post` / `$put` / `$delete` mixins, never ad-hoc
  `axios` or `fetch`; the wrapper supplies `X-WP-Nonce` and renewal.
- **Every string through `$t()`.** No hardcoded English in templates.
- **No colour literals.** Theme CSS variables only, legible in light and dark.
- **Submit buttons disable in flight**, and every REST failure surfaces to the user.
- **Destructive actions are confirmed** via
  [Confirm.vue](../../../../../resources/admin/components/Confirm.vue) — and the matching
  route needs its own policy method, never the `verifyRequest()` fallback.

Full rules: [vue.md](../../../../rules/vue.md),
[a11y-i18n.md](../../../../rules/a11y-i18n.md),
[routes.md](../../../../rules/routes.md).

## Design reference

**fluent-affiliate** for layout, spacing, and card treatment — with two deliberate
exceptions where affiliate has no equivalent: the calendars grid and the calendar grid view
both take from **fluent-booking**.

Routing now matches affiliate too: the server-declared `vue.php` route tree was removed in
[#9](https://github.com/WPManageNinja/fluent-event/pull/9), so the topbar is a PHP menu array
and the routes are a static list in
[routes.js](../../../../../resources/admin/router/routes.js).

> Directory casing trap: affiliate uses `resources/admin/Modules/` (capital M). Our vite
> glob is `resources/admin/modules/**/*.vue`, lowercase. Matching affiliate's casing breaks
> component resolution silently.

## Adding a screen

1. One entry in [routes.js](../../../../../resources/admin/router/routes.js), with
   `meta.active_menu` naming the topbar key it should light up.
2. One directory under `resources/admin/modules/`.
2b. One entry in `AdminMenuHandler::getMenuItemsList()` / `getRightMenuItemsList()` **only
   if** it deserves a topbar link. Detail screens do not.
3. One `add_submenu_page` in
   [AdminMenuHandler](../../../../../app/Hooks/Handlers/AdminMenuHandler.php) **only if** it
   deserves a WordPress sidebar entry. Detail screens do not.
