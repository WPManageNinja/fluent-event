# multisite.md — multisite-aware code paths

Triggered when editing `database/DBMigrator.php`, new `switch_to_blog` / `is_multisite` / `site_option` / `$wpdb->base_prefix` callsite, new `wp_cache_*` / `set_transient` on per-site data.

## Activation / DB setup

- On multisite network activation, every site needs the migration. `register_activation_hook` fires once for the activating site.
- `database/DBMigrator.php` runs from `boot/app.php:60-64` via `fluent_community/portal_render_for_user` version-bump check.
- Verify whether the existing `DBMigrator::run()` iterates blogs. If not, multisite installs may need a `wpmu_new_blog` hook for future sites + a one-time loop on activation.

## `switch_to_blog` / `restore_current_blog` pairing

- Every `switch_to_blog($id)` MUST be paired with `restore_current_blog()` in the same control flow. Even on exception:

```php
switch_to_blog($id);
try {
    // ... work
} finally {
    restore_current_blog();
}
```

- Forgotten `restore_current_blog()` leaks blog context across requests in some setups = data shown from wrong site.

## `site_option` vs `option`

- Network-wide setting: `get_site_option` / `update_site_option` / `delete_site_option`.
- Per-site setting: `get_option` / `update_option` / `delete_option`.
- Mixing them = wrong scope. Network-wide license stored per-site = re-prompt on every blog. Per-site setting stored network-wide = leaks across blogs.
- License check (pro `boot/app.php:13` reads `__fluent_community_pro_license`) — verify it uses the correct scope for the deployment.

## `$wpdb->base_prefix` vs `$wpdb->prefix`

- `$wpdb->prefix` = current site's prefix (changes after `switch_to_blog`).
- `$wpdb->base_prefix` = network's base prefix (constant).
- Plugin tables use `$wpdb->prefix . 'fcom_*'` (per-site). Network-wide tables (rare) use `$wpdb->base_prefix . 'fcom_*'`.
- `SpaceController.php:683-685` uses `$wpdb->get_blog_prefix($blogId)` — proper multisite-aware prefix accessor. Reuse this pattern when working with cross-blog data.
- Mixing them = queries hit the wrong table.

## Caching

- `wp_cache_*` keys are NOT per-site by default in some object-cache backends. Either:
  - Use `wp_cache_*` with explicit `$group` named per-site, OR
  - Prefix the cache key with `get_current_blog_id()`.
- Same for `set_transient` — multisite-aware transients use `set_site_transient` for network-wide and the standard `set_transient` switches to the current site context.

## Capability checks

- `current_user_can('manage_options')` — on multisite, this is per-site admin. Network admin uses `is_super_admin()` or `current_user_can('manage_network_options')`.
- `Helper::isSiteAdmin()` — check the implementation for multisite awareness when extending.

## Cron / schedulers

- Action Scheduler runs per-site. If a task is global, schedule on `wpmu_new_blog` for each site OR centralize on the main site.
- Existing `fluent_community_scheduled_hour_jobs` / `fluent_community_daily_jobs` register per-site at activation. For network-wide tasks, plan differently.

## Self-audit

For multisite-touching changes:
1. Every `switch_to_blog` has a matched `restore_current_blog`?
2. Network setting → `site_option`, per-site → `option`?
3. `$wpdb->prefix` (per-site) vs `$wpdb->base_prefix` (network) — chosen correctly for the table?
4. Cache keys include `get_current_blog_id()` (or use scoped group)?
5. Capability check matches the multisite-vs-network scope?
6. Activation iterates all blogs for DB migration (or is documented as single-site only)?
