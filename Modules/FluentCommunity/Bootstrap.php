<?php

namespace FluentEvent\Modules\FluentCommunity;

/**
 * Hooked to `fluent_community/fluent_event/init`, which fcom fires only while FluentEvent is
 * enabled in its Features; otherwise FluentEvent stays standalone.
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
