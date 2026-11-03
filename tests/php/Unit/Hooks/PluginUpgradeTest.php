<?php

namespace FluentEvent\Tests\Unit\Hooks;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\ActivationHandler;
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

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->shouldReceive('get_charset_collate')->andReturn('');

        $this->flushes = 0;

        Functions\when('dbDelta')->justReturn([]);
        Functions\when('flush_rewrite_rules')->alias(function () {
            $this->flushes++;
        });
    }

    private function upgrade()
    {
        (new ActivationHandler(Mockery::mock(Application::class)))->maybeUpgrade();
    }

    public function test_an_update_rebuilds_the_url_rules_and_records_the_new_version()
    {
        update_option('fe_version', '0.9.0');

        $this->upgrade();

        $this->assertSame(1, $this->flushes);
        $this->assertSame(FLUENT_EVENT_PLUGIN_VERSION, get_option('fe_version'));
    }

    public function test_a_site_already_on_this_version_is_left_alone()
    {
        update_option('fe_version', FLUENT_EVENT_PLUGIN_VERSION);

        $this->upgrade();

        $this->assertSame(0, $this->flushes);
    }
}
