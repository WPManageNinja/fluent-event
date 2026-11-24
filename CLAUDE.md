# CLAUDE.md

FluentEvent is a WordPress events plugin: **calendars hold events, events collect guests.**
Everything else is detail hanging off that spine.

The full rule of record is [`.claude/rules/`](.claude/rules/README.md), and a PostToolUse hook
hard-blocks a subset on every edit. This file is the shape of the thing — read it first, then
the rule file for whatever you are touching.

## 1. The Data Model

An event is a **CPT hybrid**. Identity lives in WordPress, typed columns live in our table.

- `wp_posts` (`post_type=fevent_event`) — title, slug, body, status, author.
- `wp_fevent_event_details` — 1:1 on `post_id`, NOT NULL. Dates, calendar, venue, `settings` JSON.
- `wp_postmeta` — open-ended blobs only. Nothing queryable belongs here.

Seven tables, all `wp_fevent_*`: `event_details`, `ticket_types`, `guests`, `calendars`,
`notifications`, `notification_users`, `meta`.

Three things people get wrong:

- **There is no registrations table.** A guest row *is* a ticket. An order is guest rows
  sharing a `group_id`; the `is_buyer=1` row carries the payment fields, NULL on companions.
- **No inheritance between events.** Recurrence occurrences own their own post and their own
  values — no `COALESCE` reads, no override flags.
- **Images are URLs in the record's JSON column.** Never an image column, never an
  attachment FK. The column is `meta` on both `event_details` and `calendars` — open-ended,
  holds whatever per-record config accrues, and is documented by the model's `defaultMeta()`.

Entry mode lives in `event_details.meta.entry_type` — `rsvp` (logged-in going / not going, on the portal and the public event page alike; there a visitor can also say Going by name + email, final),
`open` (a Register button opening a name + email popover — prefilled for members, email locked; a Registered badge after; in the portal a member can say Not going (`not_going`) and register again, a visitor's is final) or
`none` (view-only: nobody registers, every viewer sees the whole event, meeting link included;
admins can still add, edit and remove guests, who get the usual emails). Guests only ever answer going or not going; `cancelled` is set by an admin alone.
Registering again with an email (or account) already on the event takes a `not_going` row back to going (a visitor form,
signed in or not, can't for a row linked to an account: that member answers by account); any other row is
refused with a warning, "already registered", or "registration was cancelled" for a `cancelled` one, and nothing is saved. `show_guest_count` sits beside it for `rsvp` and `open`, and so does `capacity`: the most guests going at once (0 = unlimited); a full event refuses a new Going or Register (a `not_going` row coming back included), a guest already going keeps their seat, and admins can add guests past it. The public landing pages enforce it too. `waitlist` (off by default, only with a capacity) turns that refusal into a `waitlisted` row: the button reads Join waitlist, a waitlisted guest holds no seat (no count, no meeting link, no reminders), gets the waitlist email, can leave as `not_going`, is refused with "already on the waitlist" on registering again, and only an admin moves them to going. `show_spots_left` (off by default, only with a capacity) shows the spots left: with both switches on guests see the meter ("7 out of 10 going", "3 spots left"), with one only its line ("7 going" or "3 spots left"), and no spots left once the event ends. `ticketed` is a documented future
mode; `guests.ticket_type_id` stays nullable for it.

Statuses: events are `public | unlisted | private | draft`, calendars `public | private | draft`.
An `unlisted` event is left off the upcoming landing page but opens by direct link; a calendar has no
page of its own to share, so it has no `unlisted`.
`private` means no public page; space calendars start `private`. On the public event page a logged-in viewer gets the
member's card, as on the portal: their own answer, Going / Not going straight from the RSVP menu, and on an open event Registered with Not going, or
the form with name and email prefilled; the email stays editable, and another email registers that address as a visitor. Logged out, it is the visitor's card. FluentCommunity integration (events as posts
in discussion spaces): [.claude/docs/functional-requirements/fluentcommunity-integration.md](.claude/docs/functional-requirements/fluentcommunity-integration.md).

Schema: [docs/db/fe_db_schema.sql](.claude/docs/db/fe_db_schema.sql).

## 2. Layering

Each layer does one job. Crossing them is the most common review comment.

- **Controllers** — validate, perform or delegate the write, emit the `do_action` in
  generally hooks, return the payload array. No `$wpdb`.
- **Services** — logic with more than one caller, or a job with no controller on the
  stack. No `do_action`: the controller owns the request's hooks, so a service reused
  elsewhere can't fire them by surprise. Only a path with no controller (cleanup
  handler, scheduled job) emits its own.
- **Models** — persistence, scopes, relationships.

A service is earned through **reuse**, not by the kind of work it does. A one-caller write
stays in its controller even when it opens a `DB::transaction` — `EventController` brackets
the `wp_posts` insert and the `event_details` row that way. Extract when a second caller
appears.

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
