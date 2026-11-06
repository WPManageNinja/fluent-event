<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentEvent\App\Services\CalendarService;

class SpaceHandler
{
    public function register()
    {
        // before_delete, not deleted: fcom bulk-deletes the space's posts with no per-post hooks.
        add_action('fluent_community/space/before_delete', [$this, 'deleteCalendar']);
    }

    public function deleteCalendar($space)
    {
        $calendar = $space ? CalendarService::findBySpace($space->id) : null;

        if (!$calendar) {
            return;
        }

        do_action('fluent_event/calendar/before_deleted', $calendar);

        $calendar->delete();

        do_action('fluent_event/calendar/deleted', $calendar);
    }
}
