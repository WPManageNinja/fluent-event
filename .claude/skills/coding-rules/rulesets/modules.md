# modules.md — Modules/ structure

Triggered when editing anything under `Modules/**` (free) or `app/Modules/**` (pro), `Modules/FeaturesHandler.php`, `Modules/modules_init.php`, or adding a new module.

## What `Modules/` actually is

FluentCommunity uses `Modules/` as a first-class organizing concept — distinct from `app/`. Each module is a self-contained feature with its own files (potentially Models, Services, Controllers, Routes, Bootstrap), conditionally loaded at runtime.

This is different from FluentBooking's flat `app/` structure.

## Free modules (7)

| Module | Purpose | Has Routes | Has Hooks | Loaded by |
|---|---|---|---|---|
| `Auth/` | Custom signup / login / invitations | No (uses `Classes/InvitationController` directly) | 4 AJAX + filters | `FeaturesHandler::registerModules` L164 (always loads) |
| `Course/` | Course CRUD + enrollment + lessons | Yes — `Http/course_api.php` | 1 filter | `FeaturesHandler::registerModules` L157 — gated by `Helper::isFeatureEnabled('course_module')` |
| `Gutenberg/` | Editor block for community pages | No | `init`, `get_block_templates`, `body_class`, `wp_head`, `wp_footer` | `FeaturesHandler::registerModules` L161 — gated by filter `fluent_community/use_editor_block` |
| `Integrations/` | FluentCart/FluentCRM/FluentForms/FluentPlayer | Yes — cart_api.php, player_api.php | `$this->app->addFilter`, FluentCRM trigger classes | `modules_init.php:7` (always loads) |
| `Migrations/` | BuddyPress data import (NOT DDL) | Yes — `Http/migration_api.php` | 1 filter | `modules_init.php:8` — gated by `defined('BP_PLUGIN_DIR')` |
| `PushNotification/` | Push notifications | No | Comment-notification actions | `modules_init.php:11` — gated by `defined('FLUENT_NOTIFY_PLUGIN_VERSION')` |
| `Theming/` | Template loader for portal frames | No | `template_include` | `modules_init.php:9` (always loads) |

## Pro structure (different from free)

Pro top-level layout differs from free in key places. Always-resolved pro paths:

| Path | Purpose |
|---|---|
| `../fluent-community-pro/app/Core/` | Pro Application bootstrap (`App.php`, `Application.php`, `AppTrait.php`) — separate from free's `boot/` flow |
| `../fluent-community-pro/app/Utils/Enqueuer/` | Pro-specific asset enqueuer helpers |
| `../fluent-community-pro/app/Modules/` | 11 pro modules (note: under `app/`, NOT top-level like free's `Modules/`) |
| `../fluent-community-pro/app/Http/Policies/` | 3 own policies: `UserPolicy`, `TopicPolicy`, `ModerationPolicy` (reuses free's `AdminPolicy`, `PortalPolicy`, `CourseAdminPolicy`) |
| `../fluent-community-pro/app/Http/Requests/UserRequest.php` | Single Request class |
| `../fluent-community-pro/app/Hooks/Handlers/` | 9 handlers (`AccessManagementCrmHandler`, `CoreDepenedencyHandler`, `CourseEmailNotificationHandler`, `CustomProfileFieldsHandler`, `FollowHandler`, `ModerationHandler`, `ProActionHanders`, `SchedulePostHandler`, `ShortCodeHandler`) |
| `../fluent-community-pro/app/Hooks/actions.php`, `filters.php`, `includes.php` | Hook registration files |
| `../fluent-community-pro/app/Models/` | 4 models: `Follow`, `Moderation`, `User` (extends free `User`), `Model` (base extends free `Model`) |
| `../fluent-community-pro/src/Onboarding.vue` | Sole Vue component — pro's `src/` is intentionally tiny |
| `../fluent-community-pro/src/app.js`, `src/app.scss` | Pro frontend bootstrap (minimal) |
| `../fluent-community-pro/resources/` | **Empty placeholder** — don't add files here |
| `../fluent-community-pro/webpack.mix.js` | Pro build config (Laravel Mix — NOT Vite like free) |

Pro has NO `database/` directory. Schema work is INLINE `dbDelta` in module helpers (e.g. `app/Modules/Followers/FollowerHelper.php:132`) or runtime `ALTER TABLE` (e.g. `app/Services/ProfileFieldsService.php:272`).

## Pro modules (11)

`app/Modules/`: `CloudStorage/` (S3/R2/BunnyCDN drivers), `DocumentLibrary/`, `Emoji/`, `Followers/`, `Giphy/`, `Integrations/` (Paymattic only), `LeaderBoard/`, `Quiz/`, `SeoSiteMap/`, `UserBadge/`, `Webhooks/`.

Pro module route files (3): `app/Modules/Giphy/Http/giphy_api.php`, `app/Modules/Quiz/Http/quiz_api.php`, `app/Modules/LeaderBoard/Http/leaderboard_api.php`.

Pro security-sensitive modules (per feature-review.md): `Webhooks/`, `CloudStorage/`, `Integrations/`. Edits to these auto-load `integrations.md` ruleset (see scope-gate.md).

When editing `Onboarding.vue` (pro's single Vue component), the same `vue.md` + `a11y-i18n.md` rules apply.

## Module bootstrap pattern

### Free

`Modules/modules_init.php` is `require_once`'d from `boot/app.php:33-35` (after `Application` is built). It registers a single listener on `fluent_community/portal_loaded` (fired in `boot/app.php:38`) which instantiates the always-loaded modules:

```php
add_action('fluent_community/portal_loaded', function ($app) {
    (new FeaturesHandler())->register($app);
    (new Integrations())->register($app);
    if (defined('BP_PLUGIN_DIR')) {
        (new MigrationModule())->register($app);
    }
    (new TemplateLoader())->register($app);
    if (defined('FLUENT_NOTIFY_PLUGIN_VERSION')) {
        (new PushNotificationModule())->register($app);
    }
});
```

### FeaturesHandler

`Modules/FeaturesHandler.php:11-22`:
- `register(Application $app)` forces headless portal, calls `registerModules($app)`, adds dark-mode + headless asset hooks, plus an `init`-time oembed proxy handler (lines 74-151).
- `registerModules` (L153-165) conditionally loads `CourseModule` (feature flag), `EditorBlock` (filter), and `AuthModdule` (always).

## Conditional loading discipline

Every module conditionally registered must:

1. **Idempotency** — `register()` must be safe to call once per request (the framework ensures one call per module per request, but defensive).
2. **No registration in `__construct`** — register hooks in `register()`. Constructor is for property setup only. See `hooks.md` (duplicate registration risk).
3. **Feature gate semantics** — if loading is gated on a feature flag, the gate must be checked AT load time, not run-time per-call. Today: `Helper::isFeatureEnabled('course_module')` for Course. New modules with a feature flag should follow this pattern.
4. **Loading order** — modules load on `fluent_community/portal_loaded`, which fires inside `plugins_loaded`. Don't depend on a later hook unless wrapped with `did_action` guard.

## Adding a new module (free)

1. Create `Modules/<Name>/<Name>Module.php` with a `register(Application $app)` method.
2. If routes are needed, create `Modules/<Name>/Http/<name>_api.php` and `require_once` it inside `register()` via the framework router.
3. If models are needed, place in `Modules/<Name>/Model/` (note: singular "Model" — match existing pattern in `Modules/Course/Model/`).
4. If hooks are needed, register inside `register()` — NOT in the constructor.
5. Add the instantiation call to `modules_init.php` OR `FeaturesHandler::registerModules` (with optional feature gate).
6. If the module needs its own settings/admin UI, register the menu item via `fluent_community/main_menu_items` filter (Course pattern).

## Module-private REST routes

When a module declares routes (`Modules/Course/Http/course_api.php`):
- Use the module's own policy class (`Modules/Course/Http/Policies/CourseAdminPolicy.php`) — don't reuse a free policy unless the boundary is the same.
- Same `withPolicy(...)` resolution rules apply (see `policies.md`).
- New module → consider whether the policy chain should extend `BasePolicy` or `PortalPolicy` based on access scope.

## Module-private models

- New module model extends `FluentCommunity\App\Models\Model`.
- Follow `$fillable` + lifecycle hook conventions from `models-migrations.md`.
- Module-specific tables: prefix `fcom_<module>_*` (e.g., pro followers uses `fcom_followers`).

## Module-private migrations

- Free has NO module-specific migrations folder. All DDL is centralized in `database/Migrations/`.
- Pro modules use INLINE `dbDelta` calls in their helper services (`Modules/Followers/FollowerHelper.php:132-144`). When extending pro, match this pattern.
- If a NEW free module needs schema, ADD a migrator to `database/Migrations/` and register it in `database/DBMigrator.php` — don't introduce a per-module migrations folder.

## Cross-module dependencies

- Modules MAY listen to each other's hooks (loose coupling — preferred).
- Modules SHOULD NOT directly call into another module's services. If you find yourself needing cross-module logic, lift the shared helper to `app/Services/` (free) or `app/Services/` (pro).
- Module-to-module direct calls are flagged by feature-review's traceability lane.

## Self-audit

For each module change:
1. Module's `register()` does NOT register hooks in `__construct`?
2. Module bootstrap is added to `modules_init.php` OR `FeaturesHandler::registerModules` with appropriate feature gate?
3. Routes declared in `Modules/<Name>/Http/` with a module-specific policy?
4. Models follow `$fillable` discipline + lifecycle hook conventions?
5. New free module's DDL added to `database/Migrations/` (not a per-module folder)?
6. Pro module's DDL uses inline `dbDelta` matching `FollowerHelper.php:132` pattern?
7. Module's tables use `fcom_<module>_*` prefix?
8. No direct cross-module service calls (use hooks)?
