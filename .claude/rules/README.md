# Fluent Events — Coding Rules

Write-time guardrails for Fluent Events source (`wp-content/plugins/fluent-events`).
The PostToolUse hook [`.claude/hooks/coding-rules-scan.sh`](../hooks/coding-rules-scan.sh)
enforces a hard-block subset of these rules on every Edit/Write — diff-aware, so it
only flags patterns introduced by the current edit. These files are the
human-readable rule of record; the scanner is the safety net.

This folder holds **common coding rules** — universal discipline plus framework-wide
backend/frontend rules that apply to every file regardless of feature. The
feature/area rulesets (`email`, `multisite`, `outbound-http`, `deps-build`) stay in
the broader [`coding-rules` skill](../skills/coding-rules/rulesets/); the scanner
cites them there.

## Conventions

- Version constant: `FLUENT_EVENTS_PLUGIN_VERSION` — confirm against the plugin bootstrap file once it exists.
- Tables: `wp_fevents_*` (events, calendars, calendar_followers, event_guests, profiles, meta, media, activities).
- REST namespace: `fluent-events/v1` (assumed; confirm against `config/app.php`). Hook prefix: `fluent_events/`. Text domain: `fluent-events`. Namespace: `FluentEvents\`.
- Framework: WPManageNinja / WPFluent ORM — controllers extend a base `Controller`, validate via `$this->validate()`, read input via `$this->request->get()/getSafe()`, respond via `sendSuccess()/sendError()`.
- Frontend: Vue **Options API only** — no `<script setup>` / Composition API.

## Files (common rules — here)

| File | Domain | Scanner rules |
|------|--------|---------------|
| [always.md](always.md) | Universal — input, SQL, XSS, crypto, logic | 1 |
| [controllers-services.md](controllers-services.md) | Layering + mass assignment | 2, 3 |
| [routes.md](routes.md) | REST/AJAX permission + ownership | 4 |
| [vue.md](vue.md) | v-html, Options API, postMessage, listeners | 7, 8, 15 |
| [fix-patterns.md](fix-patterns.md) | unserialize, eval, randomness, hmac | 5, 6, 10, 11 |

## Area rulesets (in the skill — `../skills/coding-rules/rulesets/`)

| File | Domain | Scanner rules |
|------|--------|---------------|
| `outbound-http.md` | `wp_remote_*` timeout + SSRF | 9 |
| `email.md` | Header injection + recipient trust | 13 |
| `multisite.md` | `switch_to_blog` / scope | 12 |
| `deps-build.md` | Asset versioning + deps | 14 |

## Opt-outs (silence the scanner only — the rule still stands)

- Per-line: add `phpcs:ignore` or `coding-rules-scan:ignore` on the line.
- Per-file: add a comment containing `coding-rules-scan: off`.
- Per-session: `export CODING_RULES_SCAN_OFF=1`.
