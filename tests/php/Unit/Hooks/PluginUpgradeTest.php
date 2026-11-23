<?php

namespace FluentEvent\Tests\Unit\Hooks;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\ActivationHandler;
use FluentEvent\Database\DBMigrator;
use FluentEvent\Framework\Foundation\Application;
use FluentEvent\Tests\TestCase;
use Mockery;

/**
 * Updating the plugin never fires its activation hook, so the URL rules and
 * tables a new version adds have to arrive on the first request after it.
 */
class PluginUpgradeTest extends TestCase
{
    /** @var int */
    private $flushes = 0;

    /** @var int */
    private $migrations = 0;

    /** @var array<string, mixed> */
    private $transients = [];

    /** @var string[] Tables the fake database holds. */
    private $tables = [];

    /** @var string[] Tables the fake server refuses to create. */
    private $refused = [];

    /** @var object|null */
    private $sharedWpdb = null;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->sharedWpdb = $wpdb;

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wptest_';
        $wpdb->shouldReceive('get_charset_collate')->andReturn('');
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function ($text) {
            return $text;
        });
        $wpdb->shouldReceive('prepare')->andReturnUsing(function ($query, $table) {
            return $table;
        });
        // SHOW TABLES LIKE '<table>' answers with the name when the table exists.
        $wpdb->shouldReceive('get_var')->andReturnUsing(function ($table) {
            return in_array($table, $this->tables, true) ? $table : null;
        });

        $this->tables = [];
        $this->refused = [];

        $this->flushes = 0;
        $this->migrations = 0;
        $this->transients = [];

        // Each migrator calls dbDelta() once for a table it has to create.
        Functions\when('dbDelta')->alias(function ($sql) {
            $this->migrations++;

            if (preg_match('/CREATE TABLE (\S+)/', $sql, $match) && !in_array($match[1], $this->refused, true)) {
                $this->tables[] = $match[1];
            }

            return [];
        });
        Functions\when('flush_rewrite_rules')->alias(function () {
            $this->flushes++;
        });
        Functions\when('get_transient')->alias(function ($key) {
            return $this->transients[$key] ?? false;
        });
        Functions\when('set_transient')->alias(function ($key, $value) {
            $this->transients[$key] = $value;

            return true;
        });
        Functions\when('delete_transient')->alias(function ($key) {
            unset($this->transients[$key]);

            return true;
        });
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->sharedWpdb;

        parent::tearDown();
    }

    private function upgrade()
    {
        (new ActivationHandler(Mockery::mock(Application::class)))->maybeUpgrade();
    }

    public function test_an_update_rebuilds_the_url_rules_and_records_the_new_version()
    {
        update_option('fevent_version', '0.9.0');
        update_option(ActivationHandler::DB_VERSION_OPTION, FLUENT_EVENT_DB_VERSION);

        $this->upgrade();

        $this->assertSame(1, $this->flushes);
        $this->assertSame(0, $this->migrations, 'The database is current, so nothing migrates.');
        $this->assertSame(FLUENT_EVENT_PLUGIN_VERSION, get_option('fevent_version'));
    }

    public function test_a_site_already_on_this_version_is_left_alone()
    {
        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION);
        update_option(ActivationHandler::DB_VERSION_OPTION, FLUENT_EVENT_DB_VERSION);

        $this->upgrade();

        $this->assertSame(0, $this->flushes);
        $this->assertSame(0, $this->migrations);
    }

    // 1.0.0 recorded no database version, so its sites migrate again and get any table they are missing.
    public function test_a_site_without_a_database_version_migrates_and_records_it()
    {
        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION);

        $this->upgrade();

        $this->assertGreaterThan(0, $this->migrations);
        $this->assertSame(FLUENT_EVENT_DB_VERSION, get_option(ActivationHandler::DB_VERSION_OPTION));
        $this->assertArrayNotHasKey(ActivationHandler::MIGRATION_LOCK, $this->transients);
    }

    public function test_an_older_database_version_migrates_again()
    {
        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION);
        update_option(ActivationHandler::DB_VERSION_OPTION, '0.9.0');

        $this->upgrade();

        $this->assertGreaterThan(0, $this->migrations);
        $this->assertSame(FLUENT_EVENT_DB_VERSION, get_option(ActivationHandler::DB_VERSION_OPTION));
    }

    // Another request is migrating: this one leaves it to finish and records nothing.
    public function test_a_held_migration_lock_skips_the_migration()
    {
        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION);
        $this->transients[ActivationHandler::MIGRATION_LOCK] = 1;

        $this->upgrade();

        $this->assertSame(0, $this->migrations);
        $this->assertFalse(get_option(ActivationHandler::DB_VERSION_OPTION));
    }

    // The server refused a table: saving the version would stop it ever being made, so the next request retries.
    public function test_a_table_still_missing_after_the_migration_holds_the_version_back()
    {
        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION);
        $this->refused = ['wptest_fevent_meta'];

        $this->upgrade();

        $this->assertFalse(get_option(ActivationHandler::DB_VERSION_OPTION));
        $this->assertArrayHasKey(ActivationHandler::MIGRATION_LOCK, $this->transients, 'Without the lock, every request would retry.');

        // Within the lock's five minutes, the next request leaves the migration alone.
        $migrations = $this->migrations;
        $this->upgrade();
        $this->assertSame($migrations, $this->migrations);
    }
}
