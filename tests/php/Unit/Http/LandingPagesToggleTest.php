<?php

namespace FluentEvent\Tests\Unit\Http;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\AdminMenuHandler;
use FluentEvent\App\Hooks\Handlers\LandingPageHandler;
use FluentEvent\App\Http\Controllers\SettingsController;
use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Tests\TestCase;

class LandingPagesToggleTest extends TestCase
{
    private function save($landing)
    {
        $request = new class($landing) extends Request {
            private $landing;

            public function __construct($landing)
            {
                $this->landing = $landing;
            }

            public function all()
            {
                return ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => $this->landing, 'portal_slug' => 'events'];
            }
        };

        $controller = new class extends SettingsController {
            public function __construct()
            {
            }

            public function validate($data, $rules, $messages = [])
            {
                return $data;
            }
        };

        return $controller->updateGeneral($request);
    }

    private function registeredHooks()
    {
        $hooks = [];
        $record = function ($hook) use (&$hooks) {
            $hooks[] = $hook;
        };
        Functions\when('add_action')->alias($record);
        Functions\when('add_filter')->alias($record);

        (new LandingPageHandler())->register();

        return $hooks;
    }

    public function test_landing_pages_register_no_routes_by_default()
    {
        Functions\expect('add_rewrite_rule')->never();

        $this->assertSame([], $this->registeredHooks());

        (new LandingPageHandler())->addRewriteRules();
    }

    public function test_turned_on_landing_pages_register_their_routes()
    {
        Settings::update(Settings::GENERAL, ['enable_landing_pages' => 'yes']);

        $this->assertSame(['init', 'query_vars', 'template_redirect'], $this->registeredHooks());
    }

    // Who may save is the route's policy (site administrators); see AdminAccessTest.
    public function test_turning_landing_pages_on_drops_the_rewrite_rules()
    {
        update_option('rewrite_rules', ['^calendars/?$' => 'index.php?fe_route=calendars']);

        $this->save('yes');

        $this->assertTrue(Settings::landingPagesEnabled());
        $this->assertFalse(get_option('rewrite_rules'));
    }

    public function test_saving_without_toggling_keeps_the_rewrite_rules()
    {
        update_option('rewrite_rules', $rules = ['^calendars/?$' => 'index.php?fe_route=calendars']);

        $this->save('no');

        $this->assertSame($rules, get_option('rewrite_rules'));
    }

    public function test_the_admin_topbar_links_the_frontend_portal_only_while_landing_pages_are_on()
    {
        Functions\when('user_trailingslashit')->alias(function ($path) {
            return rtrim($path, '/') . '/';
        });
        $menu = new class extends AdminMenuHandler {
            public function __construct()
            {
                $this->baseUrl = 'admin.php?page=fluent-event#/';
            }

            public function items()
            {
                return array_column($this->getMenuItemsList(), 'permalink', 'key');
            }
        };

        $this->assertArrayNotHasKey('frontend_portal', $menu->items());

        Settings::update(Settings::GENERAL, ['enable_landing_pages' => 'yes']);

        $this->assertSame('https://example.com/events/', $menu->items()['frontend_portal']);
    }
}
