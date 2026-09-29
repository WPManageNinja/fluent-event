# Database patterns

**This is the one area where we do not follow fcom.** See [README.md](README.md).

---

## What the siblings do

fcom, affiliate and booking all hand-write migrators — a static `migrate()` per table, a
`SHOW TABLES LIKE` guard, and a `CREATE TABLE` string passed to `dbDelta`:

```php
// fluent-community/database/Migrations/TermMigrator.php
class TermMigrator
{
    public static function migrate()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fcom_terms';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table ( … ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
```

`DBMigrator::migrate()` then calls each in dependency order by hand.

**Two real problems with it.** The `SHOW TABLES LIKE` guard means the migrator is
create-only — an existing table is never updated, so adding a column later needs a separate
hand-written `ALTER` with its own guard. And the ordering is manual, so a new table has to
be inserted in the right place in a list.

---

## What we do instead

> **Status:** aspirational. The shipped [DBMigrator.php](../../../database/DBMigrator.php)
> is the plain form — an explicit ordered array of `<Name>Migrator` classes, each with a
> `static migrate()` that hands a `CREATE TABLE` string to `dbDelta` behind a
> `SHOW TABLES LIKE` guard. No Blueprint, no auto-discovery, no `alter()`. The rest of this
> section describes where we intend to take it; see
> [database/Migrations/](../../../database/Migrations/) for what runs today.

The target is a newer generation from `wpfluent/devtools`: a Blueprint API, auto-discovery,
and idempotent guards per operation.

One file per table in `database/Migrations/`, each a class with a `$table` property and
`create()` / `alter()` methods:

```php
class CalendarsTable
{
    public $table = 'fe_calendars';

    public function create(Blueprint $table)
    {
        $table->id();
        $table->string('title', 255);
        $table->string('slug', 255)->unique();
        $table->string('status', 64)->default('active');
        $table->json('meta')->nullable();
        $table->timestamps();
    }

    public function alter(Blueprint $table)
    {
        // added later; each op self-guards
        $table->addColumn('archived_at')->dateTime()->nullable();
    }
}
```

What that buys over the sibling approach:

- **Auto-discovery.** `runBlueprints()` globs `Migrations/*.php` — no central list to
  update, no ordering to get wrong.
- **`create()` is idempotent** — it goes through `dbDelta`, which updates an existing table
  to match rather than skipping it.
- **`alter()` is idempotent per operation.** `addColumn` skips if the column exists,
  `dropIndex` skips if it doesn't. Safe to run on every activation.
- **No migration tracking table** is needed at all.

Generate the file rather than writing it by hand:

```bash
./wpf make:migration fe_calendars
./wpf migrate            # migrate:up
./wpf migrate:refresh    # drop + rebuild
./wpf db:show
```

---

## Adding a column later

Add an `addColumn()` to the **same** migration file's `alter()` method. Do not write a
second migration file for the same table — the guards make re-running safe, and one file
per table keeps the current shape readable in one place.

---

## Schema conventions for this plugin

- Table prefix `wp_fe_`; the migrator's `$table` omits the `wp_` (the framework adds it).
- Every table: `id` BIGINT UNSIGNED auto-increment PK, `created_at` / `updated_at`.
- Open-ended config goes in a JSON column, with a `default*()` method on the model
  documenting the keys in use — see [backend.md](backend.md). **The column name is not
  uniform**: `event_details.settings` (`defaultSettings()`) and `calendars.meta`
  (`defaultMeta()`). Match the table you are touching rather than assuming `settings`.
  Both are deliberately open-ended — new config lands there without a migration.
- Index what you filter and sort on. The locked schema already names its indexes; keep
  those names.
- The authority for column shape is
  [fe_db_schema.sql](../db/fe_db_schema.sql), exported from the Workbench model at
  [fe_db_design.mwb](../db/fe_db_design.mwb). Change the model, re-export, then
  write the Blueprint to match — not the other way round.

---

## Writing queries

- ORM over raw `$wpdb` in controllers and services.
- Any raw query goes through `$wpdb->prepare()`. `LIKE` needs
  `'%' . $wpdb->esc_like($term) . '%'` first.
- Counts for a list screen come from one grouped aggregate, not a query per row. The
  calendars grid and the combined events timeline are both N+1 traps.
- Status transitions that must not race use a CAS update — `UPDATE … WHERE id = ? AND
  status = 'old'` — then check affected rows.
- Multi-row writes that must be atomic open `DB::transaction()` at the service layer.
