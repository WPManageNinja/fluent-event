<?php

namespace FluentEvent\Tests\Unit\Database;

use FluentEvent\Database\Migrations\LegacyPrefixMigrator;
use FluentEvent\Tests\TestCase;
use Mockery;

/**
 * On a site that never ran the old FluentEvent, `fe_settings`, `fe_version` and `wp_fe_*` tables
 * belong to another plugin; the move must leave them alone.
 */
class LegacyPrefixFreshInstallTest extends TestCase
{
    /** @var object|null */
    private $sharedWpdb = null;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->sharedWpdb = $wpdb;

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wptest_';
        $wpdb->posts = 'wptest_posts';
        $wpdb->postmeta = 'wptest_postmeta';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function ($text) {
            return $text;
        });
        $wpdb->shouldReceive('prepare')->andReturnUsing(function ($query, $table) {
            return $table;
        });
        // Another plugin's `wp_fe_meta`; none of FluentEvent's tables.
        $wpdb->shouldReceive('get_var')->andReturnUsing(function ($table) {
            return $table === 'wptest_fe_meta' ? $table : null;
        });
        $wpdb->shouldNotReceive('query');
        $wpdb->shouldNotReceive('update');
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->sharedWpdb;

        $this->setStaticProperty(LegacyPrefixMigrator::class, 'done', null);

        parent::tearDown();
    }

    public function test_a_fresh_install_is_marked_done_without_touching_other_plugins_data()
    {
        update_option('fe_settings', ['other' => 'plugin']);
        update_option('fe_version', '3.0.0');

        $this->assertTrue(LegacyPrefixMigrator::run());

        $this->assertSame(1, get_option(LegacyPrefixMigrator::DONE_OPTION));
        $this->assertSame(['other' => 'plugin'], get_option('fe_settings'));
        $this->assertSame('3.0.0', get_option('fe_version'));
        $this->assertFalse(get_option('fevent_settings'));
    }
}
