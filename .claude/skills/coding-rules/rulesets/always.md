# always.md — universal rules for every fired invocation

Applies to every code edit the skill fires for. Each rule references a row in `.claude/feature-review/fix-patterns.md` — use that table when picking the concrete code shape.

## Input handling (PHP)

- Never `$data = $_REQUEST` / `$_POST` / `$_GET` wholesale. Extract each field: `$x = sanitize_text_field(wp_unslash($_POST['x'] ?? ''))`. Fix-pattern row: `Superglobal wholesale`.
- Order is fixed for raw superglobal access: `wp_unslash` → validate → sanitize. Never sanitize before unslash.
- **REST controllers use a different pattern**: data comes through `$this->request->get(...)`, `$this->request->getSafe(...)` (auto-sanitizes via callback), or `Arr::get($data, ...)`. The framework's `Request` object is pre-unslashed. Sanitize per field — `wp_unslash` is not needed when source is the framework `Request` object. `wp_unslash` rule applies to raw superglobal access only.
- Validate input shape via `$this->validate($data, $rules, $messages)` at the controller entry. Inherited from `FluentCommunity\Framework\Http\Controller`. Throws `ValidationException` (422) on failure.
- Sanitize helpers per CLAUDE.md: `sanitize_text_field`, `intval`, `sanitize_url`, `wp_kses_post`. Use `wp_kses_post` (not `esc_html`) for rich HTML / markdown content.

## SQL

- Every dynamic query goes through `$wpdb->prepare('… %d %s', $id, $str)`. No exceptions.
- `LIKE`: `'%' . $wpdb->esc_like($term) . '%'` then prepare. Fix-pattern row: `SQL injection`.
- Table / column names interpolated into SQL must come from an allowlist or use `esc_sql($table)` (pattern in pro `ProfileFieldsService.php:272-279`). Never user-controlled.
- No raw `$wpdb->query(...)` with concatenated user input. Free has zero direct `$wpdb` CRUD in controllers/services (verified).

## Mass assignment

- Explicit `$fillable` on every persisted model. Never `->fill($request->all())` / `->create($_POST)`. Fix-pattern row: `Mass assignment`.
- 16 of 21 free models have `$fillable`. Models without `$fillable` (`DynamicModel`, `Model`, `SidebarLink`, `Space`, `User`) — verify why before adding setter paths through them. `Space` extends `BaseSpace` (has `$fillable`); `User` extends WP user wrapper.
- `Comment`, `Feed`, `XProfile` are the only models with `$casts` today — pattern is new for the codebase.
- Adding a column to `$fillable` is a security decision — explicitly justify why it's safe to be mass-assigned.

## Output / XSS (PHP)

- `esc_html` for text, `esc_attr` for attributes, `esc_url` for URLs, `wp_kses_post` for rich HTML / markdown rendering.
- Translation helpers: `esc_html__()` / `esc_attr__()` / `esc_html_e()` / `esc_attr_e()`. Never raw `__()` / `_e()` on user-influenceable strings. Translators can return arbitrary HTML. Fix-pattern row: `XSS after translation`.
- Save-time sanitizer is NOT render-time safe. Always re-sanitize at the FINAL sink (REST response, email body, portal template).

## Object injection

- Both raw `unserialize()` AND `maybe_unserialize()` are banned. The only sanctioned helper is **`Utility::safeUnserialize($data)`** at `app/Functions/Utility.php:1207` — wraps `unserialize` with `is_serialized()` guard + `['allowed_classes' => false]`. Already used by 10+ free models (`Feed`, `Term`, `Meta`, `XProfile`, `SpaceGroup`, `SpaceUserPivot`, `BaseSpace`, `Comment`, `Notification`, `Media`).
- For JSON-shaped data, prefer `json_decode()` over any unserialize variant.
- Fix-pattern row: `Object injection`.

## Dynamic code execution — BANNED

- No `eval()`, no `new Function()`, no `create_function()`, no dynamic `include` from variable.
- Free has zero `eval` / `new Function` callsites in `src/` (excluding vendored `libs/`). Keep it that way.

## Crypto / randomness

- Tokens / nonces / one-time codes: `wp_generate_password(32, false, false)` or `bin2hex(random_bytes(16))`. Never `mt_rand`, `rand`, `uniqid`. Fix-pattern row: `Weak randomness`.
- Pro `WebhookModule.php:147` uses `wp_generate_password(8, false)` for new user passwords — note: only 8 chars, no special. When extending, prefer longer or rely on the email confirmation flow.
- Signature compare: `hash_equals($a, $b)`. Never `===` / `==`. Fix-pattern row: `Constant-time compare`.
- Pro `WebhookModule.php:42` uses `hash_equals` for shared-key compare. Pro `Modules/CloudStorage/S3/S3.php` uses `hash_hmac` for AWS SigV4 (legitimate).
- No MD5 / SHA1 for security operations.

## Auth baseline (FluentCommunity-specific)

Authorization is role-based, NOT capability-based. There is NO plugin-specific capability layer (different from FluentBooking).

- Site-admin check: `Helper::isSiteAdmin()` at `app/Services/Helper.php:156`.
- Portal access check: `Helper::canAccessPortal($userId)` at `app/Services/Helper.php:424`.
- Current XProfile: `Helper::getCurrentProfile()` at `app/Services/Helper.php:502`.
- Community-admin: `User::hasCommunityAdminAccess()` at `app/Models/User.php:384`.
- Space-manage: `User::hasSpaceManageAccess()` at `User.php:403`.
- Space role: `User::getSpaceRole($space)` at `User.php:410`. Roles: `admin`, `moderator`, `member`, `pending`, `course_admin`, `course_creator`.
- Permission map: `User::getRolePermissions()` at `User.php:467-498` — `super_admin`, `community_admin`, `community_moderator`, `course_creator`, `course_admin`, `delete_any_feed`, `edit_any_feed`, `delete_any_comment`, `edit_any_comment`, `read`.
- Per-space permission: `$user->verifySpacePermission('can_add_member', $space)` at `User.php:636`.

When adding authorization, reuse these helpers. Never reinvent.

## Architecture (CLAUDE.md)

- REST namespace `fluent-community/v2` (from `config/app.php`). No override constant defined currently.
- Hook prefix: `fluent_community/` (slash form is dominant — 361 distinct hook names. Underscore hook names exist only for scheduler cadences + the daily digest single-action: `fluent_community_scheduled_hour_jobs`, `fluent_community_daily_jobs`, `fluent_community_send_daily_digest`, `fluent_community_send_daily_digest_init`, plus pro's `fluent_community_sync_user_points`).
- Text domain: `fluent-community` (pro uses `fluent-community-pro`).
- Table prefix: `fcom_*`.
- Free namespace `FluentCommunity\`, pro `FluentCommunityPro\`. Pro depends on parent free; never the reverse.
- PSR-4 autoload for both repos (free `composer.json`: `FluentCommunity\App\ → app/`). New PHP classes must follow PSR-4 — namespace mirrors directory path, one class per file, filename matches class name.
- Indentation: 4 spaces for PHP and Vue (`.vue` SFC `<script>` + `<template>` blocks), 2 spaces for CSS / SCSS. No tabs.
- No comments unless required. Default to none. Add one only when the WHY is non-obvious (hidden constraint, workaround for a specific bug, subtle invariant). Don't explain WHAT the code does — well-named identifiers do that. No PR-stage commentary (`// added for X`, `// fixes #123`).
- Variable names: minimum 3 letters. No single-letter or two-letter names. Loop counters use `$i` / `$j` only inside tight numeric loops; everything else gets a descriptive name (`$user`, `$feedId`, `$idx`, not `$u` / `$id` / `$x`).
- Vue: **Options API only** per CLAUDE.md. Composition API is forbidden by team convention. 292/296 `.vue` files are Options API (only `MilkDownEditor/Tooltip.vue` uses `<script setup>`).
- REST X-HTTP-Method-Override pattern: PUT/PATCH/DELETE sent as POST with `X-HTTP-Method-Override` header (see `src/Bits/Rest.js`). Don't bypass.

## Privacy

- No PII in `error_log`, URL query strings, admin notices, log lines.
- Strip response body / headers from any logged outbound HTTP call (SSRF reflection avoidance).
- Existing portal `localStorage` keys: `fcom_pref`, `fcom_global_storage`, `fcom_feed_layout`, `fcom_layout_by_context`, `fcom_feed_sort`, `fcom_sort_by_context`, `fcom_cpf_reopen`, plus Pinia persistedstate auto-keys. All UI prefs, no PII. New key → match the no-PII / no-token profile.

## Conditional-logic discipline

- `else if ('some_string')` is always truthy in PHP — never do this. Variable comparison must use `===` / `==` with explicit operand.
- No `if ($x = someValue())` when comparison was intended.
- `&&` binds tighter than `||`. Parenthesize compound auth checks: `(A && B) || C` is NOT the same as `A && (B || C)`.

## Concurrency

- Status transitions on `Feed`, `Comment`, etc. — when adding a CAS-style update, use atomic `UPDATE … SET status = 'new' WHERE id = ? AND status = 'old'`, check affected rows. Fix-pattern row: `Race / atomic state`.
- Status enums are NOT centralized today (no `$validStatuses` const). Real values seen: `Feed.status` = `published`/`pending`; `Comment.status` = `published`; `Space pivot.status` = `active`/`pending`; `XProfile.status` = `active`; `User.status` = `active`.
- When introducing a new status, document it in the model AND keep CAS pairs explicit.
- Find-or-create needs a unique DB index. Two concurrent calls will both miss without it.

## Transactions

- Free currently has zero `DB::transaction` callsites. Multi-row writes that must be atomic open a transaction at the SERVICE layer — `DB::transaction(function () { ... })`. Adding one is a notable design decision; surface to user before adding.

## Systematic root-cause debugging

Bug fixes patch the ROOT cause, not the symptom. Reproduce → trace up the call stack → find the earliest point where bad state originated → fix THERE. Don't swallow errors in `try/catch`, don't guard at the symptom site, don't patch the consumer when the producer is wrong. Surface-only fix is acceptable only for unfixable third-party roots, with a comment naming the upstream issue + removal condition. Self-audit: state the root cause in one sentence + cite file:line where the root lives. If you can't, you're patching a symptom.

## Self-audit anchor

After every meaningful edit batch, mentally walk this file top to bottom against the just-changed code. Any rule that touches the diff but wasn't followed = self-audit finding.
