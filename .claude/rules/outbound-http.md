# outbound-http — server-side HTTP / SSRF

Applies to `wp_remote_*` / `wp_safe_remote_*` callsites and any fetch of a
user-influenceable URL.

## Timeout + bounds

- Every `wp_remote_*` sets a `timeout` (and a bound `redirection`). No unbounded calls
  — WP's default is 5s, set it explicitly.

## SSRF

- Prefer `wp_safe_remote_*` when the URL is user-influenced — but it is NOT a full SSRF
  defense. For user-supplied URLs also:
  - reject non-`http(s)` schemes;
  - DNS-resolve the host and reject private/loopback ranges (`127.0.0.0/8`,
    `169.254.169.254`, RFC1918, `::1`, `fc00::/7`);
  - allowlist known integration hosts where possible;
  - cap response body size + content-type.
- Never reflect the raw response body/headers into logs, admin notices, or REST
  responses.

## OAuth / redirects (forward-looking)

- OAuth `state`: `wp_generate_password(32, false, false)`, stored server-side, compared
  with `hash_equals()`. Post-callback redirects use `wp_safe_redirect()` + host
  allowlist. See [fix-patterns.md](fix-patterns.md).

**Enforced by** scanner rule 9 (`wp_remote_*` with no `timeout` anywhere in the file).
