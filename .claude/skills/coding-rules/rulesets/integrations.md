# integrations.md — webhooks, cloud storage, external integrations

Triggered when editing pro `app/Modules/Webhooks/**`, `app/Modules/CloudStorage/**`, `app/Modules/Integrations/Paymattic/**`, `app/Modules/Followers/**`, OAuth callback handlers, gateway integrations, or any code involving 3rd-party API credentials / webhook verification.

This replaces the `payments.md` ruleset from fluent-booking — FluentCommunity has NO native payment storage. Payments are delegated to Paymattic; payment state arrives via `wppayform/*` hooks.

## Free has no payments / money tables

Confirmed: zero `DECIMAL`/`FLOAT`/`DOUBLE` columns in `database/Migrations/`. Pro has none either. Money math is entirely Paymattic's domain.

If introducing the FIRST native money column / payment storage in either repo, surface to the user — significant design event.

## Pro `Modules/Webhooks/` — security-critical surface

`app/Modules/Webhooks/WebhookModel.php` + `WebhookModule.php`. Listens to free hook `fluent_community/portal_action_incoming_webhook` (`WebhookModule.php:15`).

What it does:
- Accepts inbound HTTP request with `?webhook=<key>` query param
- Rate-limit: transient-based, 20 req/IP/5min (`WebhookModule.php:32-37`)
- Key lookup; `hash_equals` shared-key compare (`:42`)
- Creates users via `wp_insert_user` (`:46-97`) + enrolls into spaces/courses from `$_POST`
- Default password: `wp_generate_password(8, false)` (`:147`) — only 8 chars, no special

⚠ **Known concerns** (do NOT regress when editing):

1. **No HMAC signature on body.** Only a static shared key passed via query string. Anyone with the key can replay or forge requests. When extending, consider adding HMAC verification:

```php
$expected = hash_hmac('sha256', $rawBody, $webhookSecret);
$received = $_SERVER['HTTP_X_FCOM_SIGNATURE'] ?? '';
if (!hash_equals($expected, $received)) {
    return rest_ensure_response(['error' => 'signature_mismatch'], 401);
}
```

2. **User creation surface is public.** A leaked webhook key = mass user creation. Audit any new field added to the `$_POST` payload that gets passed to `wp_insert_user`.

3. **Idempotency missing.** Replaying the same request creates new users unless email already exists. Add a request-ID / nonce-replay guard if introducing destructive endpoints (e.g., remove user from space) under this module.

4. **Password strength.** `wp_generate_password(8, false)` is weak. Prefer `wp_generate_password(20, true, true)` for new flows OR rely on email-confirmation password set.

## Pro `Modules/CloudStorage/` — outbound HTTP + signed URLs

Drivers: `S3/S3.php` (AWS — custom SigV4 implementation, ~2000 lines vendored), `S3/` (also Cloudflare R2 via S3 API), `BunnyCDN/BunnyCdnDriver.php`.

Hooks: `fluent_community/media_upload_data` filter, `fluent_community/delete_remote_media_s3`, `fluent_community/media_signed_public_url_s3`.

⚠ **Known concerns:**

1. **`wp_remote_request` (NOT `wp_safe_remote_*`)** is used in `BunnyCDN/BunnyCdnDriver.php:127,153,180`. `wp_safe_remote_*` runs `wp_http_validate_url()` which blocks loopback / private hosts at the URL-validation layer (see `outbound-http.md`). When extending CloudStorage to a new provider, prefer `wp_safe_remote_*` OR add explicit private-IP rejection.
2. **No URL scheme validation** on stored endpoint URLs before issuing requests. Admin-stored endpoints CAN be `http://` or weird schemes — validate at save time.
3. **S3 SigV4 hash_hmac** at `S3/S3.php:1231-2003` is legitimate (AWS signing algorithm). Don't replace with `hash_equals` — different purpose.

## Pro `Modules/Integrations/Paymattic/`

`Bootstrap.php`, `RemoveFromSpaceAction.php`. Listens to:
- `wppayform/after_payment_status_change` — `RemoveFromSpaceAction.php:14`
- `wppayform/subscription_payment_canceled` — `RemoveFromSpaceAction.php:15`

Payment method strings checked: `stripe`, `square` (`RemoveFromSpaceAction.php:89,93`). Paymattic owns the gateway credentials and webhook signature verification — FluentCommunity Pro is downstream of Paymattic events.

Rules:
- Don't add gateway-specific logic here. If a new gateway is needed, extend Paymattic.
- Don't read raw payment data from Paymattic's tables directly — use Paymattic's API / hooks.
- When a payment-state transition triggers removal from a space, it must be idempotent (Paymattic may fire the hook twice — common gateway-webhook retry).

## OAuth callback (forward-looking)

No OAuth in current free OR pro. When introducing:

1. Generate `state` at initiation: `wp_generate_password(32, false, false)`.
2. Store server-side, bound to the initiating session (transient keyed by session ID).
3. On callback, compare with `hash_equals($stored, $received_state)`. Reject mismatch.
4. Missing state binding = CSRF account hijacking. Fix-pattern row: `OAuth callback`.
5. Refresh tokens: encrypted at rest (use `cipher` from `config/app.php`). Don't log. Don't expose in REST responses (except masked).

## Webhook signature (general rule)

For incoming webhooks from external providers (when added):

```php
$rawBody = file_get_contents('php://input');
$expected = hash_hmac('sha256', $rawBody, $provider_secret);
$received = $_SERVER['HTTP_X_PROVIDER_SIGNATURE'] ?? '';
if (!hash_equals($expected, $received)) {
    return new \WP_Error('invalid_signature', 'Signature mismatch', ['status' => 401]);
}
```

- Sign the RAW BODY, not the parsed JSON (re-serialization can break the signature).
- `hash_equals` — never `===` / `==`.
- Verify BEFORE doing any state mutation.

Fix-pattern row: `Webhook signature`.

## Open redirect

- Post-OAuth / post-webhook redirects use `wp_safe_redirect()`, not `wp_redirect()`. Allowlist host. Fix-pattern row: `Open redirect`.

## Token storage

- API keys, OAuth refresh tokens, webhook secrets stored in `wp_options` or a dedicated table.
- READ endpoints exposing these tokens are sensitive — lower-trust users must NOT be able to read.
- Never log tokens. Never include in REST responses (except masked).
- Existing pro `__fluent_community_pro_license` option in `boot/app.php:13` — only this constant string is part of the pro license check.

## SSRF reflection avoidance

Response body / headers / status from outbound HTTP must NOT be copied verbatim into logs / admin notices / REST responses. Blind SSRF → exfiltration. Fix-pattern row: `SSRF reflection`.

## Self-audit

For each integration-related change:
1. Webhook handler verifies HMAC signature on raw body (if extending to support it)?
2. CloudStorage uses `wp_safe_remote_*` (or explicit URL validation) before outbound request?
3. New user-creation surface (Webhooks) — idempotency story?
4. OAuth `state` parameter generated, stored, and compared with `hash_equals`?
5. Tokens never logged or unmasked in responses?
6. `wp_safe_redirect` (not `wp_redirect`) for post-callback redirects?
7. Response body / headers NOT reflected into logs or notices?
8. Payment-state hooks (Paymattic) handled idempotently?
