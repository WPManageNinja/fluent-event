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
- Tables: `wp_fevents_*` (events, ticket_types, registrations, guests, calendars, notifications, notification_prefs, meta). Ticketing: event → ticket_types → registrations (buyer+payment) → guests (1 per ticket). `notification_prefs` = per-user, row-per-(scope,event_key,channel), two-way indexed (by user + by trigger); global=`scope_id 0`. Attendee identity = WP users (hosts/managers) + anonymous guests (name/email/phone); no custom profile/auth/activity tables. Media = WP library (`wp_posts` attachment IDs).
- **Event = CPT hybrid**: `wp_posts` (`post_type=fevents_event`: title/slug/body/status/author/cover) + `wp_postmeta` (form_fields, settings, addon fields) + `wp_fevents_events` (1:1 `post_id` FK: typed dates/venue_type/join_policy/calendar_id — what ticketing FKs to). See [docs/db/implementation.md](../docs/db/implementation.md).
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
