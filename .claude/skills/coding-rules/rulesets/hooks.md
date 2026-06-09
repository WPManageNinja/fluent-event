# hooks.md — WordPress hooks + schedulers

Triggered when editing `app/Hooks/**`, `app/Hooks/actions.php`, `app/Hooks/filters.php`, `app/Hooks/Handlers/**`, `app/Hooks/Scheduler.php`, `boot/app.php`, or anywhere a `do_action(...)` / `apply_filters(...)` is added/removed/renamed.

## Where things actually live

- **Hook registration files**: `app/Hooks/actions.php`, `app/Hooks/filters.php`.
- **Handlers** (13 files in `app/Hooks/Handlers/`): `ActivationHandler`, `ActivityMonitorHandler`, `CleanupHandler`, `CustomizerHander` (sic — existing typo, retained for BC), `DeactivationHandler`, `EmailNotificationHandler`, `EventHooksHandler` (stub), `FluentBlockEditorHandler`, `NotificationEventHandler`, `PortalHandler`, `PortalSettingsHandler`, `RateLimitHandler`, `Scheduler`. Activation + Deactivation use `handle()` (called from `boot/app.php` via WP hooks). The other 11 have `register()` called from `actions.php`.
- **Activation / deactivation**: `boot/app.php:11-28` — `register_activation_hook` / `register_deactivation_hook` call `ActivationHandler::handle()` and `DeactivationHandler::handle()`.
- **Module bootstrap**: `Modules/modules_init.php` listens on `fluent_community/portal_loaded` (fired in `boot/app.php:38`) → instantiates `FeaturesHandler`, `Integrations`, `MigrationModule`, `TemplateLoader`, `PushNotificationModule`. See `modules.md`.

## Hook name conventions

Convention is overwhelmingly **slash** (`fluent_community/...` — 361 distinct hook names) vs **underscore** for actual hooks. Underscore hook names exist for Action Scheduler cadence + similar (4 in free, 1 in pro):

- `fluent_community_scheduled_hour_jobs` (cadence)
- `fluent_community_daily_jobs` (cadence)
- `fluent_community_send_daily_digest` (single-action)
- `fluent_community_send_daily_digest_init` (init)
- Pro: `fluent_community_sync_user_points` (single-action)

Other `fluent_community_<word>` literals in the codebase (`fluent_community_admin`, `fluent_community_settings`, `fluent_community_onboarding`, etc.) are NOT hooks — they're `wp_localize_script` handles, option keys, or asset handles. Don't confuse them.

**Use slash form for new event hooks** — match the dominant convention. Underscore is reserved for scheduler cadence names. Don't introduce new underscore variants for regular events.

Pro emits hooks under the free `fluent_community/*` namespace too. No `fluent_community_pro/*` prefix exists in the entire pro codebase.

## Hook callsite counts

224 `do_action` + 300 `apply_filters` callsites in `app/` + `Modules/` (free). Pro has ~206 references to free's `fluent_community/*` hooks across its handlers/modules (mix of `add_action`, `add_filter`, and direct emissions). Every new hook should have at least one downstream listener — search both repos before emitting (`grep -rn "your_hook_name" app/ Modules/ ../fluent-community-pro/`).

## Hook registration style

- Inside handler classes: classic `add_action('hook_name', [$this, 'method'], $priority, $accepted_args)` — used 105 times across `app/` + `Modules/`. `$app->addAction` is NEVER used inside handler bodies (0 matches verified).
- `app/Hooks/filters.php` uses `$app->addFilter(...)` for top-level wiring (2 occurrences).
- Module integration bootstraps use `$this->app->addFilter` (e.g., `Modules/Integrations/FluentPlayer/Bootstrap.php:33`).
- `$accepted_args` MUST match callback signature. Mismatch silently drops args.

## Hook handlers — known specifics

- `ActivityMonitorHandler.php:13-44` — `fluent_community/comment_added`, `feed/created`, `track_activity`, `profile_update`, plus AJAX `wp_ajax_fluent_community_renew_nonce`. Don't add more AJAX to this class — it's already 39+ lines of `register()`.
- `CleanupHandler.php:13-41` — feed/lesson/media deletion + `deleted_user` cascade.
- `EmailNotificationHandler.php:24-39` — central email fan-out (see `email.md`).
- `Scheduler.php:13-30` — wires the two recurring cadences (see below).
- `EventHooksHandler.php:7` — `register()` is currently empty. Stub; don't add unrelated registrations here.

## Constructor hook-registration check

No FluentCommunity handler registers hooks in its `__construct`. FluentCRM integration triggers under `Modules/Integrations/FluentCRM/SpaceJoinTrigger.php` set `$this->triggerName` in `__construct` and delegate to `parent::__construct()` (`FluentCrm\App\Services\Funnel\BaseTrigger`) — the PARENT handles `add_action`, so duplicate-registration risk lives in the FluentCRM framework, not here.

When adding a new handler class, register in `register()` method, NOT in `__construct`. Instantiation pattern in `actions.php`: `(new HandlerClass())->register();` — one bootstrap location per handler.

## Callback addressability (removability)

- Closures (`function () { ... }`) are unremovable. `remove_action` cannot target them. `boot/app.php` and `Scheduler.php` use closure handlers for the recurring cadences — accepted because those are single global registrations.
- For module-level filters, prefer `[$this, 'method']` over closures if removal is plausible.
- `[ClassName::class, 'method']` is the most removable; use for cross-module hooks.

## Schedulers (Action Scheduler)

**TWO real recurring cadences** (verified):

- `fluent_community_scheduled_hour_jobs` — 3600s, registered 3x:
  - `boot/app.php:16`
  - `boot/app.php:51` (defensive re-register)
  - `app/Hooks/Handlers/ActivationHandler.php:26`
  
  Handler: `Scheduler.php:15` — calls `checkDailyDigestSchedule()` + emits `fluent_community/maybe_delete_draft_medias`.

- `fluent_community_daily_jobs` — 86400s, registered 3x:
  - `boot/app.php:20`, `:55`
  - `ActivationHandler.php:30`
  
  Handler: `Scheduler.php:24` — emits `fluent_community/remove_old_notifications` + cleans Action Scheduler logs.

NOT cadences (single-action chunked workers):
- `fluent_community/email_notify_new_posts` (`EmailNotificationHandler.php:72, 199`)
- `fluent_community/comment_added_async` (`EmailNotificationHandler.php:219, 231, 246, 357`)
- `fluent_community/email_notify_users_everyone_tag` (`EmailNotificationHandler.php:475` + `NotificationEventHandler.php:647`)
- `fluent_community_send_daily_digest` (`EmailNotificationHandler.php:563`)
- `fluent_community_send_daily_digest_init` (`Scheduler.php:60`)
- Pro: `fluent_community/feed/scheduled_publish`, `notify_profile_feed_new_post`, `notify_followers_new_post`, moderation async, course email notifications, `fluent_community_sync_user_points`

All use group `'fluent-community'` so deactivation can clean up.

Rules for new schedulers:
- Recurring task → register via `as_schedule_recurring_action(time(), <seconds>, '<hook>', [], 'fluent-community', true)`. Match pattern in `boot/app.php:16,20`.
- Named action group `'fluent-community'` so cleanup works.
- Add a handler in `app/Hooks/Handlers/Scheduler.php` (or a dedicated handler if the work is substantial — `EmailNotificationHandler` is a good template for chunked async).
- One-shot per-event → `as_schedule_single_action(time() + $delay, '<hook>', [$args], 'fluent-community')`.
- Plugin deactivation should unhook your new scheduled action (if persistence is a concern).

## New `do_action` / `apply_filters`

- Document the arg shape at the emission site (PHPDoc above the line):

```php
/**
 * @param Feed $feed
 * @param array $context
 */
do_action('fluent_community/feed/created', $feed, $context);
```

- Verify at least one consumer exists somewhere (free + pro). Emitting with zero consumers is dead code — flag and either add the listener or remove the emission.

## Free → pro hook contracts

- Pro at `../fluent-community-pro/app/Hooks/Handlers/` has 9 handler files: `AccessManagementCrmHandler`, `CoreDepenedencyHandler`, `CourseEmailNotificationHandler`, `CustomProfileFieldsHandler`, `FollowHandler`, `ModerationHandler`, `ProActionHanders` (sic), `SchedulePostHandler`, `ShortCodeHandler`. Plus `app/Hooks/actions.php`, `filters.php`, `includes.php`. Pro has ~206 references to free hooks total (consumes via `add_action`/`add_filter` AND re-emits under the same `fluent_community/*` namespace).
- Renaming or removing a `do_action` / `apply_filters` consumed by pro breaks pro silently. Search pro for every hook name before changing.
- Changing arg count = update pro consumer in the same commit OR ship a `do_action_deprecated` / `apply_filters_deprecated` shim. See `backwards-compat.md`.

## Self-audit

For each hook change:
1. `$accepted_args` matches callback signature?
2. Removable callback (no closure) if removal plausible?
3. Hook name follows slash convention (or underscore only if it's a scheduler cadence)?
4. Schedulers named + grouped under `'fluent-community'`?
5. New `do_action` has at least one real consumer somewhere (search free + pro)?
6. Free → pro contract intact (arg count, hook name unchanged or shimmed)?
7. Handler registered in ONE bootstrap location (not also from class constructor)?
8. No duplicate `do_action` emission for the same conceptual event (Eloquent lifecycle + manual `do_action`)?
9. New `wp_ajax_*` is plugin-prefixed (`fluent_community_*` or `fcom_*`)?
