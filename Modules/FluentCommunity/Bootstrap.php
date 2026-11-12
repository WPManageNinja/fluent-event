<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentEvent\Framework\Support\Arr;

/**
 * Hooked to `fluent_community/fluent_event/init`, which fcom fires only while FluentEvent is
 * enabled in its Features, passing its FluentEvent settings; otherwise FluentEvent stays standalone.
 */
class Bootstrap
{
    public static function register($app = null, $settings = [])
    {
        $routeSlug = sanitize_title((string) Arr::get((array) $settings, 'route_slug', '')) ?: 'events';

        (new PortalHandler($routeSlug))->register();
        (new FeedHandler())->register();
        (new SpaceHandler())->register();
    }
}
