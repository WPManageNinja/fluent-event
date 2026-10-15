# FluentEvent tests

Three tiers, following the Fluent suite's layout (fluent-booking / fluent-affiliate / fcom).

| Tier | Runner | Where | Needs |
|---|---|---|---|
| PHP unit | PHPUnit 9.6 + Brain\Monkey + Mockery | `tests/php/Unit/` | nothing — bare checkout |
| PHP integration | same, against real WordPress | `tests/php/Integration/` | this site's WordPress + DB |
| JS unit | Vitest | `tests/unit/` | `npm install` |

```bash
composer install -d tests          # PHP tooling into tests/vendor (gitignored)
npm install

npm test                  # JS (Vitest)
npm run test:php          # PHP unit
npm run test:integration  # PHP integration — writes to this site's DB, rolled back
npm run test:all          # JS + PHP unit; the two that need nothing but a checkout
```

Vitest is pinned at 0.34.6 because Vite is 4 and Vitest 1+ needs Vite 5. Its audit
advisories (vitest UI server, tinypool) are dev-only and clear with that upgrade.

PHP tooling has its own `tests/composer.json` rather than the root's `require-dev`: the root
post-update script runs PHP-Scoper over every third-party package in `vendor/`, and
`vendor/composer` is tracked. Not the siblings' layout, on purpose.

## What each tier can catch

**Unit** — no database. Routes resolve and every write route has its own policy method;
the permission matrix; hook wiring (every callback exists, every queued job has a listener,
reminders cancel before cleanup); model contracts (`$fillable`, statuses, `defaultMeta()`);
request shaping; dates; mail header building; the portal's meeting-link rule; and
`tests/lint/raw-sql-prefix.php`, which statically catches an unprefixed or hardcoded-`wp_`
table inside `selectRaw()` and friends, heredocs and variables passed in included. The test
table prefix is `wptest_`, never `wp_`.

URL sanitizing is deliberately not tested here: the `esc_url_raw()` stub has no protocol
allowlist, so a test against it would only test the stub.

**Integration** — the controllers, which hold most of the logic (a one-caller write stays in
its controller). Requests go through the real router, policies, validator and database.

## Integration safety

Every test runs in a transaction rolled back in `tearDown()`, which then fails the test if
`wp_fe_meta`'s row count changed. That is what lets it point at a working dev site. So:

- **No DDL, no TRUNCATE, no COMMIT.** DDL implicitly commits in MySQL and makes every later
  write permanent. `DB::transaction()` is fine — the harness opens the outer transaction on
  the framework connection, so inner ones become savepoints (`HarnessGuardTest` proves it).
- **Mail and outbound HTTP are blocked**, and captured for assertions (`$this->sentMail`).
- **The Redis object cache is switched off** (`WP_REDIS_DISABLED`); the bootstrap refuses
  to run if a persistent cache is still active, since rolled-back rows would survive in it.
- Static caches (`PermissionManager::$cache`, `Settings::$stored`) and the framework's
  singleton `Request` are reset per test / per request.

## Conventions

- One behaviour per test, named as a sentence.
- A comment only where the *why* isn't obvious.
- A test that pins a known bug says `KNOWN ISSUE` and what it should assert once fixed.
