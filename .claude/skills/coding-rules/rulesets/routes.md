# routes.md — REST routes + AJAX surfaces

Triggered when editing `app/Http/Routes/api.php`, `app/Http/Routes/routes.php`, module sub-routes (`Modules/Course/Http/course_api.php`, `Modules/Migrations/Http/migration_api.php`, `Modules/Integrations/FluentCart/Http/cart_api.php`, `Modules/Integrations/FluentPlayer/Http/player_api.php`), pro `app/Http/Routes/**`, or the AJAX-handler files listed in Surface #2 below.

## Two route surfaces in this codebase

1. **WPFluent framework REST routes** — declared via `$router->prefix(...)->withPolicy(...)->group(...)`. Resolved to WP `register_rest_route` by the framework. Route files:

   **Free:**
   - `app/Http/Routes/api.php` (166 lines, 112 routes per CLAUDE.md, 10 prefix groups)
   - `app/Http/Routes/routes.php` (12 lines — namespace wrapper that requires `api.php`)
   - `Modules/Course/Http/course_api.php` — courses + admin/courses routes (PortalPolicy + CourseAdminPolicy)
   - `Modules/Migrations/Http/migration_api.php` — BuddyPress data-import routes
   - `Modules/Integrations/FluentCart/Http/cart_api.php`, `Modules/Integrations/FluentPlayer/Http/player_api.php`

   **Pro** (`../fluent-community-pro/`):
   - `app/Http/Routes/api.php` (132 lines) — admin endpoints `/managers`, `/license`, `/messaging-setting`, `/custom-profile-fields`, follow CRUD, moderation, reports, schedule posts. Uses free's `AdminPolicy`, `PortalPolicy`, plus pro's own `UserPolicy`, `TopicPolicy`, `ModerationPolicy`.
   - `app/Http/Routes/routes.php` — namespace wrapper
   - `app/Modules/Giphy/Http/giphy_api.php` — Giphy search/insert
   - `app/Modules/Quiz/Http/quiz_api.php` — quiz CRUD + take quiz
   - `app/Modules/LeaderBoard/Http/leaderboard_api.php` — leaderboard listing

   Pro has 11 controllers (incl. base `Controller.php` that extends free's `Controller`) + 5 in modules. See pro `CLAUDE.md` for the full inventory.

2. **`wp_ajax_*` actions** — 7 `wp_ajax_*` callsites across 4 free handler files + 1 pro handler file (5 handler files total). All prefixed (`fluent_community_*` or `fcom_*`); no collision risk. Bullets below group priv + nopriv pairs per file/feature:
   - `app/Hooks/Handlers/ActivityMonitorHandler.php:39` — `wp_ajax_fluent_community_renew_nonce` (1 callsite — REST nonce renewal)
   - `Modules/Auth/AuthModdule.php:27-28` — `wp_ajax_fcom_user_registration` + `wp_ajax_nopriv_fcom_user_registration` (2 callsites — custom signup)
   - `Modules/Auth/AuthModdule.php:29-30` — `wp_ajax_fcom_user_login_form` + `wp_ajax_nopriv_fcom_user_login_form` (2 callsites — custom login)
   - `Modules/Auth/Classes/InvitationHandler.php:21` — `wp_ajax_fcom_user_accept_invitation` (1 callsite)
   - Pro `app/Hooks/Handlers/CoreDepenedencyHandler.php:22` — `wp_ajax_fcom_install_core_plugin` (1 callsite)

Most new endpoints belong on surface #1 (REST). Surface #2 is for the custom auth flow (Modules/Auth) and admin tooling.

## Namespace

- Runtime: `fluent-community/v2` from `config/app.php` (`rest_namespace` + `rest_version`).
- No `FLUENT_COMMUNITY_API_NAMESPACE` override constant defined currently (different from FluentBooking which has `FCAL_API_NAMESPACE`). If introduced for fork compatibility, follow the FluentBooking pattern.

## Policy binding (CRITICAL)

```php
$router->prefix('spaces')->withPolicy('SpacePolicy')->group(function ($router) {
    $router->delete('/{spaceSlug}', 'SpaceController@deleteBySlug')->alphaNumDash('spaceSlug');
});
```

Resolution: framework auto-suffixes `SpacePolicy` with `@deleteBySlug` → looks for `SpacePolicy::deleteBySlug()` → if missing, **falls back to `SpacePolicy::verifyRequest()`** (see `policies.md`).

Per CLAUDE.md rule 3: destructive controller methods MUST have matching policy methods, not rely on `verifyRequest` fallback.

Rule: when adding a route to a `withPolicy(...)` group, verify either:
1. The policy class has a method matching the controller action name (REQUIRED for destructive: `delete*`, `remove*`, `update*`), OR
2. The policy's `verifyRequest()` is sufficient for this action (acceptable for GET reads).

## Route parameter constraints

Use inline param validators (existing pattern in `api.php`):
- `->int('feed_id')` — integer only
- `->alphaNumDash('spaceSlug')` — alphanumeric + dash for slugs
- `->alphaNum('hash')` — alphanumeric only

Don't accept unconstrained params. Validators run before the controller; un-constrained string params reach handlers with arbitrary content.

## Public AJAX (Modules/Auth pattern)

The custom auth flow (`Modules/Auth/AuthModdule.php`) registers 4 nopriv AJAX endpoints. When adding any new public AJAX:

1. Verify nonce: `check_ajax_referer('<resource_bound_action>', 'nonce')` BEFORE processing.
2. Rate-limit — `app/Hooks/Handlers/RateLimitHandler.php` provides the helpers. Apply to all `_nopriv_` registrations.
3. Resource-bound action strings for nonces when state-changing: `wp_create_nonce("fcom_invite_$invite_hash")`.
4. Don't accept user-controlled IDs as authorization — load the resource by hash + verify the action is allowed.
5. If introducing `permission_callback => '__return_true'` on a REST route, same rules apply: rate-limit + resource-bound ownership check.

## Nonces

- Authenticated REST routes auto-validate `X-WP-Nonce` via the framework. Don't re-implement.
- The portal SPA gets its REST nonce from `window.fluentComAdmin.rest.nonce`. On expiry, `Bits/Rest.js` dispatches `fcom_renew_rest_nonce` CustomEvent → `ActivityMonitorHandler` re-issues via `wp_ajax_fluent_community_renew_nonce`. Don't bypass this flow.
- For email-link callbacks (reset, invitation, signed login URL): bind the nonce to the specific resource — `wp_create_nonce("fcom_accept_invite_$invitation_id")`. Fix-pattern row: `Public nonce on user-chosen ID`.
- `Modules/Auth/AuthModdule.php` uses signed URL hash (`maybeAutoLogin`) for invitation links — don't weaken this.

## Input validation

- Controller pattern: `$this->validate($data, $rules, $messages)`. 9 callsites in `app/` today (Admin, Profile, Space controllers).
- Sample rules: `'title' => 'required'`, `'slug' => 'nullable|string'`. Match style of existing callers.
- Only `app/Http/Requests/` (if exists) for separate Request classes — controllers inline validation by convention.

## Error response shape

- Framework returns `{message, errors, ...}` on validation errors. Use `$this->sendError(['message' => ..., 'errors' => ...], 422)` for consistent shape.
- Success: `$this->sendSuccess([...])` (preferred) OR return an array directly (framework converts). New code → prefer `sendSuccess`/`sendError`.

## REST X-HTTP-Method-Override

Per CLAUDE.md rule 6: `Bits/Rest.js` shims PUT/PATCH/DELETE as POST with `X-HTTP-Method-Override` header. Backend routes still register as PUT/PATCH/DELETE; framework handles the override. Don't register additional POST routes to duplicate this.

## Self-audit

For each route added/modified in this batch:
1. Bound to a `withPolicy(...)` group, AND the policy has either an action-named method (for destructive) or a sufficient `verifyRequest()` (for reads)?
2. Param validators (`->int(...)`, `->alphaNumDash(...)`, etc.) applied where applicable?
3. Validation at controller entry via `$this->validate(...)`?
4. Public AJAX → nonce verified (`check_ajax_referer`) + rate-limit applied + resource owned by caller (or accessed via signed hash) before any write?
5. Error response uses `sendError(...)` with consistent shape?
6. No hardcoded `fluent-community` in route URL (uses framework lookup) for fork compatibility?
