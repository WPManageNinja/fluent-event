<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentEvent\App\Services\CalendarService;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\App\Services\Settings;

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

    public static function portalVars($vars)
    {
        $vars['has_fluent_event'] = true;
        $vars['fluent_event_version'] = FLUENT_EVENT_PLUGIN_VERSION;
        $vars['fluent_event_settings'] = [
            'default_view' => Settings::get()['default_events_view'],
        ];

        $userId = get_current_user_id();

        $vars['fluent_event_permissions'] = [
            'write_events'    => $userId && PermissionManager::userCan('write_events', $userId),
            'read_events'     => $userId && PermissionManager::userCan(['read_events', 'write_events'], $userId),
            'write_calendars' => $userId && PermissionManager::userCan('write_calendars', $userId),
            'read_calendars'  => $userId && PermissionManager::userCan(['read_calendars', 'write_calendars'], $userId),
        ];

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
