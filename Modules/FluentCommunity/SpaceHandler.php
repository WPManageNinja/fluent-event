<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentEvent\App\Services\CalendarService;

class SpaceHandler
{
    public function register()
    {
        // before_delete, not deleted: fcom bulk-deletes the space's posts with no per-post hooks.
        add_action('fluent_community/space/before_delete', [$this, 'deleteCalendar']);
        add_action('fluent_community/space/updated', [$this, 'syncCalendar']);
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

    public function syncCalendar($space)
    {
        if (!CommunityEvents::isDiscussionSpace($space)) {
            return;
        }

        $calendar = CalendarService::findBySpace($space->id);

        if (!$calendar) {
            return;
        }

        $slug = CalendarService::uniqueSlug($space->slug, $calendar->id);

        if ($calendar->title === $space->title && $calendar->slug === $slug) {
            return;
        }

        $calendar->update(['title' => $space->title, 'slug' => $slug]);

        do_action('fluent_event/calendar/updated', $calendar);
    }
}
