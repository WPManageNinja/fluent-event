# Fluent Events — Coding Rules

Write-time guardrails for Fluent Events source (`wp-content/plugins/fluent-events`).
The PostToolUse hook [`.claude/hooks/coding-rules-scan.sh`](../hooks/coding-rules-scan.sh)
enforces a hard-block subset of these rules on every Edit/Write — diff-aware, so it
only flags patterns introduced by the current edit. These files are the
human-readable rule of record; the scanner is the safety net.

This folder holds all coding rules for Fluent Events — universal discipline,
framework-wide backend/frontend rules, and the scanner-enforced area rules. (The
former `coding-rules` skill was removed; its enforced rulesets live here now.)

## Conventions

- Version constant: `FLUENT_EVENTS_PLUGIN_VERSION` — confirm against the plugin bootstrap file once it exists.
- Tables: `wp_fe_*` — prefix is `wp_fe_`, and the event table is **`wp_fe_event_details`** (the typed 1:1 extension of the `fevents_event` post; identity lives in `wp_posts`). Full set: `event_details`, `ticket_types`, `guests`, `calendars`, `notifications`, `notification_users`, `meta`. Ticketing: event → ticket_types → guests (1 row per ticket; an **order = guest rows sharing `registration_no`**, the `is_buyer=1` row carries order+payment fields, NULL on companions — **no registrations table**). Attendee identity = WP users (hosts/managers) + anonymous guests (name/email, optional nullable `user_id` when logged in); no custom profile/auth/activity tables. **Entry mode** (`_fevents_settings.registration_type` in `wp_postmeta`): `ticketed` (guest row requires a `ticket_type_id`), `rsvp` (guest row, `ticket_type_id` NULL, going/not-going via `status`), `open` (no guests, no ticket_types — just a listing). `guests.ticket_type_id` is nullable; ticketed mode enforces it in the service layer. **Images = plain URLs in the owning record's `settings` JSON** — never image columns, never an attachment FK (`_fevents_settings.cover_url` in `wp_postmeta` for events, `calendars.settings.logo_url`/`.cover_url` for calendars). WP media library is still the uploader; we persist the resolved URL.
- **In-app notifications (fcom-style, 2 tables)**: `notifications` = the record (render-once: action/title/content/route/actor/object). `notification_users` = per-recipient delivery **and** per-user prefs in ONE table, split by `object_type` (`notification` delivery vs `notification_pref`); delivery `object_id`=notification id + `is_read`=read-flag, pref `object_id`=scope + `notification_type`=pref key + `is_read`=on/off. Indexed by user (inbox) + by object (recipients / who-subscribed). **Email** → guests only, non-negotiable, no DB trace. In-app = logged-in WP users only.
- **Event = CPT hybrid**: `wp_posts` (`post_type=fevents_event`: title/slug/body/status/author) + `wp_postmeta` (`_fevents_settings` config JSON + addon fields) + `wp_fe_event_details` (1:1 `post_id` FK, NOT NULL + UNIQUE: typed start_at/end_at/timezone/venue_type/calendar_id/status/parent_event_id + `form_fields` JSON — what ticketing FKs to). **No `settings` column and no inheritance** on event_details: every row is self-contained, so a recurrence occurrence has its own post and its own full row (no `COALESCE` reads, no `is_overridden`). **No currency anywhere** — it's one global plugin setting. See [docs/db/implementation.md](../docs/db/implementation.md).
- **Classification = WP taxonomies** on `fevents_event`: `fevents_event_type`, `fevents_venue`, `fevents_organizer`, `fevents_performer` (reusable, filterable, term meta for extra fields). Performer is thin (name+photo, no profile page); no `people` table. Sponsors deferred (future taxonomy).
- REST namespace: `fluent-events/v1` (assumed; confirm against `config/app.php`). Hook prefix: `fluent_events/`. Text domain: `fluent-events`. Namespace: `FluentEvents\`.
- Framework: WPManageNinja / WPFluent ORM — controllers extend a base `Controller`, validate via `$this->validate()`, read input via `$this->request->get()/getSafe()`, respond via `sendSuccess()/sendError()`.
- Frontend: Vue **Options API only** — no `<script setup>` / Composition API.

## Files

| File | Domain | Scanner rules |
|------|--------|---------------|
| [always.md](always.md) | Universal — input, SQL, XSS, crypto, logic | 1 |
| [controllers-services.md](controllers-services.md) | Layering + mass assignment | 2, 3 |
| [routes.md](routes.md) | REST/AJAX permission + ownership | 4 |
| [vue.md](vue.md) | v-html, Options API, postMessage, listeners | 7, 8, 15 |
| [a11y-i18n.md](a11y-i18n.md) | Frontend a11y + i18n (translation, ARIA, focus, alt) | — (self-audit) |
| [fix-patterns.md](fix-patterns.md) | unserialize, eval, randomness, hmac | 5, 6, 10, 11 |
| [outbound-http.md](outbound-http.md) | `wp_remote_*` timeout + SSRF | 9 |
| [email.md](email.md) | Header injection + recipient trust | 13 |
| [multisite.md](multisite.md) | `switch_to_blog` / scope | 12 |
| [deps-build.md](deps-build.md) | Asset versioning + deps | 14 |

## Opt-outs (silence the scanner only — the rule still stands)

- Per-line: add `phpcs:ignore` or `coding-rules-scan:ignore` on the line.
- Per-file: add a comment containing `coding-rules-scan: off`.
- Per-session: `export CODING_RULES_SCAN_OFF=1`.
