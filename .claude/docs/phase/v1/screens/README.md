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

Copy affiliate's *look*, not its routing: it has no `vue.php` and declares screens as PHP
submenu pages. Our nav is server-declared in
[app/Http/Routes/vue.php](../../../../../app/Http/Routes/vue.php), which is newer than
either sibling.

> Directory casing trap: affiliate uses `resources/admin/Modules/` (capital M). Our vite
> glob is `resources/admin/modules/**/*.vue`, lowercase. Matching affiliate's casing breaks
> component resolution silently.

## Adding a screen

1. One `->add()` in [vue.php](../../../../../app/Http/Routes/vue.php) under the `primary`
   or `secondary` menu group.
2. One directory under `resources/admin/modules/`.
3. One `add_submenu_page` in
   [AdminMenuHandler](../../../../../app/Hooks/Handlers/AdminMenuHandler.php) **only if** it
   deserves a WordPress sidebar entry. Detail screens do not.
