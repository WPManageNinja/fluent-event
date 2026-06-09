# fix-patterns — Hard-block primitives

## Object injection — `unserialize()` / `maybe_unserialize()`

Untrusted serialized input can instantiate arbitrary objects (POP-chain RCE).

- Prefer `json_encode` / `json_decode` for stored structured data.
- If PHP serialization is unavoidable, route through a sanctioned safe unserializer
  (e.g. an `App\Functions\Utility::safeUnserialize()` helper once it exists) — never
  bare `unserialize()` / `maybe_unserialize()` on external data.
- Enforced by scanner rule 5.

## eval / new Function — banned

`eval()` (PHP) and `new Function()` (JS) execute arbitrary code. Replace with a
registered handler map / data-driven dispatch.

- Enforced by scanner rule 6.

## Weak randomness for secrets

`mt_rand()` / `uniqid()` are predictable — never use them for tokens, OTPs, nonces,
keys, or salts.

- PHP: `wp_generate_password(32, false, false)` or `bin2hex(random_bytes(16))`.
- Enforced by scanner rule 10 (weak RNG near a token/secret variable).

## Constant-time compare + webhook signatures

Comparing an HMAC with `==` / `===` leaks timing. After `hash_hmac()`, verify with
`hash_equals()`.

```php
$expected = hash_hmac( 'sha256', $body, $secret );
if ( ! hash_equals( $expected, $received ) ) {
    // reject
}
```

- Enforced by scanner rule 11 (`hash_hmac` with no `hash_equals` in the file).
