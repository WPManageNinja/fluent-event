<?php

namespace FluentEvent\Tests\Unit\Modules;

use FluentEvent\Modules\FluentCommunity\PortalHandler;
use FluentEvent\Tests\TestCase;

/**
 * With the portal at the site root, fcom renders a path on reload only when its
 * first segment is in fluent_community/app_route_paths; anything else is a WordPress 404.
 */
class PortalRoutePathsTest extends TestCase
{
    public function test_reloading_any_top_level_portal_route_serves_the_portal()
    {
        $script = file_get_contents(dirname(__DIR__, 4) . '/resources/fcom-portal/fcom-portal.js');
        preg_match_all("/routes\.push\(\{\s*path: '\/([a-z-]+)/", $script, $matches);

        $this->assertContains('create-new-event', $matches[1]);

        $paths = (new PortalHandler())->addRoutePath([]);

        foreach ($matches[1] as $segment) {
            $this->assertContains($segment, $paths, "/$segment 404s on reload");
        }
    }
}
