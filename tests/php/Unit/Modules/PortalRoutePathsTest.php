<?php

namespace FluentEvent\Tests\Unit\Modules;

use Brain\Monkey\Functions;
use FluentEvent\Modules\FluentCommunity\PortalHandler;
use FluentEvent\Tests\TestCase;

/**
 * With the portal at the site root, fcom renders a path on reload only when its
 * first segment is in fluent_community/app_route_paths; anything else is a WordPress 404.
 */
class PortalRoutePathsTest extends TestCase
{
    public function test_every_top_level_route_reloads_under_the_slug_fcom_passes()
    {
        $this->assertSame(['spaces', 'meetups'], (new PortalHandler('meetups'))->addRoutePath(['spaces']));
    }

    public function test_a_route_moved_by_the_filter_reloads_too()
    {
        Functions\when('apply_filters')->alias(function ($hook, $paths) {
            $paths['create_event'] = 'new-event';
            $paths['edit_event'] = 'edit-event/:event_slug';

            return $paths;
        });

        $this->assertSame(['events', 'new-event', 'edit-event'], (new PortalHandler())->addRoutePath([]));
    }
}
