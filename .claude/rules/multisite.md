# multisite — blog context

Applies to multisite-aware code paths.

## switch_to_blog pairing

- Every `switch_to_blog($id)` pairs with `restore_current_blog()` in a `try/finally`,
  so context restores even on exception. An unrestored switch leaks the wrong site's
  context into the rest of the request.

```php
switch_to_blog( $blogId );
try { /* work scoped to $blogId */ } finally { restore_current_blog(); }
```

## Option scope

- Network-wide → `get_site_option` / `update_site_option`. Per-site → `get_option` /
  `update_option`. Mixing them leaks across sites or re-prompts on every blog.

## Table prefix

- `$wpdb->prefix` is the current site (changes after `switch_to_blog`);
  `$wpdb->base_prefix` is the network. Per-site tables use `prefix`; use
  `$wpdb->get_blog_prefix($id)` for cross-blog work.

## Cache / cron

- Cache keys are per-site: include `get_current_blog_id()` or a scoped group;
  `set_site_transient` only for network-wide. Action Scheduler runs per-site.

**Enforced by** scanner rule 12 (`switch_to_blog` with no `restore_current_blog` in the file).
