<?php

namespace FluentEvent\Tests\Unit\Hooks;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\CleanupHandlers\CleanupHandler;
use FluentEvent\App\Services\EventReminderService;
use FluentEvent\Modules\FluentCommunity\Bootstrap;
use FluentEvent\Modules\FluentCommunity\FeedHandler;
use FluentEvent\Tests\Helpers\HookRecorder;
use FluentEvent\Tests\TestCase;

/**
 * Runs app/Hooks/*.php and the FluentCommunity bootstrap for real, so a renamed
 * handler, a throwing constructor or a job with no listener fails here.
 */
class HookRegistrationTest extends TestCase
{
    const HANDLER_NS = 'FluentEvent\\App\\Hooks\\Handlers\\';

    /** @var HookRecorder */
    private $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new HookRecorder();
        $recorder = $this->recorder;

        Functions\when('add_action')->alias(function ($hook, $callback, $priority = 10) use ($recorder) {
            $recorder->addAction($hook, $callback, $priority);
            return true;
        });

        Functions\when('add_filter')->alias(function ($hook, $callback, $priority = 10) use ($recorder) {
            $recorder->addFilter($hook, $callback, $priority);
            return true;
        });

        Functions\when('add_shortcode')->justReturn(true);
    }

    private function load($file)
    {
        $app = $this->recorder;

        require FLUENT_EVENT_PLUGIN_DIR . 'app/Hooks/' . $file;

        return $this->recorder;
    }

    private function loadEverything()
    {
        $this->load('actions.php');
        $this->load('filters.php');
        $this->load('includes.php');

        Bootstrap::register();

        return $this->recorder;
    }

    public function test_the_actions_file_registers_its_handlers_without_fatalling()
    {
        $this->assertNotEmpty($this->load('actions.php')->all());
    }

    public function test_every_registered_callback_resolves_to_a_real_method()
    {
        foreach ($this->loadEverything()->all() as $entry) {
            $this->assertCallbackIsUsable($entry);
        }
    }

    public function test_the_community_bridge_waits_for_fluentcommunity_to_enable_it()
    {
        $recorder = $this->load('actions.php');

        $this->assertSame(
            [[Bootstrap::class, 'register']],
            array_column($recorder->on('fluent_community/fluent_event/init'), 'callback')
        );
        $this->assertEmpty($recorder->on('fluent_community/portal_loaded'), 'The bridge must not load on fcom alone');
        $this->assertEmpty($recorder->on('fluent_community/feed/before_deleted'), 'Feed hooks registered before fcom enabled FluentEvent');
    }

    public function test_the_community_bridge_registers_at_once_when_fluentcommunity_already_enabled_it()
    {
        Functions\when('did_action')->alias(function ($hook) {
            return $hook === 'fluent_community/fluent_event/init' ? 1 : 0;
        });

        $recorder = $this->load('actions.php');

        $this->assertEmpty($recorder->on('fluent_community/fluent_event/init'));
        $this->assertNotEmpty($recorder->on('fluent_community/feed/before_deleted'));
    }

    /**
     * The alias stands in for fcom's class for this process only.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_a_bridge_registered_late_still_uses_fluentcommunitys_events_slug()
    {
        \Mockery::mock('alias:FluentCommunity\Modules\Integrations\Integrations')
            ->shouldReceive('getFluentEventSettings')
            ->andReturn(['enable_fluent_event' => 'yes', 'route_slug' => 'meetups']);

        Functions\when('did_action')->alias(function ($hook) {
            return $hook === 'fluent_community/fluent_event/init' ? 1 : 0;
        });

        [$entry] = $this->load('actions.php')->on('fluent_community/app_route_paths');

        $this->assertSame(['meetups'], $entry['callback'][0]->addRoutePath([]));
    }

    public function test_every_queued_job_has_a_listener()
    {
        $registered = $this->loadEverything()->hooks();

        foreach ($this->queuedJobHooks() as $hook) {
            $this->assertContains($hook, $registered, "{$hook} is queued with Action Scheduler but nothing listens for it");
        }
    }

    public function test_event_deletion_cancels_reminders_and_unlinks_the_post_before_cleanup_runs()
    {
        // cleanEvent deletes the event's fe_meta, which is what names the queued sends and the linked post.
        $entries = $this->loadEverything()->on('fluent_event/event/before_deleted');

        $cleanup = $this->priorityOf($entries, [CleanupHandler::class, 'cleanEvent']);
        $cancel = $this->priorityOf($entries, [EventReminderService::class, 'cancelReminders']);
        $unlink = $this->priorityOf($entries, [FeedHandler::class, 'unlinkPost']);

        $this->assertLessThan($cleanup, $cancel);
        $this->assertLessThan($cleanup, $unlink);
    }

    public function test_event_lifecycle_hooks_keep_reminders_in_step()
    {
        $recorder = $this->loadEverything();

        $this->assertNotNull($this->priorityOf($recorder->on('fluent_event/event/created'), [EventReminderService::class, 'scheduleReminders']));
        $this->assertNotNull($this->priorityOf($recorder->on('fluent_event/event/updated'), [EventReminderService::class, 'rescheduleReminders']));
        $this->assertNotNull($this->priorityOf(
            $recorder->on('fluent_event/settings/email_notifications_updated'),
            [EventReminderService::class, 'rescheduleAllReminders']
        ));
    }

    public function test_deleting_a_calendar_cascades_to_its_events()
    {
        $this->assertNotNull($this->priorityOf(
            $this->load('actions.php')->on('fluent_event/calendar/before_deleted'),
            [CleanupHandler::class, 'cleanCalendar']
        ));
    }

    /**
     * @return string[]
     */
    private function queuedJobHooks()
    {
        $hooks = [];
        $pattern = '/\bas_(?:enqueue_async_action|schedule_single_action|schedule_recurring_action)\s*\((?:[^\'",]*,\s*)?[\'"]([^\'"]+)[\'"]/';

        foreach (['app', 'Modules'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FLUENT_EVENT_PLUGIN_DIR . $dir));

            foreach ($files as $file) {
                if ($file->getExtension() === 'php' && preg_match_all($pattern, file_get_contents($file->getPathname()), $m)) {
                    $hooks = array_merge($hooks, $m[1]);
                }
            }
        }

        $hooks = array_values(array_unique($hooks));

        $this->assertNotEmpty($hooks, 'Found no queued jobs; the scan is broken');

        return $hooks;
    }

    public function test_a_plugin_update_is_picked_up_on_the_next_request()
    {
        $callbacks = array_column($this->load('actions.php')->on('init'), 'callback');

        $this->assertContains('ActivationHandler@maybeUpgrade', $callbacks);
    }

    private function priorityOf(array $entries, array $callback)
    {
        foreach ($entries as $entry) {
            $registered = $entry['callback'];

            if (!is_array($registered)) {
                continue;
            }

            $class = is_object($registered[0]) ? get_class($registered[0]) : $registered[0];

            if ($class === $callback[0] && $registered[1] === $callback[1]) {
                return $entry['priority'];
            }
        }

        return null;
    }

    private function assertCallbackIsUsable(array $entry)
    {
        $callback = $entry['callback'];

        if ($callback instanceof \Closure) {
            return;
        }

        // Mirrors FoundationTrait::parseHookHandler(): a bare class runs __invoke() if it has one, else handle().
        if (is_string($callback)) {
            $parts = preg_split('/::|@/', $callback);
            $default = method_exists(self::HANDLER_NS . $parts[0], '__invoke') ? '__invoke' : 'handle';
            $callback = [$parts[0], $parts[1] ?? $default];
        }

        [$class, $method] = $callback;

        if (is_string($class) && strpos($class, '\\') === false) {
            $class = self::HANDLER_NS . $class;
        }

        $this->assertTrue(is_object($class) || class_exists($class), "{$entry['hook']} names missing class {$this->name($class)}");
        $this->assertTrue(method_exists($class, $method), "{$entry['hook']} names missing method {$this->name($class)}::{$method}");
    }

    private function name($class)
    {
        return is_object($class) ? get_class($class) : $class;
    }
}
