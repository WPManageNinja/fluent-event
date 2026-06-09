# controllers-services.md — controllers + services

Triggered when editing `app/Http/Controllers/**`, `app/Services/**`, `Modules/**/Controllers/**`, or `Modules/**/Services/**` with DB writes/reads.

## What's actually in the codebase

13 free controllers: `Activity`, `Admin`, `Comments`, `Feeds`, `Members`, `Notifications`, `Option`, `Profile`, `Reaction`, `Reports`, `Setting`, `Space`, plus base `Controller.php`. Largest: `FeedsController` (1535 lines), `SpaceController` (960), `ProfileController` (742), `CommentsController` (708), `SettingController` (630).

18 services in `app/Services/`: `Helper`, `AuthenticationService`, `LockscreenService`, `OnboardingService`, `ProfileHelper`, `FeedsHelper`, `UploadHelper`, `EmailDomBuilder`, `SmartCodeParser`, `RemoteUrlParser`, plus `Libs/{Mailer,FileSystem,DailyDigest,EmailComposer}`.

Module controllers exist under `Modules/Course/Http/Controllers/`, `Modules/Auth/Classes/InvitationController.php`, `Modules/Migrations/Http/Controllers/`, `Modules/Integrations/FluentCart/Http/Controllers/`, `Modules/Integrations/FluentPlayer/Http/Controllers/`. Pro adds more in `app/Http/Controllers/` and `app/Modules/*/Http/Controllers/`.

## Layering

- Controllers: validate input, call service, shape response. NO business logic, NO `$wpdb` calls, NO transactions.
- Services: business logic, transactions, cross-model writes, emit `do_action` for side-effects.
- Models: persistence, scopes, accessors, relationships.

**Existing layering is clean**: 0 direct `$wpdb->query/get_var/get_results/get_col/get_row` callsites across `app/Http/Controllers/` and `app/Services/`. Models exclusively use the WPFluent ORM. Keep it that way.

Note: pro has 6 direct `$wpdb` callsites (`Modules/Followers/FollowerHelper.php`, `Modules/LeaderBoard/Services/LeaderBoardHelper.php`, `Services/ProfileFieldsService.php`) — all are guarded migration / schema-introspection patterns, not application-layer CRUD.

## Base controller

`app/Http/Controllers/Controller.php` (free) is abstract, extends `FluentCommunity\Framework\Http\Controller`. Provides:
- `getUser($throwException=false)` — returns logged-in user model (line 15)
- `getUserId()` (line 26)
- `getXProfile()` (line 34)

`sendSuccess`, `sendError`, `validate` are inherited from the framework base. Controllers should use them.

Pro `app/Http/Controllers/Controller.php` extends free's `Controller` (`extends FluentCommunity\App\Http\Controllers\Controller as BaseController` at L6).

## Validation pattern

- `$this->validate($data, $rules, $messages)` — throws `ValidationException` (422) on failure; validated data accessible via `$this->validated()`.
- Existing usage: `AdminController.php:267`, `ProfileController.php:244`, `SpaceController.php:51, 546, 655`, plus others (9 callsites total).
- Sample rules: `'title' => 'required'`, `'slug' => 'nullable|string'`.

## Request access patterns

- `$this->request->get('key', 'default')` — read a single param
- `$this->request->getSafe('key', 'sanitize_text_field', 'default')` — read + sanitize per callback
- `$this->request->all()` — full payload (use sparingly — MASS ASSIGN risk if passed to model)
- `Arr::get($data, 'key', 'default')` — for already-extracted arrays

Avoid `$_REQUEST` / `$_POST` direct access in controllers. Free has no wholesale superglobal assignment in controllers (verified).

## Mass-assign discipline

- `Model::create($request->all())` is banned. Extract validated fields explicitly:

```php
$data = $this->validate($request->all(), [
    'title' => 'required|string',
    'slug' => 'nullable|string',
]);
Space::create([
    'title' => sanitize_text_field($data['title']),
    'slug' => sanitize_title($data['slug'] ?? ''),
]);
```

- Same for `->fill()`, `->update()`, `->save()` with raw request data.

## Transactions

- Multi-row writes that must be atomic: `DB::transaction(function () { ... })` at the SERVICE layer.
- Free currently has zero `DB::transaction` callsites. Booking creation, space creation, feed publishing — all multi-row but not wrapped. Introducing a transaction is a significant design decision — surface to the user before adding.
- Don't catch the exception inside the transaction closure (it commits half the work). Let it bubble.

## Idempotency

- Async chunked jobs (the `as_schedule_single_action` pattern in `EmailNotificationHandler` — e.g., comment notification fan-out chunked by `last_send_user_id` pagination): every retry must be idempotent. Use:
  - Pagination cursors stored on the booking / feed / digest record
  - `wp_cache` short-TTL guards if a chunk is already being processed
- Webhooks (pro `Modules/Webhooks/WebhookModule.php`): incoming requests dedupe by `webhook` key transient (5min) for rate-limit, but no idempotency key on the action payload — if the request creates a user (`wp_insert_user`), a replay creates duplicates unless the email lookup matches an existing one. When editing this module, audit replay-safety.

## Response shape

- Use `$this->sendSuccess([...])` or `$this->sendError([...], $status)`.
- Some legacy controllers return arrays directly — framework converts. New code → prefer `sendSuccess`/`sendError` for consistency.
- Pro extends free's `Controller` base — same conventions apply.

## Event emission

- Side-effects go through `do_action('fluent_community/<noun>/<verb>', $args)`. Slash convention dominates (447 hits) — use it. Scheduler hooks use underscore (Action Scheduler convention).
- Don't emit a hook AND directly call another service for the same effect — listeners get called twice or in wrong order.
- **Event dedup** — Eloquent lifecycle hooks (`creating` / `deleting` used in `BaseSpace`, `Comment`, `Feed`, `Media`, `Notification`, `NotificationSubscriber`, `NotificationSubscription`, `Reaction`, `SpaceGroup`, `Term`) firing AND a manual `do_action('fluent_community/feed/created', ...)` for the same conceptual event is a duplicate. Pick one path. Two paths = listeners fire twice = double email, double notification.

## DRY across layers

- Before adding a helper, grep both repos for the same logic. Free has 18 services with overlapping responsibilities (`FeedsHelper`, `ProfileHelper`, `UploadHelper`). Pro re-implements logic across `Modules/Followers/FollowerHelper.php`, `Modules/LeaderBoard/Services/LeaderBoardHelper.php`, etc.
- Pro extending a free helper is preferred over duplicating it. Pro can call into free directly because of 199 cross-repo namespace references in pro.
- Three nearly-identical blocks across services = extract. Two is fine. Don't pre-abstract.

## Column / field name typos

- `.get('user_id')` returns null silently if the column doesn't exist. Cross-reference every property access against the model's actual columns.
- Models with multi-type tables (`fcom_meta`, `fcom_spaces`, `fcom_notification_users`) — see `models-migrations.md`. Always filter by `type` / `object_type`.

## REST input → service args

- Service method signature names match what the controller passes. Drift (`$spaceId` vs `$space_id`) eventually becomes a contract bug across repos.
- Pro callers invoke free services with `[ServiceClass::class, 'method']`. Signature change = breaks pro silently (199 cross-repo references — grep before changing).

## Self-audit

For each controller/service change:
1. Controller does no business logic, no `$wpdb`, no transactions?
2. Service opens transactions for multi-row atomic writes (if introducing)?
3. Mass-assign goes through explicit field extraction?
4. Idempotency story for async chunked jobs / webhooks?
5. Response uses `sendSuccess`/`sendError` (or matches the file's existing convention)?
6. No duplicate event emission (Eloquent lifecycle + manual `do_action` for same conceptual event)?
7. No copy-paste of existing helper — grep both repos before adding logic?
8. Service args match every caller (free + pro)?
