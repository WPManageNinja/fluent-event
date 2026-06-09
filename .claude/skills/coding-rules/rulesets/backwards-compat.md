# backwards-compat.md — backwards-compat / deprecation

Triggered when: hook/filter rename or removal, route removal, migration files, `$fillable` change, public CSS class / DOM ID rename, PHP version-sensitive call, MySQL version-sensitive feature, `fluent-community.php` plugin header edits, `config/app.php` edits.

## Hooks / filters

- Free emits 224 `do_action` + 300 `apply_filters`. Pro has ~206 references to free's `fluent_community/*` namespace (mix of consumers + re-emissions). Renaming or removing any silently breaks pro.
- Pro emits also under `fluent_community/*` namespace (no `_pro/*` prefix). Renaming a pro-emitted hook breaks any third-party listener.
- Removing or renaming a `do_action` / `apply_filters` requires a deprecation shim for one release:
  - `apply_filters_deprecated('old_name', $args, '<version>', 'new_name')`
  - `do_action_deprecated('old_name', $args, '<version>', 'new_name')`
- Free has zero `apply_filters_deprecated` / `do_action_deprecated` callsites currently. Adding the first establishes the pattern.
- Changing arg count on existing `do_action` / `apply_filters`: search pro at `../fluent-community-pro/app/Hooks/` for every consumer (`grep -rn 'your_hook'`). Update or shim. Don't break.

## Migrations

- Every new migration has a down-migration path (rollback) — or documented as one-way.
- Column rename: `ALTER TABLE … CHANGE COLUMN` preserving the old name as an intermediate step if data depends on it. Never drop + create — data loss.
- Adding NOT NULL column to populated table requires DEFAULT or backfill in two steps (add nullable → backfill → set NOT NULL).
- Removing a column: deprecate at code layer first (one release of "stop reading"), then drop in next release.
- Pro has NO `database/Migrations/` folder. Pro inline `dbDelta` / `ALTER` in service code (`Modules/Followers/FollowerHelper.php:132`) — same caution applies.

## Routes / AJAX

- Removed REST route or `wp_ajax_*` action: return a 410-style noisy error or alias for one release before removing entirely. Pro and third-party listeners may still call it.
- Renamed route: keep old path registered as an alias for one release.

## REST namespace constant

- REST namespace is `fluent-community/v2` from `config/app.php`. No override constant currently.
- Forks / private deployments could rely on a future override. If introducing one (e.g., `FLUENT_COMMUNITY_API_NAMESPACE`), match the FluentBooking `FCAL_API_NAMESPACE` pattern with a `defined(...) ?: 'default'` fallback.
- NEVER hardcode `fluent-community` in route URLs or client code — use the framework's `rest_namespace + rest_version` lookup.

## `$fillable` adds

- Every new entry in `$fillable` is a new mass-assignment surface. Justify it in the commit.
- The audit's Security agent flags every new `$fillable` add. Pre-empt by listing each in the PR description.

## Public API surface

- 224 `do_action` + 300 `apply_filters` make up the public surface. Pro is 109 of those.
- New filter / action: add to `changelog.txt` / `CHANGELOG.md` if one exists. Document the arg shape.
- Renamed Vue component name (`src/Modules/<X>.vue`) — themes / pro may reference it. Audit.

## CSS / DOM

- Removed or renamed CSS class on public-facing markup: themes may target it. Either keep the old class as alias or document the breaking change.
- Same rule for DOM IDs (`#fcom-portal`, `.fluent-community-frame`).

## PHP version

- `str_contains` requires PHP 8+. `enum`, `readonly` properties require PHP 8.1+. `match` requires PHP 8+. Null-safe `?->` requires PHP 8+.
- **Plugin minimum PHP: 7.3** — declared in `readme.txt` (`Requires PHP: 7.3`) for both free and pro.
- For broader compat, prefer `false !== strpos($haystack, $needle)` over `str_contains`, `array_key_exists` over null-safe chains, traditional switch over match.
- Bumping min PHP is a release-coordination event (changelog entry, deprecation notice in prior release).

## MySQL version

- JSON columns: MySQL 5.7+.
- Generated columns: 5.7+.
- CTE (`WITH … AS`): MySQL 8.0+.
- Window functions: MySQL 8.0+.

## Free / pro version-compat

- Free `FLUENT_COMMUNITY_MIN_PRO_VERSION = '2.4.01'` — pro must be ≥ this for free to allow pro to load.
- Pro `FLUENT_COMMUNITY_MIN_CORE_VERSION = '2.4.01'` — free must be ≥ this for pro to activate.
- Both must be bumped together when a free/pro contract changes.

## Plugin header + version constants (`fluent-community.php`, `config/app.php`)

Both files are scope-gate-routed to `backwards-compat.md` because their values ripple through pro + forks. Rules per constant:

- **`FLUENT_COMMUNITY_PLUGIN_VERSION`** — semver. Bump:
  - PATCH (x.y.Z) for fixes only.
  - MINOR (x.Y.0) for new features that don't break existing API.
  - MAJOR (X.0.0) for backwards-incompatible changes (route removal, hook rename without shim, schema-breaking migration).
  - Must match the `Version:` line in the plugin header AND `readme.txt:Stable tag`.
- **`FLUENT_COMMUNITY_DB_VERSION`** — bump triggers `DBMigrator::run()` via `boot/app.php:60-64`. Bumping requires a migration that is safe to run repeatedly (see `models-migrations.md`). Never bump without a matching schema change.
- **`FLUENT_COMMUNITY_MIN_PRO_VERSION`** — pro must ship ≥ this BEFORE free ships the bump. Coordinate with pro repo release.
- **`config/app.php` `rest_namespace` / `rest_version`** — runtime REST base path. Renaming breaks every client (admin Vue, portal Vue, pro consumers). Treat as a public API change — major bump + migration story for clients.
- **`config/app.php` `text_domain`** — must stay `fluent-community` (free) / `fluent-community-pro` (pro). Renaming silently breaks all translations.
- **`config/app.php` `cipher`** — encryption cipher (`AES-256-CBC`). Changing requires data re-encryption.
- **`config/app.php` `hook_prefix`** — `fluent-community`. Changing breaks every `addCustomAction` / `addCustomFilter` consumer in pro + third-party integrations.
- **Pro `FLUENT_COMMUNITY_PRO_VERSION`** — pro semver, pair with `FLUENT_COMMUNITY_MIN_CORE_VERSION` bump as needed.

When editing the plugin header `Plugin Name:` / `Description:` / `Author:` lines: cosmetic, no BC concern. `Plugin URI:` / `License:` requires legal review, not BC review.

## Self-audit

When this ruleset loaded, the final audit pass must explicitly walk: every removed/renamed hook, every removed/renamed route, every migration, every `$fillable` change, every plugin-constant bump. Empty list = clean BC.
