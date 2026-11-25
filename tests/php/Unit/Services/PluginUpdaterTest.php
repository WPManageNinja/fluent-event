<?php

namespace FluentEvent\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FluentEvent\App\Services\PluginManager\PluginUpdater;
use FluentEvent\Tests\TestCase;

class PluginUpdaterTest extends TestCase
{
    private $hooks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->hooks = [];
        $capture = function ($hook, $callback) {
            $this->hooks[$hook] = $callback;
            return true;
        };
        Functions\when('add_filter')->alias($capture);
        Functions\when('add_action')->alias($capture);
        Functions\when('admin_url')->alias(function ($path) {
            return 'https://example.com/wp-admin/' . $path;
        });
        Functions\when('wp_nonce_url')->alias(function ($url, $action) {
            return $url . '&_wpnonce=nonce-for-' . $action;
        });
    }

    protected function tearDown(): void
    {
        unset($_GET['fluent_sl_check_update_fluent-event']);
        parent::tearDown();
    }

    private function makeUpdater()
    {
        return new PluginUpdater([
            'slug'              => 'fluent-event',
            'basename'          => 'fluent-event/fluent-event.php',
            'version'           => '1.0.0',
            'api_url'           => 'https://example.com/addons',
            'show_check_update' => true,
            'verify_checksum'   => false,
        ]);
    }

    public function test_check_update_link_carries_a_nonce()
    {
        Functions\when('current_user_can')->justReturn(true);
        $this->makeUpdater();

        $links = $this->hooks['plugin_row_meta']([], 'fluent-event/fluent-event.php');

        $this->assertStringContainsString('_wpnonce=nonce-for-fsl_check_update_fluent-event', $links['check_update']);
    }

    public function test_check_update_handler_verifies_the_nonce_before_clearing_the_cache()
    {
        $_GET['fluent_sl_check_update_fluent-event'] = '1';
        Functions\when('current_user_can')->justReturn(true);
        // check_admin_referer() dies on a bad nonce; the exception stands in for that.
        Functions\expect('check_admin_referer')->once()->with('fsl_check_update_fluent-event')
            ->andThrow(new \RuntimeException('nonce rejected'));
        Functions\expect('delete_transient')->never();

        $this->makeUpdater();

        $this->expectExceptionMessage('nonce rejected');
        $this->hooks['admin_init']();
    }
}
