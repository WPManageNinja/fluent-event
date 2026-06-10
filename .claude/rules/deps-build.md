# deps-build — assets + dependencies

Applies to asset enqueues and build / dependency config.

## Asset version

- `wp_enqueue_script` / `wp_enqueue_style` uses `FLUENT_EVENTS_PLUGIN_VERSION` as the
  version arg — never `time()` / `rand()` (defeats browser + CDN caching on every load).
  Bump the constant on release.

## Dependencies

- Pin runtime deps (no `^` / `~` auto-bump). Verify a new dep is maintained; document why.
- Update the lockfile in the same commit as `package.json` / `composer.json`.
- Removing a dep: grep for every import first.

## CDN / SRI

- A new `<script src="https://cdn…">` needs `integrity="sha384-…" crossorigin`. Prefer
  self-hosting — it survives a CDN compromise.

## Enqueue dedup

- Register a shared library once with a stable handle; dependents declare it as a
  dependency rather than re-registering.

**Enforced by** scanner rule 14 (`time()` used as the asset version).
