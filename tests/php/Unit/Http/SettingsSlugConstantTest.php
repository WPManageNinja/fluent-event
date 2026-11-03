<?php

namespace FluentEvent\Tests\Unit\Http;

use Brain\Monkey\Functions;
use FluentEvent\App\Http\Controllers\SettingsController;
use FluentEvent\App\Http\Requests\SettingsRequest;
use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Tests\TestCase;

class SettingsSlugConstantTest extends TestCase
{
    public function slugConstants()
    {
        return [
            'sanitising changes it'    => ['Calendars'],
            'it would fail validation' => ['ক্যালেন্ডার'],
        ];
    }

    /**
     * A constant can't be undefined, so each case needs its own process.
     *
     * @dataProvider slugConstants
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_a_slug_constant_leaves_the_other_general_settings_saveable($constant)
    {
        define('FLUENT_EVENT_PORTAL_SLUG', $constant);

        update_option(Settings::WP_OPTION_KEY, ['portal_slug' => 'calendars']);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('sanitize_title')->alias('strtolower');

        $request = new class($constant) extends Request {
            private $slug;

            public function __construct($slug)
            {
                $this->slug = $slug;
            }

            public function all()
            {
                return ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'portal_slug' => $this->slug];
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

        $saved = $controller->updateGeneral($request);

        $this->assertArrayNotHasKey('portal_slug', SettingsRequest::generalRules());
        $this->assertSame($constant, $saved['settings']['portal_slug']);
        $this->assertSame('cards', Settings::get()['default_events_view']);
        $this->assertSame('calendars', Settings::get()['portal_slug']);
    }
}
