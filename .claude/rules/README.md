# FluentEvent — Coding Rules

Write-time guardrails for FluentEvent source (`wp-content/plugins/fluent-event`).
The PostToolUse hook [`.claude/hooks/coding-rules-scan.sh`](../hooks/coding-rules-scan.sh)
enforces a hard-block subset of these rules on every Edit/Write — diff-aware, so it
only flags patterns introduced by the current edit. These files are the
human-readable rule of record; the scanner is the safety net.

This folder holds all coding rules for FluentEvent — universal discipline,
framework-wide backend/frontend rules, and the scanner-enforced area rules. (The
former `coding-rules` skill was removed; its enforced rulesets live here now.)

## Conventions

- Version constant: `FLUENT_EVENT_PLUGIN_VERSION` — confirm against the plugin bootstrap file once it exists.
- Tables: `wp_fe_*` — prefix is `wp_fe_`, and the event table is **`wp_fe_event_details`** (the typed 1:1 extension of the `fe_event` post; identity lives in `wp_posts`). Full set: `event_details`, `ticket_types`, `guests`, `calendars`, `notifications`, `notification_users`, `meta`. Ticketing: event → ticket_types → guests (1 row per ticket; an **order = guest rows sharing `group_id`**, the `is_buyer=1` row carries order+payment fields, NULL on companions — **no registrations table**). Attendee identity = WP users (hosts/managers) + anonymous guests (name/email, optional nullable `user_id` when logged in); no custom profile/auth/activity tables. **Entry mode** (`event_details.meta.entry_type`): `rsvp` (logged-in guest rows, going/not-going via `status`; on the public event page anyone, logged in or not, can only say Going, by name + email, with no undo — the landing pages treat every viewer as a visitor), `open` (a Register button opening a name + email popover — prefilled for members, email locked; a Registered badge after; in the portal a member can cancel it (`cancelled`) and register again, a visitor's is final), `none` (view-only: nobody registers, every viewer sees the whole event including the meeting link; admins can still add, edit and remove guests, who get the usual emails). `meta.capacity` caps how many guests can be going on an `rsvp` or `open` event (0 = unlimited); admins can add past it, and the public landing pages enforce it, same as the portal. `ticketed` (guest row requires a `ticket_type_id`) is a future mode, so `guests.ticket_type_id` stays nullable. **Statuses**: events are `public | unlisted | private | draft`, calendars `public | private | draft`; `unlisted` (events only) = left off the upcoming landing page, opens by direct link; `private` = no public page. **Images = plain URLs in the owning record's JSON column** — never image columns, never an attachment FK (`event_details.meta.cover_url`, `calendars.meta.logo_url`/`.cover_url`). WP media library is still the uploader; we persist the resolved URL. Both JSON columns are named `meta`. The calendar column is deliberately open-ended — new per-calendar config lands there without a migration, documented by the model's `defaultMeta()` (v1 stores only `theme_color`).
- **In-app notifications (fcom-style, 2 tables)**: `notifications` = the record (render-once: action/title/content/route/actor/object). `notification_users` = per-recipient delivery **and** per-user prefs in ONE table, split by `object_type` (`notification` delivery vs `notification_pref`); delivery `object_id`=notification id + `is_read`=read-flag, pref `object_id`=scope + `notification_type`=pref key + `is_read`=on/off. **Indexes**: `notifications` = **PRIMARY only** (always fetched by PK from the delivery row's `object_id`; rendered content means a notification outlives its object, so there's no delete-by-object query). `notification_users` = `UNIQUE(user_id, object_type, object_id, notification_type)` (idempotent fan-out via `INSERT IGNORE` + one pref per key + the inbox seek), `idx_notif_user_inbox (user_id, is_read, object_type)` (the unread badge — `COUNT(*)` has no `LIMIT`, so `is_read` must be indexed; all three filter columns are in it, so the count is covering), and `idx_notif_user_object (object_id, is_read, object_type, notification_type)` (recipients of a notification / who's subscribed to a scope). Inbox = `ORDER BY object_id DESC`, never `ORDER BY` the joined `notifications.created_at` (forces a filesort). **Email** → guests only, non-negotiable, no DB trace. In-app = logged-in WP users only.
- **Event = CPT hybrid**: `wp_posts` (`post_type=fe_event`: title/slug/body/status/author) + `wp_postmeta` (add-on / open-ended blobs only) + `wp_fe_event_details` (1:1 `post_id` FK, NOT NULL + UNIQUE: typed start_at/end_at/calendar_id/status/parent_event_id, nullable timezone/venue_type, plus `form_fields` and `settings` JSON — what ticketing FKs to). **No inheritance**: `post_id` is NOT NULL so every row — recurrence occurrences included — owns its own post and its own full values (no `COALESCE` reads, no `is_overridden`). `status` is `public|unlisted|private|draft` (default `public`) and overlaps `post_status` — keep them in step on write. **No currency anywhere** — it's one global plugin setting. See [docs/db/fe_db_schema.sql](../docs/db/fe_db_schema.sql).
- **Classification = WP taxonomies** on `fe_event`: `fe_event_type`, `fe_venue`, `fe_organizer`, `fe_performer` (reusable, filterable, term meta for extra fields). Performer is thin (name+photo, no profile page); no `people` table. Sponsors deferred (future taxonomy).
- REST namespace: `fluent-event/v2` (from `config/app.php` — `rest_namespace` + `rest_version`). Hook prefix: `fluent_event/`. Text domain: `fluent-event`. Namespace: `FluentEvent\`.
- Framework: WPManageNinja / WPFluent ORM — controllers extend a base `Controller`, validate via `$this->validate()`, read input via `$this->request->get()/getSafe()`, return the payload array on success and `sendError()` on failure.
- Frontend: Vue **Options API only** — no `<script setup>` / Composition API.

## Files

| File | Domain | Scanner rules |
|------|--------|---------------|
| [always.md](always.md) | Universal — input, SQL, XSS, crypto, logic | 1 |
| [controllers-services.md](controllers-services.md) | Layering + mass assignment | 2, 3 |
| [routes.md](routes.md) | REST/AJAX permission + ownership | 4 |
| [vue.md](vue.md) | v-html, Options API, postMessage, listeners | 7, 8, 15 |
| [bem.md](bem.md) | CSS class naming — BEM, prefixes, modifier specificity | — (self-audit) |
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
