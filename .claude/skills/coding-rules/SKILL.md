---
name: coding-rules
description: Write-time guardrails for FluentCommunity code so feature-review audits pass on first run. Auto-triggers when editing PHP under app/, Modules/, database/, boot/; Vue under src/; the gutenberg/ or custom-editor/ trees; migrations; Vite/Composer/package config; or anything matching feature-review.md scope-gating. Spans BOTH free and the sibling pro plugin at ../fluent-community-pro. Applies to ALL code edits (features, fixes, refactors), not feature work only. Loads only the rulesets relevant to the touched file patterns.
---

# Coding Rules

Mirror of `.claude/commands/feature-review.md` scope-gating, applied at write time. Goal: every Critical/High/Medium finding the audit would surface is already addressed by the time the file is saved.

## When this skill fires

Auto-invoke when the user task implies editing any path that `feature-review.md` would gate on:

- `app/**` (PHP backend)
- `Modules/**` (free module trees — Auth, Course, Gutenberg, Integrations, Migrations, PushNotification, Theming)
- `database/Migrations/**`, `database/DBMigrator.php`
- `app/Hooks/actions.php`, `app/Hooks/filters.php` (hook registration files), `app/Hooks/Handlers/**`, `app/Hooks/Scheduler.php`
- `boot/app.php` (activation / deactivation hooks)
- `src/**` (Vue 3 SPA)
- `gutenberg/**`, `custom-editor/**` (React/Gutenberg blocks)
- `package.json`, `composer.json`, `package-lock.json`, `pnpm-lock.yaml`, `composer.lock`
- `vite.unified.config.js`, `build.sh`, `deploy.sh`
- `.github/workflows/**`
- Mailer / notification handlers (`EmailNotificationHandler.php`)
- Cross-repo pro work at `../fluent-community-pro/**` mirroring the same patterns
- `tests/**`, `src/__tests__/**`, `phpunit.xml.dist`, `playwright.config.js`, `vitest.config.js`

Also fires for sibling pro repo edits.

**Pro path mirroring (explicit rule).** Every scope-gate row that names a free-relative path (`app/**`, `Modules/**`, `database/**`, `boot/**`, `src/**`) ALSO matches the same path under `../fluent-community-pro/`. Treat each row as if it had a second copy with the `../fluent-community-pro/` prefix. Concrete pro paths spelled out in scope-gate rows take precedence over the implicit mirror — they're additive, not overriding.

Does NOT fire for:
- Pure prose edits to `*.md` outside `CLAUDE.md`
- `audit-*.md` / `.agent/reports/**` (audit output, not source)
- `builds/**` (release artifacts)
- `node_modules/**`, `vendor/**`

## How to use

1. **Pre-flight — path match.** Before the first Edit/Write on a matching path, read `scope-gate.md` and load every ruleset whose path pattern matches any planned-to-touch file. Always load `rulesets/always.md`.

2. **Pre-flight — content-grep.** Several scope-gate rows describe BEHAVIORS rather than file paths. After step 1, run a content-grep over (a) the current content of every file you plan to touch AND (b) the diff you plan to produce — load additional rulesets when these keywords appear:

   | Grep pattern (in planned content/diff) | Load additionally |
   |---|---|
   | `wp_mail\s*\(`, `Reply-To:` / `From:` / `Cc:` / `Bcc:` header literals, `EmailComposer`, `EmailNotificationHandler` | `email.md` |
   | `wp_remote_(get\|post\|request\|head)\s*\(`, `wp_safe_remote_`, OAuth callback, `state` parameter handling | `outbound-http.md` |
   | `switch_to_blog`, `restore_current_blog`, `is_multisite`, `(get\|update\|delete)_site_option`, `\$wpdb->base_prefix`, `wp_cache_(get\|set\|delete)`, `(get\|set\|delete)_transient` | `multisite.md` |
   | `hash_hmac` near webhook handler, `Modules/Webhooks/**`, `Modules/CloudStorage/**`, `Modules/Integrations/**`, OAuth, `client_secret`, S3 / R2 / BunnyCDN driver edits, Paymattic integration | `integrations.md` |
   | Hook removal (`do_action_deprecated`, `apply_filters_deprecated`, missing emission for a hook pro consumes), route removal in `app/Http/Routes/api.php`, migration `DROP COLUMN` / `DROP TABLE`, `$fillable` array change, public CSS class / DOM ID rename, PHP 8+ feature (`str_contains`, `enum`, `readonly`, `match\s*\(`, `?->`), MySQL 5.7+/8.0+ feature (`JSON_EXTRACT`, `GENERATED ALWAYS`, `WITH ` CTE, window function) | `backwards-compat.md` |
   | Eloquent persistence (`->create\(`, `->update\(`, `->fill\(`, `->save\(`, `->insert\(`, `->delete\(`) or `$wpdb->(insert\|update\|delete\|replace\|query)` in `app/Services/**`, `app/Http/Controllers/**`, or `Modules/**/Controllers/**` | `controllers-services.md` + `models-migrations.md` |
   | New `WHERE`/`ORDER BY`/`JOIN` against a column not in any existing index, new query pattern on a hot table (`fcom_posts`, `fcom_spaces`, `fcom_post_comments`, `fcom_notifications`, `fcom_meta`) | `models-migrations.md` |
   | New `do_action\s*\(` or `apply_filters\s*\(` emission in any file (not just `app/Hooks/**`) — services, controllers, and module bootstraps emit too | `hooks.md` |
   | `addGlobalScope` / `withoutGlobalScopes` / multi-type-table read (`fcom_spaces`, `fcom_notification_users`, `fcom_meta` access without `object_type` / `type` filter) | `models-migrations.md` (multi-type discipline) |
   | New Pinia store added under `src/stores/`, `defineStore`, `pinia-plugin-persistedstate` config change | `pinia-stores.md` |
   | `Vitest` / `phpunit` / `playwright` test file edit, deletion of an existing test, new feature without test coverage | `tests.md` |

   When the planned diff is small (< 50 lines), grep is fast — do it inline. When larger, spawn the pre-flight sub-agent with `pre-flight.md` and pass the file list + this keyword table.

3. **While coding** — keep loaded rulesets in mind. Each ruleset references `.claude/feature-review/fix-patterns.md` rows so the chosen approach matches what the audit will accept.

4. **Self-audit** — at every meaningful edit batch (claude judgment-call: finished a controller method, model + migration pair, Vue component, ≥ 3 files touched), re-run the content-grep over the actual diff (`git diff HEAD`) — new keyword hits = load additional rulesets retroactively. Then spawn a `general-purpose` sub-agent with `self-audit.md` as prompt + just-changed file list + merged matched-rulesets list. Sub-agent returns `NO ISSUES` or a finding list. Fix silently. Continue.

5. **Hook safety net** — PostToolUse hook at `.claude/hooks/coding-rules-scan.sh` greps every Edit/Write for hard-block anti-patterns. Diff-aware (only flags new lines vs HEAD). Honors `phpcs:ignore` per-line. If hit, hook exits non-zero with stderr → re-prompt with the specific violation. Self-correct.

6. **Stop before declaring done** — final self-audit pass over the full task diff (`git diff` from task start) before saying the task is complete. Re-run content-grep on full diff one last time.

## Soft enforcement

No external blocker beyond the safety-net hook. Self-corrections happen silently — do NOT narrate every finding back to the user. Surface only:
- Findings the user might want to know about (design-level, requires their judgment call)
- Findings that change the API surface the user just asked for
- Anything the user explicitly asks to see

## Ruleset registry

See `scope-gate.md` for the full pattern→ruleset map.

| Ruleset | Triggers on |
|---------|-------------|
| `always.md` | every fired invocation |
| `backwards-compat.md` | hook/filter rename or removal, route removal, migration files, `$fillable` change, public CSS/DOM renames, PHP/MySQL version-sensitive calls, plugin header/version constants |
| `routes.md` | `app/Http/Routes/**`, AJAX-registering files, module sub-routes (`Modules/*/Http/**`) |
| `policies.md` | `app/Http/Policies/**`, `app/Services/Helper.php` (`isSiteAdmin`, `canAccessPortal`), `app/Models/User.php` (role helpers) |
| `models-migrations.md` | `app/Models/**`, `database/Migrations/**`, any code accessing multi-type `fcom_*` tables |
| `controllers-services.md` | `app/Http/Controllers/**`, `app/Services/**`, `Modules/**/Controllers/**` with DB writes/reads |
| `vue.md` | `src/**` (Vue 3) |
| `modules.md` | `Modules/**`, `Modules/FeaturesHandler.php`, `Modules/modules_init.php`, pro `app/Modules/**` |
| `hooks.md` | `app/Hooks/**`, `boot/app.php`, hook emissions anywhere |
| `pinia-stores.md` | `src/stores/**`, `defineStore`, persistedstate config |
| `multisite.md` | `switch_to_blog`, `is_multisite`, `site_option`, `$wpdb->base_prefix`, `wp_cache_*`, `*_transient` on per-site data, `database/DBMigrator.php` |
| `email.md` | mailer/notification handlers, `wp_mail` callers, `EmailComposer`, daily digest |
| `outbound-http.md` | `wp_remote_*` / `wp_safe_remote_*` callsites, OAuth callback handlers |
| `integrations.md` | pro `Modules/Webhooks/**`, `Modules/CloudStorage/**`, `Modules/Integrations/**`, gateway / OAuth / webhook code |
| `deps-build.md` | `package.json`, `composer.json`, `*-lock.*`, `vite.unified.config.js`, `build.sh`, `deploy.sh`, `.github/workflows/**`, `gutenberg/`, `custom-editor/` build configs |
| `a11y-i18n.md` | any Vue UI change |
| `tests.md` | `tests/**`, `src/__tests__/**`, deletion of test files, new feature without test coverage |

## Relationship to feature-review

`.claude/commands/feature-review.md` audits AFTER the work is done. This skill applies the same rules BEFORE/DURING work so the audit finds nothing. Same scope-gate table. Same fix-patterns reference. Same severity calibration. The audit remains the source of truth; this skill is a write-time mirror.

If `feature-review.md` scope-gating changes, update `scope-gate.md` here in lockstep. Drift between the two = false confidence at write time.
