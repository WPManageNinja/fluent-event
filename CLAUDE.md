# CLAUDE.md

Fluent Event is a WordPress events plugin: **calendars hold events, events collect guests.**
Everything else is detail hanging off that spine.

The full rule of record is [`.claude/rules/`](.claude/rules/README.md), and a PostToolUse hook
hard-blocks a subset on every edit. This file is the shape of the thing — read it first, then
the rule file for whatever you are touching.

## 1. The Data Model

An event is a **CPT hybrid**. Identity lives in WordPress, typed columns live in our table.

- `wp_posts` (`post_type=fe_event`) — title, slug, body, status, author.
- `wp_fe_event_details` — 1:1 on `post_id`, NOT NULL. Dates, calendar, venue, `settings` JSON.
- `wp_postmeta` — open-ended blobs only. Nothing queryable belongs here.

Seven tables, all `wp_fe_*`: `event_details`, `ticket_types`, `guests`, `calendars`,
`notifications`, `notification_users`, `meta`.

Three things people get wrong:

- **There is no registrations table.** A guest row *is* a ticket. An order is guest rows
  sharing a `group_id`; the `is_buyer=1` row carries the payment fields, NULL on companions.
- **No inheritance between events.** Recurrence occurrences own their own post and their own
  values — no `COALESCE` reads, no override flags.
- **Images are URLs in the record's JSON column.** Never an image column, never an
  attachment FK. The column is `settings` on `event_details` and `meta` on `calendars` —
  `calendars.meta` is open-ended, holds whatever per-calendar config accrues, and is
  documented by the model's `defaultMeta()`.

Entry mode lives in `event_details.settings.registration_type` — `ticketed`, `rsvp`, or
`open`. `guests.ticket_type_id` is nullable; ticketed mode enforces it in the service.

Details: [docs/db/implementation.md](.claude/docs/db/implementation.md).

## 2. Layering

Each layer does one job. Crossing them is the most common review comment.

- **Controllers** — validate, call a service, emit the `do_action` in generally hooks, shape the
  response. No business logic, no `$wpdb`, no transactions.
- **Services** — business logic, transactions, cross-model writes. No `do_action`: the
  controller owns the request's hooks, so a service reused elsewhere can't fire them by
  surprise. Only a path with no controller (cleanup handler, scheduled job) emits its own.
- **Models** — persistence, scopes, relationships.

Two rules with teeth:

- **Every model has explicit `$fillable`.** `->fill($request->all())` is banned. Adding a
  column to `$fillable` is a security decision.
- **Multi-row atomic writes open `DB::transaction()` at the service layer.** Don't catch
  inside the closure — that commits half the work.

See [controllers-services.md](.claude/rules/controllers-services.md) and
[routes.md](.claude/rules/routes.md).

## 3. Frontend

A Vue 3 SPA in wp-admin, Element Plus for components.

- **Options API only.** No `<script setup>`, no Composition API. Scanner rule 8.
- **The topbar is PHP**, not Vue — `app/Views/admin/menu.php`. It paints with the page
  instead of waiting for the bundle. Vue owns the canvas below it, nothing more.
- **A nav item is two edits**: the menu array in `AdminMenuHandler`, and the route in
  `router/routes.js`. `meta.active_menu` matches the wrapper's `data-key`.
- **REST goes through the app wrapper** (`$get` / `$post` / …) — it handles the nonce. No
  ad-hoc `fetch`.

See [vue.md](.claude/rules/vue.md).

## 4. Styling

**A `.vue` file carries no styling.** No `<style>` block, no `style="..."`. Markup gets a
class; the class lives in `resources/scss/`.

| Concern | File |
|---|---|
| Design tokens (`--fe-*`), light + dark | `variables.scss` |
| Resets, wp-admin overrides | `globals.scss` |
| Topbar / nav | `navigation.scss` |
| Shell + page canvas | `app.scss` |
| Element Plus overrides | `vendor.scss` |

Register each in `admin.scss` with `@use` — order matters, later wins.

- **Class names are BEM** — `fe-block__element--modifier`, every block prefixed `fe-`. See
  [bem.md](.claude/rules/bem.md).
- **No hex literals outside `variables.scss`.** Read a token with a fallback:
  `var(--fe-primary-text, #19283a)`. Both modes must hold.
- **Specificity beats `!important`.** A modifier written `&--active` is 0,1,0 and loses to
  `&:hover` at 0,2,0 — write `&.fe-navbar__link--active`. Same for Element Plus: its CSS
  loads *after* ours, so an equal-specificity override silently does nothing. Read EP's real
  selector out of the built `assets/admin/index.css` first.

**One exception to all of it:** a rarely-used component needing a class nothing will ever
reuse may keep its own `<style scoped>`. When unsure, SCSS.

## 5. Conventions

Fixed. Don't reinvent them per file.

- Namespace `FluentEvent\` · text domain `fluent-event` · hook prefix `fluent_event/`
- REST namespace `fluent-event/v2`, from `config/app.php` — don't hardcode the literal
- Version constant `FLUENT_EVENT_PLUGIN_VERSION` — the asset version arg, never `time()`
- Every user-facing string translated: `esc_html__()` in PHP, the `$t()` mixin in Vue

## 6. Working Habits

- **Fix the root cause.** Reproduce, trace up the stack, fix where the bad state started.
  Guarding at the symptom site is not a fix.
- **Grep before adding a helper.** Three near-identical blocks means extract; two is fine.
- **Match the Fluent suite.** When our approach and fluent-affiliate's differ, prefer theirs
  unless we have a concrete reason. Parity is worth more than a local improvement.
- **Commit and push only when the current message says so.** Approval never carries over
  from an earlier turn. Workflow in
  [commit-push-pr](.claude/skills/commit-push-pr/SKILL.md).
