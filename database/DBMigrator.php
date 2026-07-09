<?php

namespace FluentEvents\Database;

use FluentEvents\Framework\Database\Schema;
use FluentEvents\Framework\Database\Migration\Blueprint;

/**
 * Database Migrator — the central place for all database migrations.
 *
 * This class handles two styles of migrations that coexist:
 *
 *   1. SQL migrations  — .sql files in database/Migrations/ (legacy, uses dbDelta)
 *   2. Blueprint migrations — .php files in database/Migrations/ (recommended)
 *
 * How it works:
 *   - migrateUp() is the single entry point. It runs SQL migrations first,
 *     then discovers and runs all Blueprint PHP files automatically.
 *   - Everything is idempotent: create() uses dbDelta (safe to re-run),
 *     alter() guards each operation (checks DB state before executing).
 *   - No migration tracking table is needed. Safe to call on every
 *     plugin activation, update, CLI command, or test run.
 *
 * To add a new table:
 *   ./wpf make:migration my_table
 *   Then edit the generated PHP file in database/Migrations/
 *
 * To add columns later:
 *   Add $table->addColumn(...) calls inside the alter() method
 *   of the same migration file. Guards prevent duplicate execution.
 *
 * @see \WPFluent\Database\Migration\Blueprint
 * @see \WPFluent\Database\Migration\ColumnDefinition
 */
class DBMigrator
{
    /**
     * SQL migration files from outside the Migrations/ directory.
     *
     * Files inside Migrations/*.sql are auto-discovered — only use
     * this for SQL files stored elsewhere (e.g. another package).
     *
     * Supports two formats:
     *   - Indexed: ['/path/to/table.sql'] → table name derived from filename
     *   - Keyed:   ['my_table' => '/path/to.sql'] → explicit table name
     *
     * @var array<int|string, string>
     */
    private static $migrations = [
        // ...
    ];

    /**
     * Blueprint table names registered via inline create() calls.
     *
     * Tables from Migrations/*.php files are auto-discovered — only
     * list tables here if you define Blueprint migrations inline
     * in migrateUp() instead of in separate files.
     *
     * Used by getTableNames() so migrate:down and migrate:refresh
     * can discover and drop all tables without running migrateUp().
     *
     * @var string[]
     */
    protected static $tables = [];

    // ═════════════════════════════════
    //  Entry Points
    // ═════════════════════════════════

    /**
     * Run all migrations (called on plugin activation/update).
     *
     * @param  bool  $network_wide  Whether to run for all sites in multisite
     * @return array dbDelta result messages from SQL migrations
     */
    public static function run($network_wide = false)
    {
        return static::migrateUp($network_wide);
    }

    /**
     * Execute all migrations in order:
     *   1. SQL file migrations (Migrations/*.sql + $migrations array + inline SQL)
     *   2. Blueprint migrations (Migrations/*.php — auto-discovered)
     *
     * Every operation is idempotent:
     *   - SQL files use dbDelta (creates table if missing, updates columns if changed)
     *   - Blueprint create() uses dbDelta (same behavior)
     *   - Blueprint alter() checks current DB state before each operation
     *
     * Safe to call on every activation, update, CLI run, and test.
     *
     * @param  bool  $network_wide  Whether to run for all sites in multisite
     * @return array dbDelta result messages from SQL migrations
     */
    public static function migrateUp($network_wide = false)
    {
        // Step 1: Run all SQL-based migrations via dbDelta
        $result = Schema::migrate(static::getMigrations());

        // Step 2: Discover and run all Blueprint PHP migration files
        static::runBlueprints();

        return $result;
    }

    /**
     * Drop ALL tables managed by this migrator (SQL + Blueprint).
     *
     * Used by migrate:down CLI command. Discovers table names from
     * all three sources without needing to run migrateUp() first.
     *
     * @return array Results from each drop operation
     */
    public static function migrateDown()
    {
        $result = [];

        foreach (static::getTableNames() as $table) {
            $result[] = Schema::dropTableIfExists($table);
        }

        return $result;
    }

    // ═════════════════════════════════
    //  SQL Migrations
    // ═════════════════════════════════

    /**
     * Collect all SQL-based migrations into a single array.
     *
     * Merges three sources:
     *   1. Auto-discovered .sql files from database/Migrations/
     *   2. Manually registered files in $migrations property
     *   3. Inline SQL definitions (written directly in this method)
     *
     * Returns: ['table_name' => 'SQL string or file path', ...]
     *
     * @return array<string, string>
     */
    public static function getMigrations()
    {
        // Merge auto-discovered SQL files with manually registered ones
        $sqls = array_merge(
            static::getMigratableFiles(
                glob(__DIR__.'/Migrations/*.sql')
            ),
            static::mapMigrations()
        );

        // Inline SQL migration — define table columns directly here
        // Useful for simple tables that don't need a separate file
        $sqls['alpha_meta'] = "
            id int auto_increment not null primary key,
            meta_key varchar(10) not null,
            meta_value varchar(255)
        ";

        // Filter out the example.sql placeholder file
        return array_filter($sqls, function ($file) {
            return basename($file) !== 'example.sql';
        });
    }

    // ═════════════════════════════════
    //  Blueprint API
    // ═════════════════════════════════

    /**
     * Auto-discover and run all Blueprint migration files (*.php) from Migrations/.
     *
     * Each PHP file in the directory should define a class with:
     *   - public $table = 'table_name';     — the table name (without prefix)
     *   - public function create(Blueprint)  — define columns for CREATE TABLE
     *   - public function alter(Blueprint)   — define changes (optional)
     *
     * Files are loaded via require_once, class name is derived from filename.
     * Example: AlphaOrdersTable.php → <PluginNamespace>\Database\Migrations\AlphaOrdersTable
     */
    protected static function runBlueprints()
    {
        $files = glob(__DIR__ . '/Migrations/*.php') ?: [];

        foreach ($files as $file) {
            require_once $file;

            // Derive the fully-qualified class name from the filename
            $class = __NAMESPACE__ . '\\Migrations\\' . basename($file, '.php');

            if (!class_exists($class)) {
                continue;
            }

            $migration = new $class();
            $table = $migration->table ?? null;

            if (!$table) {
                continue;
            }

            // Track this table name so getTableNames() can find it
            if (!in_array($table, static::$tables)) {
                static::$tables[] = $table;
            }

            // Run create() if defined — uses dbDelta (idempotent)
            if (method_exists($migration, 'create')) {
                static::create($table, [$migration, 'create']);
            }

            // Run alter() if defined — each operation is self-guarding
            if (method_exists($migration, 'alter')) {
                static::alter($table, [$migration, 'alter']);
            }
        }
    }

    /**
     * Create a table using the Blueprint fluent API.
     *
     * Internally uses dbDelta which is idempotent — if the table exists,
     * it updates columns to match; if it doesn't, it creates the table.
     *
     * Usage:
     *   DBMigrator::create('orders', function (Blueprint $table) {
     *       $table->id();
     *       $table->string('title', 100);
     *       $table->timestamps();
     *   });
     *
     * @param  string   $table    Table name without prefix
     * @param  callable $callback Receives a Blueprint instance to define columns
     * @return array    dbDelta result messages
     */
    public static function create($table, callable $callback)
    {
        // Collect column definitions via the callback (no SQL runs yet)
        $blueprint = new Blueprint($table, 'create');
        $callback($blueprint);

        // Track this table name for getTableNames()
        if (!in_array($table, static::$tables)) {
            static::$tables[] = $table;
        }

        // Generate CREATE TABLE SQL and execute via dbDelta
        return $blueprint->executeCreate();
    }

    /**
     * Alter a table using the Blueprint fluent API.
     *
     * Uses a collect-then-execute pattern:
     *   1. The callback collects operations (addColumn, dropColumn, etc.)
     *   2. executeAlter() runs each operation with a guard check:
     *      - addColumn:    skips if column already exists
     *      - dropColumn:   skips if column doesn't exist
     *      - modifyColumn: skips if column doesn't exist
     *      - renameColumn: skips if source missing or target exists
     *      - addIndex:     skips if index already exists
     *      - dropIndex:    skips if index doesn't exist
     *
     * This makes every alter() call idempotent — safe to run repeatedly.
     *
     * Usage:
     *   DBMigrator::alter('orders', function (Blueprint $table) {
     *       $table->addColumn('priority')->tinyInteger()->default(0);
     *       $table->addColumn('notes')->text()->nullable();
     *       $table->dropColumn('old_field');
     *       $table->modifyColumn('title')->string(255);
     *       $table->renameColumn('old_name', 'new_name');
     *       $table->index('priority');
     *       $table->dropIndex('old_index');
     *   });
     *
     * @param  string   $table    Table name without prefix
     * @param  callable $callback Receives a Blueprint instance to define operations
     * @return array    Result messages (one per executed operation, empty if all skipped)
     */
    public static function alter($table, callable $callback)
    {
        // Collect alter operations via the callback (no SQL runs yet)
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);

        // Execute each operation with guard checks against current DB state
        return $blueprint->executeAlter();
    }

    // ═════════════════════════════════
    //  Table Name Discovery
    // ═════════════════════════════════

    /**
     * Get ALL table names managed by this migrator.
     *
     * Merges three sources so migrate:down and migrate:refresh
     * can find every table without running migrateUp() first:
     *   1. SQL migration table names (from getMigrations() keys)
     *   2. Inline Blueprint table names (from $tables property)
     *   3. File-based Blueprint table names (from Migrations/*.php)
     *
     * @return string[] Unique table names (without prefix)
     */
    public static function getTableNames()
    {
        return array_unique(array_merge(
            array_keys(static::getMigrations()),
            static::$tables,
            static::getBlueprintTableNames()
        ));
    }

    /**
     * Scan Blueprint PHP files for table names.
     *
     * Reads the $table property from each migration class without
     * running create() or alter(). Used by getTableNames() for
     * table discovery when migrateUp() hasn't been called.
     *
     * @return string[] Table names from Blueprint migration files
     */
    protected static function getBlueprintTableNames()
    {
        $tables = [];
        $files = glob(__DIR__ . '/Migrations/*.php') ?: [];

        foreach ($files as $file) {
            require_once $file;

            $class = __NAMESPACE__ . '\\Migrations\\' . basename($file, '.php');

            if (class_exists($class) && property_exists($class, 'table')) {
                $tables[] = (new $class())->table;
            }
        }

        return $tables;
    }

    // ═════════════════════════════════
    //  Internal Helpers (SQL file processing)
    // ═════════════════════════════════

    /**
     * Process an array of SQL file paths into a migration map.
     *
     * Takes raw file paths from glob(), sorts them by creation/modification
     * time, and returns ['table_name' => '/path/to/file.sql', ...].
     * Table name is derived from the filename (minus .sql extension).
     * Empty files are excluded.
     *
     * @param  array $files Array of absolute file paths
     * @return array<string, string> ['table_name' => 'file_path', ...]
     */
    public static function getMigratableFiles($files)
    {
        $result = [];

        // Collect file metadata for sorting
        $files = array_map(function ($filePath) {
            return [
                'path' => $filePath,
                'ctime' => filectime($filePath),
                'mtime' => filemtime($filePath)
            ];
        }, $files);

        // Sort by creation time, then modification time
        usort($files, function ($file1, $file2) {
            if ($file1['ctime'] == $file2['ctime']) {
                return $file1['mtime'] - $file2['mtime'];
            }
            return $file1['ctime'] - $file2['ctime'];
        });

        // Map filename (without .sql) → file path
        foreach ($files as $file) {
            $result[basename($file['path'], '.sql')] = $file['path'];
        }

        // Exclude empty files
        return array_filter($result, function($f) {
            return filesize($f) > 0;
        });
    }

    /**
     * Resolve the $migrations property into a migration map.
     *
     * Handles both indexed entries (table name from filename)
     * and keyed entries (explicit table name). Skips missing
     * or empty files.
     *
     * @return array<string, string> ['table_name' => 'file_path', ...]
     */
    protected static function mapMigrations()
    {
        $result = [];

        foreach (static::$migrations as $key => $value) {
            // Indexed entry: derive table name from filename
            if (is_int($key)) {
                $file = basename($value);
                $key = substr($file, 0, strpos($file, '.'));
            }

            // Only include files that exist and are non-empty
            if (@filesize($value)) {
                $result[$key] = $value;
            }
        }

        return array_filter($result);
    }
}
