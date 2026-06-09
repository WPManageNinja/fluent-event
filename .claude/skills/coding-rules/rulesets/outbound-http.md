# outbound-http.md — outbound HTTP / SSRF / OAuth

Triggered when editing existing callsites or adding new ones for `wp_remote_get`, `wp_remote_post`, `wp_remote_request`, `wp_safe_remote_*`, OAuth callback handlers, or any code fetching a user-influenceable URL.

## Existing callsites

**Free:**
- `app/Hooks/CLI/Commands.php:171` — `wp_remote_get($baseUrl . $page, ...)` — WP-CLI command (admin-trusted context, lower risk)
- `app/Services/OnboardingService.php:279` — `wp_remote_post($url, ...)` — onboarding flow; verify URL source
- `app/Services/RemoteUrlParser.php:143` — `wp_safe_remote_get($url, $args)` — URL preview / unfurl. User-supplied URL — HIGH SSRF risk surface

**Pro:**
- `app/Modules/CloudStorage/BunnyCDN/BunnyCdnDriver.php:127, 153, 180` — `wp_remote_request` (NOT safe variant)
- `app/Modules/CloudStorage/S3/S3.php` — vendored S3 SDK (~2000 lines) uses its own cURL-style requests + AWS SigV4
- `app/Modules/Giphy/Services/GiphyService.php:41` — `wp_safe_remote_get`

## `wp_safe_remote_*` is NOT a full SSRF defense

It sets `reject_unsafe_urls => true`, which runs `wp_http_validate_url()` — rejects URLs without scheme, non-`http(s)` schemes, and disallowed redirects. **But raw private-IP requests (e.g., `http://127.0.0.1/`, `http://169.254.169.254/`) may still go through** depending on `WP_Http::block_request()` and the site's `WP_HTTP_BLOCK_EXTERNAL` config.

Prefer `wp_safe_remote_*` over `wp_remote_*` when URL is user-influenceable, AND add the explicit private-IP check below.

## SSRF — private-network defense

User-controlled URL reaching `wp_remote_*` / `wp_safe_remote_*` must:

1. Parse URL → reject non-`http(s)` scheme.
2. Resolve host → reject:
   - `127.0.0.0/8` (loopback)
   - `169.254.169.254` (cloud metadata)
   - RFC1918: `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`
   - `::1`, `fc00::/7` (IPv6)
   - Hostnames resolving to any of the above after DNS lookup
3. Allowlist known integration hosts (Giphy, GitHub, integration providers) where possible.

Fix-pattern row: `SSRF`.

## Timeout + redirect bounds

- `wp_remote_get($url, ['timeout' => 10, 'redirection' => 3])`. Never unbounded. WP default timeout is 5s — explicit is safer.
- For URL parsing / preview (`RemoteUrlParser`): cap response body size. Streaming layer that aborts past a threshold preferred for large-body endpoints.
- `RemoteUrlParser.php` reads response body via `wp_remote_retrieve_body` (`:154`) — currently no size cap.

## Response reflection avoidance

- Response body / headers / status must NOT be copied verbatim into:
  - `error_log`
  - Admin notices
  - REST responses (URL preview returns parsed metadata, NOT raw response)
  - Action notes / audit trail
- Log only: status code + (sanitized) URL. Fix-pattern row: `SSRF reflection`.

## OAuth callback (forward-looking)

No OAuth in current free OR pro. When introducing:

1. Generate `state` at initiation: `wp_generate_password(32, false, false)`.
2. Store server-side, bound to the initiating session (transient keyed by session ID).
3. On callback, compare with `hash_equals($stored, $received_state)`. Reject mismatch.
4. Missing state binding = CSRF account hijacking. Fix-pattern row: `OAuth callback`.

## Token storage

- OAuth refresh tokens, integration API keys: store in `wp_options` (or a dedicated table) with appropriate access-gating on read.
- READ endpoints exposing these tokens are sensitive — admin-only.
- Never log tokens. Never include in REST responses (except masked).

## Open redirect

- Post-OAuth / post-callback redirects use `wp_safe_redirect()`, not `wp_redirect()`. Allowlist host. Fix-pattern row: `Open redirect`.

## CDN / external script loads

- Adding `<script src="https://cdn.example.com/lib.js">` requires SRI: `integrity="sha384-…" crossorigin="anonymous"`. Fix-pattern row: `SRI`.
- Self-hosting is preferred — survives CDN compromise.

## Pro CloudStorage specifics

- `wp_remote_request` (NOT safe variant) used in BunnyCDN driver. When extending or adding a new CDN driver:
  - Validate scheme (`https://` only — `http://` over the open internet for CDN URLs is wrong)
  - Set `timeout`
  - Add private-IP rejection
  - OR migrate to `wp_safe_remote_request`
- See `integrations.md` for the full CloudStorage rules.

## RemoteUrlParser specifics

`app/Services/RemoteUrlParser.php` fetches user-supplied URLs for link preview / unfurl. This is the HIGHEST SSRF risk surface in free. When editing:
- DNS-resolve the host and reject private IPs before fetching
- Cap response body size
- Cap fetch time strictly (5s suggested)
- Whitelist Content-Type (text/html, application/json) before processing

## Self-audit

For each outbound-HTTP change:
1. URL scheme validated (`http` / `https` only)?
2. Private IP rejection on resolved host?
3. Allowlist for known integrations (where applicable)?
4. Timeout + redirect bounds set?
5. Response body NOT reflected into logs / responses / notices?
6. Response body size capped (for user-supplied URLs)?
7. OAuth `state` generated, stored, compared with `hash_equals` (if introducing OAuth)?
8. Tokens never logged or unmasked in responses?
9. `wp_safe_redirect` for post-callback redirects?
10. Prefer `wp_safe_remote_*` over `wp_remote_*` for user-influenced URLs?
