<?php

namespace FluentEvent\Tests\Unit\Http;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\LandingPageHandler;
use FluentEvent\App\Http\Controllers\SettingsController;
use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Framework\Validator\Validator;
use FluentEvent\Tests\TestCase;

class RootPortalTest extends TestCase
{
    /**
     * @dataProvider rootRequests
     */
    public function test_the_root_portal_takes_only_the_home_page_and_urls_wordpress_could_not_resolve($request, $is404, $isFront, $route, $isFeed = false)
    {
        $GLOBALS['wp'] = (object) ['request' => $request];
        Functions\when('is_404')->justReturn($is404);
        Functions\when('is_front_page')->justReturn($isFront);
        Functions\when('is_feed')->justReturn($isFeed);

        $handler = new class extends LandingPageHandler {
            public function route()
            {
                return $this->rootRoute();
            }
        };

        $this->assertSame($route, $handler->route());
    }

    public function rootRequests()
    {
        return [
            'home page'            => ['', false, true, ['calendars', '', '']],
            'home page feed'       => ['', false, true, ['', '', ''], true],
            'search at the root'   => ['', false, false, ['', '', '']],
            'a page keeps its url' => ['about', false, false, ['', '', '']],
            'unresolved calendar'  => ['team', true, false, ['calendar', 'team', '']],
            'unresolved event'     => ['team/launch', true, false, ['event', 'team', 'launch']],
            'deeper than an event' => ['team/launch/more', true, false, ['', '', '']],
        ];
    }

    public function test_the_root_portal_adds_no_rewrite_rules()
    {
        Settings::update(Settings::GENERAL, ['enable_landing_pages' => 'yes', 'portal_slug' => '']);
        Functions\expect('add_rewrite_rule')->never();

        (new LandingPageHandler())->addRewriteRules();
    }

    public function test_a_blank_slug_moves_the_portal_to_the_site_root()
    {
        Functions\when('current_user_can')->justReturn(true);

        $this->save('');

        $this->assertSame('', Settings::portalSlug());
    }

    public function test_a_slug_that_sanitizes_to_nothing_is_rejected_not_taken_as_the_root()
    {
        Functions\when('current_user_can')->justReturn(true);

        $this->assertSame(422, $this->save('!!!')['status']);
        $this->assertSame('events', Settings::portalSlug());
    }

    private function save($slug)
    {
        Functions\when('sanitize_title')->alias(function ($value) {
            return trim(preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-');
        });

        $request = new class($slug) extends Request {
            private $slug;

            public function __construct($slug)
            {
                $this->slug = $slug;
            }

            public function all()
            {
                return ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => 'yes', 'portal_slug' => $this->slug];
            }
        };

        $controller = new class extends SettingsController {
            public function __construct()
            {
            }

            public function validate($data, $rules, $messages = [])
            {
                if (Validator::make($data, $rules, $messages)->fails()) {
                    throw new \DomainException('invalid', 422);
                }

                return $data;
            }
        };

        try {
            return $controller->updateGeneral($request);
        } catch (\DomainException $e) {
            return ['status' => $e->getCode()];
        }
    }
}
