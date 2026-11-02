<?php

namespace FluentEvent\Tests\Unit\Hooks;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\ActivationHandler;
use FluentEvent\App\Hooks\Handlers\DeactivationHandler;
use FluentEvent\Framework\Foundation\Application;
use FluentEvent\Tests\TestCase;
use Mockery;

/**
 * Network (de)activation on a 150-site network: Action Scheduler keeps one
 * queue per site, so every site needs its own unschedule and its own requeue.
 */
class NetworkActivationTest extends TestCase
{
    /** @var int */
    private $currentBlog = 1;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->blogs = 'wptest_blogs';
        $wpdb->shouldReceive('get_charset_collate')->andReturn('');

        $this->currentBlog = 1;

        Functions\when('dbDelta')->justReturn([]);
        Functions\when('is_super_admin')->justReturn(true);

        // get_sites() returns 100 sites unless asked for more.
        Functions\when('get_sites')->alias(function ($args = []) {
            $number = isset($args['number']) ? $args['number'] : 100;
            $ids = $number ? range(1, min(150, $number)) : range(1, 150);

            if (isset($args['fields']) && $args['fields'] === 'ids') {
                return $ids;
            }

            return array_map(function ($id) {
                return (object) ['blog_id' => $id];
            }, $ids);
        });

        Functions\when('switch_to_blog')->alias(function ($id) {
            $this->currentBlog = (int) $id;
            return true;
        });

        Functions\when('restore_current_blog')->alias(function () {
            $this->currentBlog = 1;
            return true;
        });
    }

    private function app()
    {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('isMultisite')->andReturn(true);

        return $app;
    }

    public function test_network_activation_requeues_reminders_on_every_site()
    {
        $queuedOn = [];

        Functions\when('as_enqueue_async_action')->alias(function ($hook) use (&$queuedOn) {
            if ($hook === 'fluent_event/reschedule_event_reminders') {
                $queuedOn[] = $this->currentBlog;
            }

            return 1;
        });

        (new ActivationHandler($this->app()))->handle(true);

        $this->assertSame(range(1, 150), $queuedOn);
        $this->assertSame(1, $this->currentBlog);
    }

    public function test_network_deactivation_clears_queued_actions_on_every_site()
    {
        $clearedOn = [];

        Functions\when('as_unschedule_all_actions')->alias(function () use (&$clearedOn) {
            $clearedOn[] = $this->currentBlog;
        });

        (new DeactivationHandler($this->app()))->handle(true);

        $this->assertSame(range(1, 150), $clearedOn);
        $this->assertSame(1, $this->currentBlog);
    }

    public function test_a_new_site_is_set_up_when_the_plugin_is_network_active()
    {
        // As on front-end signup (wp-activate.php), where wp-admin/includes/plugin.php is never loaded.
        $this->assertFalse(function_exists('is_plugin_active_for_network'));

        Functions\when('get_site_option')->justReturn(['fluent-events/fluent-event.php' => time()]);
        $queuedOn = $this->recordReminderRequeues();

        (new ActivationHandler($this->app()))->handleNewSite((object) ['blog_id' => 42], 'fluent-events/fluent-event.php');

        $this->assertSame([42], $queuedOn->getArrayCopy());
        $this->assertSame(1, $this->currentBlog);
    }

    public function test_a_new_site_is_left_alone_when_the_plugin_is_not_network_active()
    {
        Functions\when('get_site_option')->justReturn(['another-plugin/another-plugin.php' => time()]);
        $queuedOn = $this->recordReminderRequeues();

        (new ActivationHandler($this->app()))->handleNewSite((object) ['blog_id' => 42], 'fluent-events/fluent-event.php');

        $this->assertSame([], $queuedOn->getArrayCopy());
        $this->assertSame(1, $this->currentBlog);
    }

    public function test_new_sites_are_set_up_after_core_creates_their_tables()
    {
        $boot = file_get_contents(FLUENT_EVENT_PLUGIN_DIR . 'boot/app.php');

        // wp_insert_site fires before core's wp_initialize_site (priority 10) creates the site's tables.
        $this->assertStringNotContainsString("'wp_insert_site'", $boot);
        $this->assertSame(1, preg_match("/add_action\('wp_initialize_site',.*?\}, (\d+)\);/s", $boot, $match));
        $this->assertGreaterThan(10, (int) $match[1]);
    }

    private function recordReminderRequeues()
    {
        $queuedOn = new \ArrayObject();

        Functions\when('as_enqueue_async_action')->alias(function ($hook) use ($queuedOn) {
            if ($hook === 'fluent_event/reschedule_event_reminders') {
                $queuedOn[] = $this->currentBlog;
            }

            return 1;
        });

        return $queuedOn;
    }
}
