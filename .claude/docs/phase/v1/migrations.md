# Migrations — Phase 1

> Plan only. Nothing implemented yet.
>
> Column shape comes from [db/fevent_db_schema.sql](../../db/fevent_db_schema.sql), exported
> from the Workbench model. Migration style comes from
> [patterns/database.md](../../patterns/database.md).

**All 7 migrations are written in v1, and all 7 tables are created on activation.** The
schema is settled; adding a table later costs a migration and a version bump for nothing.

What differs is only whether a v1 *feature* puts rows in it:

| Migration — all written in v1 | Table — all created in v1 | Does v1 store rows? |
|---|---|---|
| `CalendarsTable.php` | `fe_calendars` | yes |
| `EventDetailsTable.php` | `fe_event_details` | yes |
| `GuestsTable.php` | `fe_guests` | yes — RSVPs |
| `MetaTable.php` | `fe_meta` | yes — settings sections |
| `TicketTypesTable.php` | `fe_ticket_types` | no — ticketing is post-v1 |
| `NotificationsTable.php` | `fe_notifications` | no — notifications are post-v1 |
| `NotificationUsersTable.php` | `fe_notification_users` | no — notifications are post-v1 |

The three "no" rows are complete, correct, indexed tables sitting empty. They are not stubs
and they are not deferred work — the migration is done, and the feature that fills them
arrives later without touching the database.

Auto-discovered by [DBMigrator](../../../../database/DBMigrator.php) from
`database/Migrations/*.php` — no central list, no ordering to maintain.

---

## The foreign keys cannot be created

The schema declares six FK constraints. **None of them will exist in the database.** Three
independent reasons:

1. **`Blueprint` has no `foreign()` method.** Checked the full API — `id`, `string`, `json`,
   `index`, `unique`, `addColumn`, `dropIndex` and so on. Nothing expresses a constraint.
2. **`dbDelta()` does not support foreign keys.** Our `create()` goes through it, and it
   silently mangles or drops `FOREIGN KEY` lines.
3. **No sibling uses them.** `grep "FOREIGN KEY"` across the `database/` directory of
   fluent-community, fluent-booking, fluent-cart and fluent-affiliate returns **zero files**.
   This is the house pattern, not an oversight.

### What each constraint becomes

The indexes backing them are still created — only the constraints are dropped. Each
`ON DELETE` rule becomes a service-layer responsibility:

| Constraint | Schema rule | Becomes |
|---|---|---|
| `fk_events_calendar_id` | RESTRICT | **Service must block deleting a calendar that has events.** This is v1 open question 5 — the database will not stop it |
| `fk_events_parent_event_id` | CASCADE | deleting a recurrence parent must delete occurrences — not v1 |
| `fk_ticket_types_event_id` | CASCADE | deleting an event deletes its tiers — not v1 |
| `fk_guests_event_id` | CASCADE | **deleting an event must delete its guests**, in the same transaction |
| `fk_guests_calendar_id` | RESTRICT | reinforces the calendar rule above |
| `fk_guests_ticket_type_id` | RESTRICT | not v1 |

> **This corrects a claim in [v1_docs.md](v1_docs.md) §2.** The stated reason all 7 tables
> get created was that `guests` carries an FK to `ticket_types`, so the table had to exist.
> With no FKs, that argument dissolves. The decision stands on the weaker but still adequate
> ground: the schema is settled and migrations are cheap.

---

## Mapping the schema to Blueprint

Verified against the framework, not assumed:

| Schema | Blueprint |
|---|---|
| table name | `public $table = 'fe_calendars';` — **without** `wp_`. `Schema::table()` prepends `$wpdb->prefix` |
| `BIGINT UNSIGNED AUTO_INCREMENT PK` | `$table->id()` |
| `VARCHAR(n)` | `$table->string('col', n)` |
| `BIGINT UNSIGNED` FK column | `$table->bigInteger('col')->unsigned()` |
| `TINYINT(1)` | `$table->tinyInteger('col')->default(0)` |
| `DECIMAL(10,2)` | `$table->decimal('col', 10, 2)` |
| `JSON` | `$table->json('col')->nullable()` |
| `DATETIME` | `$table->datetime('col')` |
| `NULL DEFAULT NULL` | `->nullable()` |
| `DEFAULT 'x'` | `->default('x')` |
| `COMMENT '…'` | `->comment('…')` |
| `INDEX (a, b)` | `$table->index(['a','b'], 'idx_name')` |
| `UNIQUE INDEX (a)` | `$table->unique('a', 'uq_name')` |
| `created_at` / `updated_at` | `$table->timestamps()` |

### Two things `timestamps()` does not do

```php
public function timestamps()
{
    $this->timestamp('created_at')->default('CURRENT_TIMESTAMP');
    $this->timestamp('updated_at')->nullable();
}
```

- **No `ON UPDATE CURRENT_TIMESTAMP`.** Blueprint has no `onUpdate()`. The ORM maintains
  `updated_at` on save, as it does in every sibling. Anything writing via raw `$wpdb` must
  set it explicitly.
- **`updated_at` is nullable**, so a freshly inserted row has `NULL` there until first
  update. Do not rely on `updated_at` being populated.

`CURRENT_TIMESTAMP` is recognised as a raw expression rather than quoted as a string —
confirmed in `ColumnDefinition`.

---

## Open question that blocks `fe_guests`

**`guests.status` has no RSVP vocabulary.** The schema comments it
`approved | checked_in | cancelled`. v1's only feature is going / not-going, which is
absent.

The column is `VARCHAR(64)`, so this is a *vocabulary* decision, not a schema change — but
it must be made deliberately before the model and service are written:

- widen the documented vocabulary to include `going` / `not_going`, or
- add a separate `rsvp_response` column and leave `status` for lifecycle.

Tracked as open question 1 in [v1_docs.md](v1_docs.md) §7.

---

## Verify with

```bash
./wpf migrate:refresh     # drop and rebuild all 7
./wpf db:show             # confirm they exist
./wpf db:table fe_guests  # confirm columns and indexes
./wpf check               # full pipeline
```

Every `create()` is idempotent through `dbDelta`, and every `alter()` operation self-guards,
so re-running on each activation is safe.

**Adding a column later** goes into the same file's `alter()` method — not a second
migration file for the same table. One file per table keeps the current shape readable in
one place.
