<?php

namespace FluentEvent\Modules\FluentCommunity;

/**
 * Hooked to `fluent_community/portal_loaded`, so it only runs while
 * FluentCommunity is active; without it FluentEvent stays standalone.
 */
class Bootstrap
{
    public static function register()
    {
        (new PortalHandler())->register();
        (new FeedHandler())->register();
        (new SpaceHandler())->register();
    }
}
