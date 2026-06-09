# Scope Gate — file pattern → ruleset map

Mirror of the scope-gating table in `.claude/commands/feature-review.md`. Keep in lockstep.

Always load `rulesets/always.md`. Then load every ruleset whose trigger matches any touched file in this task.

## Pattern → ruleset

| Touched file pattern | Load ruleset |
|----------------------|--------------|
| (anything matching skill triggers) | `always.md` |
| `app/Http/Routes/**` (free), `Modules/Course/Http/course_api.php`, `Modules/Migrations/Http/migration_api.php`, `Modules/Integrations/FluentCart/Http/cart_api.php`, `Modules/Integrations/FluentPlayer/Http/player_api.php`, pro `app/Http/Routes/api.php` + `app/Http/Routes/routes.php`, pro module route files (`app/Modules/Giphy/Http/giphy_api.php`, `app/Modules/Quiz/Http/quiz_api.php`, `app/Modules/LeaderBoard/Http/leaderboard_api.php`) | `routes.md` |
| `app/Hooks/Handlers/ActivityMonitorHandler.php` (`wp_ajax_fluent_community_renew_nonce`), `Modules/Auth/AuthModdule.php` (`wp_ajax[_nopriv]_fcom_user_registration`, `wp_ajax[_nopriv]_fcom_user_login_form`), `Modules/Auth/Classes/InvitationHandler.php` (`wp_ajax_fcom_user_accept_invitation`), pro `app/Hooks/Handlers/CoreDepenedencyHandler.php` (`wp_ajax_fcom_install_core_plugin`) — AJAX registration files | `routes.md` + `hooks.md` |
| `app/Http/Policies/**` (free), pro `app/Http/Policies/**` (3 own: `UserPolicy.php`, `TopicPolicy.php`, `ModerationPolicy.php`), `app/Services/Helper.php` (`isSiteAdmin`, `canAccessPortal`, `getCurrentProfile`), `app/Models/User.php` (`hasCommunityAdminAccess`, `hasSpaceManageAccess`, `getSpaceRole`, `verifySpacePermission`) | `policies.md` |
| `app/Models/**`, `database/Migrations/**`, `database/DBMigrator.php` | `models-migrations.md` |
| `app/Http/Controllers/**`, `app/Services/**`, `Modules/**/Controllers/**`, `Modules/**/Services/**` with DB writes/reads (`->create`, `->update`, `->fill`, `->save`, `->insert`, `->delete`, `$wpdb->insert/update/delete/replace/query`, raw SQL) | `controllers-services.md` + `models-migrations.md` |
| `src/**` (Vue 3 SPA — Options API), pro `../fluent-community-pro/src/Onboarding.vue` | `vue.md` + `a11y-i18n.md` |
| `Modules/**` (free — top-level), `Modules/FeaturesHandler.php`, `Modules/modules_init.php`, pro `app/Modules/**` (note: under `app/`, NOT top-level), pro `app/Core/` (Application bootstrap), pro `app/Utils/` (Enqueuer + helpers) | `modules.md` |
| `src/stores/**` (Pinia stores — `cache.js`, `feed.js`, `space.js`, `user.js`, `notification.js`, `ticker.js`, `ui.js`), `defineStore` introductions, `pinia-plugin-persistedstate` config edits | `pinia-stores.md` |
| `app/Hooks/**`, `app/Hooks/actions.php`, `app/Hooks/filters.php`, `app/Hooks/Handlers/**`, `app/Hooks/Scheduler.php`, `boot/app.php` (activation/deactivation hooks) | `hooks.md` |
| Pro `app/Modules/Webhooks/**`, pro `app/Modules/CloudStorage/**` (S3/R2/BunnyCDN drivers), pro `app/Modules/Integrations/Paymattic/**`, pro `app/Modules/Followers/**`, OAuth callback handlers, gateway integrations, webhook signature compares | `integrations.md` |
| `switch_to_blog`, `is_multisite`, `site_option`, `$wpdb->base_prefix`, `wp_cache_*` or `set_transient` on per-site data, `database/DBMigrator.php` | `multisite.md` |
| `app/Hooks/Handlers/EmailNotificationHandler.php`, `app/Services/Libs/Mailer.php`, `app/Services/Libs/DailyDigest.php`, `app/Services/Libs/EmailComposer.php`, `app/Services/Libs/FileSystem.php`, `app/Services/EmailDomBuilder.php`, any new `wp_mail()` callsite or header build | `email.md` |
| New `wp_remote_get` / `wp_remote_post` / `wp_remote_request` / `wp_safe_remote_*` (existing callsites: `app/Hooks/CLI/Commands.php`, `app/Services/OnboardingService.php`, `app/Services/RemoteUrlParser.php`, pro `app/Modules/CloudStorage/BunnyCDN/BunnyCdnDriver.php`, pro `app/Modules/CloudStorage/S3/S3.php`, pro `app/Modules/Giphy/Services/GiphyService.php`), OAuth callback handler | `outbound-http.md` |
| `package.json`, `composer.json`, `*-lock.*` (`package-lock.json` + `pnpm-lock.yaml` BOTH present in free), `vite.unified.config.js` (free build — Vite), pro `webpack.mix.js` (pro build — Laravel Mix, DIFFERENT system), pro `package.json` + lockfiles, `build.sh`, `deploy.sh`, `.github/workflows/**`, `gutenberg/build/**` config, `custom-editor/**` build config | `deps-build.md` |
| Hook/filter rename or removal, route removal, migration files, `$fillable` change, shortcode addition/change, public CSS class / DOM ID rename, PHP version-specific call (`str_contains`, `enum`, `readonly`, `match`, `?->`), MySQL version-specific (JSON cols, generated cols, CTE) | `backwards-compat.md` |
| `fluent-community.php` (plugin header + version constants `FLUENT_COMMUNITY_PLUGIN_VERSION`, `FLUENT_COMMUNITY_DB_VERSION`, `FLUENT_COMMUNITY_MIN_PRO_VERSION`), `config/app.php` (REST namespace, text domain, cipher, hook prefix), pro `fluent-community-pro.php` (`FLUENT_COMMUNITY_PRO_VERSION`, `FLUENT_COMMUNITY_MIN_CORE_VERSION`) | `backwards-compat.md` |
| `tests/**`, `src/__tests__/**`, `phpunit.xml.dist`, `playwright.config.js`, `vitest.config.js`, test file deletion in this diff | `tests.md` |

## Tiny-diff shortcut

If the planned change is < 50 lines and matches only one ruleset beyond `always.md`, skip the pre-flight sub-agent spawn. Load the rulesets directly. Still run self-audit at the end.

## Pro repo

Cross-repo edges (free → pro hook consumers, pro → free symbol calls) — always load `backwards-compat.md` and `hooks.md` even when triggered for a single-repo reason.

Pro is a pure extension shell — no own REST namespace, no own hook prefix, no own emails. Pro emits hooks under the free `fluent_community/*` namespace. 199 references to `FluentCommunity\App\` exist in pro.

## Conflict rule

If two rulesets disagree, security wins over optimization, BC wins over maintainability. Cite both in self-audit if both apply.
