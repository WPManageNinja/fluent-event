# models-migrations.md — models + DB schema

Triggered when editing `app/Models/**`, `database/Migrations/**`, `database/DBMigrator.php`, any model in `Modules/**/Model/**`, or any code adding a new query pattern.

## What's actually in the codebase

21 free models in `app/Models/`: `Activity`, `BaseSpace`, `Comment`, `Contact`, `DynamicModel`, `Feed`, `Media`, `Meta`, `Model` (base), `Notification`, `NotificationSubscriber`, `NotificationSubscription`, `Reaction`, `SidebarLink`, `Space`, `SpaceGroup`, `SpaceUserPivot`, `Term`, `User`, `UserMeta`, `XProfile`.

Plus module models: `Modules/Course/Model/Course.php`, `CourseTopic.php`, `CourseLesson.php`; `Modules/PushNotification/FnSubscriptionModel.php`; `Modules/Auth/Classes/Invitation.php`. Pro adds `Follow`, `Moderation`, plus extensions.

13 migrators in `database/Migrations/`: `FeedSpace`, `Feed`, `FeedReactions`, `FeedComments`, `FeedSpaceUser`, `Meta`, `Notifications`, `NotificationUser`, `UserActivities`, `Term`, `TermFeed`, `MediaArchive`, `XProfile`. Orchestrator: `database/DBMigrator.php`. NO module-specific DDL in free.

Pro has NO `database/Migrations/` folder. Schema work is INLINE via `dbDelta` (e.g., `Modules/Followers/FollowerHelper.php:132-144`) or runtime `ALTER TABLE` (`Services/ProfileFieldsService.php:272-279`).

Tables prefixed `fcom_*`. Real names seen: `fcom_posts` (Feed), `fcom_spaces`, `fcom_post_comments`, `fcom_post_reactions`, `fcom_space_user`, `fcom_meta`, `fcom_notifications`, `fcom_notification_users`, `fcom_user_activities`, `fcom_terms`, `fcom_term_feed`, `fcom_media`, `fcom_xprofile`.

## `$fillable`

- Always explicit for persisted models. Adding an entry is a security decision (see `always.md` and `backwards-compat.md`).
- Every column in `$fillable` must exist in the migration. Drift = silent ignore or runtime error.
- **Models WITHOUT `$fillable`** (5):
  - `DynamicModel.php` — base abstraction; no DDL surface; fine
  - `Model.php` — base class; fine
  - `SidebarLink.php` — extends `BaseSpace` and uses `addGlobalScope('sidebar_link')`. INHERITS `BaseSpace::$fillable`. Verify Sidebar-specific writes don't expose unintended `BaseSpace` columns.
  - `Space.php` — extends `BaseSpace`. Same inheritance story.
  - `User.php` — WP user wrapper with `$guarded = ['ID', 'user_pass']` — inverse allowlist. Anything else mass-assignable; audit any new WP user column adds.

## Multi-type table discipline (CRITICAL — per CLAUDE.md rule 7)

Three tables store multiple object types and rely on a column to disambiguate:

| Table | Discriminator column | Models | Global scope filter |
|---|---|---|---|
| `fcom_spaces` | `type` | `Space`, `SpaceGroup`, `SidebarLink` | each model adds `addGlobalScope` filtering by type |
| `fcom_notification_users` | `object_type` | `NotificationSubscriber`, `NotificationSubscription` | each adds an `object_type` global scope |
| `fcom_meta` | `object_type` | `Meta`, `UserMeta`, `Staff`-like in pro | global scope filters per type |

Rules:
- When reading from these tables WITHOUT going through a model, always include `WHERE type = ...` or `WHERE object_type = ...`.
- `withoutGlobalScopes()` strips the type filter — use only when intentional and explicitly comment why.
- New model on a multi-type table → MUST define a `static::boot() { static::addGlobalScope(...) }` filtering by the discriminator.

## `$casts`

- Currently used in 3 models only: `Comment`, `Feed`, `XProfile`. Pattern is new for this codebase.
- If you introduce `$casts`, match the column type exactly:
  - `INT` / `BIGINT` → `'integer'`
  - `TINYINT(1)` → `'boolean'`
  - `JSON` / `TEXT` storing JSON → `'array'` or `'json'`
  - `DATETIME` → `'datetime'`
- Cast mismatch causes silent data corruption.

## Column charset / collation

- New text columns: `utf8mb4_unicode_ci`. Member names + post content contain emoji.
- Migrations using `$wpdb->get_charset_collate()` for the table-level default is preferred (existing pattern in every migrator).

## Column length vs UI

- Vue form has `maxlength=500`? DB column needs to accept ≥ 500. Otherwise silent truncation on save.
- Walk every new form field's `maxlength` and verify the matching column length.

## Nullable

- Column nullable in migration must match PHP type hint / docblock.
- Cast / accessor returning `null` when the column is `NOT NULL` is dead code.

## Indexes

- Every new `WHERE` / `ORDER BY` / `JOIN` column needs an index unless the table is tiny.
- Hot tables: `fcom_posts` (Feed), `fcom_post_comments` (Comment), `fcom_notifications`, `fcom_meta`. New query patterns on these without an index will degrade portal performance for large communities.
- Composite `WHERE` (multiple columns AND'd) → composite index in the right column order.
- `LIKE 'prefix%'` is indexable; `LIKE '%suffix'` is not.

## Timestamps

- `$timestamps = true` on the model only if the migration has both `created_at` and `updated_at` columns.

## Lifecycle hooks (used in real code)

- `creating` — Activity, BaseSpace, Comment, Feed, Media, NotificationSubscriber, NotificationSubscription, Reaction, SpaceGroup
- `deleting` — BaseSpace, Comment, Feed, Media, Notification, Term
- `updating` — NotificationSubscriber, NotificationSubscription

If editing one of these, the `boot()` method has side-effects to consider (notification fan-out, cascade cleanup, default-value injection).

## Existing migration pattern

Every migrator uses this exact shape (verified in all 13 files):

```php
$table = $wpdb->prefix . static::$tableName;
if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
    $sql = "CREATE TABLE $table ( ... ) $charsetCollate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
```

`dbDelta` handles idempotency natively — `IF NOT EXISTS` is not idiomatic here.

## ALTER pattern (pro example)

Pro `Services/ProfileFieldsService.php:272-279`:

```php
$exists = $wpdb->get_col($wpdb->prepare(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
    $table, 'custom_fields'
));
if (!$exists) {
    $safe_table = esc_sql($table);
    $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `custom_fields` LONGTEXT NULL");
}
```

Match this pattern for runtime schema changes. `phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared` acceptable on static DDL with `esc_sql`'d table name.

## Migration idempotency

- Activation hook fires multiple times: per-site on multisite, on every plugin reactivation, after WP core update. Migration MUST be safe to run repeatedly.
- `dbDelta` is natively idempotent for table creation.
- `ALTER` requires explicit existence guard (see pattern above).
- Test mentally: "If I run this migration 3 times in a row, does anything break?"

## DBMigrator + multisite

- `database/DBMigrator.php` runs from `boot/app.php:60-64` on version-bump check. For multisite, see `multisite.md` — verify per-site iteration covers your new migration.
- Pro has NO `database/Migrations/` folder. Pro schema work is inline `dbDelta` or `ALTER` in service / module code. When adding a new pro table, follow `Modules/Followers/FollowerHelper.php:132-144` pattern.

## Down-migration

- See `backwards-compat.md` — every migration has a rollback path (or documented why not).

## Self-audit

For each model/migration change:
1. `$fillable` matches actual columns (or inherits cleanly from base)?
2. Multi-type table — `addGlobalScope` on the type discriminator?
3. `$casts` matches column types (if used)?
4. Every new WHERE/ORDER/JOIN column has an index?
5. Column charset = `utf8mb4_unicode_ci` for text?
6. Nullable matches PHP type hint?
7. UI `maxlength` ≤ DB column length?
8. Down-migration exists (or rationale documented)?
9. Migration is idempotent — safe to run 3 times in a row (SHOW TABLES guard + `dbDelta`, or `information_schema.COLUMNS` guard for ALTER)?
10. Lifecycle hook side-effects (creating/deleting) accounted for?
