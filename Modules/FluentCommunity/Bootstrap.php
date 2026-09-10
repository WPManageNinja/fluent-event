<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentEvent\App\Services\CalendarService;

class Bootstrap
{
    public static function register()
    {
        if (!static::isCommunityActive()) {
            return;
        }

        add_filter('fluent_community/portal_vars', [self::class, 'portalVars']);

        add_action('fluent_community/space/deleted', [self::class, 'onSpaceDeleted']);
    }

    /** Constant AND class: a half-loaded fcom defines one without the other. */
    protected static function isCommunityActive()
    {
        return defined('FLUENT_COMMUNITY_PLUGIN_VERSION')
            && class_exists(\FluentCommunity\App\App::class)
            && \FluentCommunity\App\App::getInstance();
    }

    /** So their Vue feature-detects instead of sniffing our plugin version. */
    public static function portalVars($vars)
    {
        $vars['has_fluent_event'] = true;
        $vars['fluent_event_version'] = FLUENT_EVENT_PLUGIN_VERSION;

        return $vars;
    }

    /* Just unlink */
    public static function onSpaceDeleted($spaceId)
    {
        $calendar = CalendarService::matchSpace((int) $spaceId);

        if (!$calendar) {
            return;
        }

        $meta = (array) $calendar->meta;
        unset($meta[CalendarService::SPACE_META_KEY]);

        $calendar->meta = $meta;
        $calendar->save();

        do_action('fluent_event/calendar/unlinked', $calendar, (int) $spaceId);
    }
}
