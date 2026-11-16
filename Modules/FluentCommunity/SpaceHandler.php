<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Models\BaseSpace as FcomBaseSpace;
use FluentEvent\App\Services\CalendarService;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Support\Arr;

class SpaceHandler
{
    public function register()
    {
        // fcom bulk-deletes the space's posts with no per-post hooks, so the per-post park never runs.
        add_action('fluent_community/space/before_delete', [$this, 'parkCalendar']);
        add_filter('fluent_event/calendar_api_response', [$this, 'addSpaceUrl']);
        add_filter('fluent_event/calendars_api_response', [$this, 'addSpaceUrls']);
    }

    public function addSpaceUrl($data)
    {
        $spaceId = (int) Arr::get((array) $data['calendar']->meta, CalendarService::SPACE_META_KEY);
        $space = $spaceId ? FcomBaseSpace::query()->find($spaceId) : null;

        if (CommunityEvents::isDiscussionSpace($space)) {
            $data['space_url'] = esc_url_raw($space->getPermalink());
        }

        return $data;
    }

    // One query for the list's spaces, not one per row.
    public function addSpaceUrls($data)
    {
        $spaceIds = [];

        foreach ($data['calendars'] as $calendar) {
            $spaceIds[$calendar->id] = (int) Arr::get((array) $calendar->meta, CalendarService::SPACE_META_KEY);
        }

        $spaceIds = array_filter($spaceIds);

        if (!$spaceIds) {
            return $data;
        }

        $spaces = FcomBaseSpace::query()->whereIn('id', array_unique($spaceIds))->get()->keyBy('id');

        foreach ($data['calendars'] as $calendar) {
            $space = $spaces->get(Arr::get($spaceIds, $calendar->id));

            if (CommunityEvents::isDiscussionSpace($space)) {
                $calendar->space_url = esc_url_raw($space->getPermalink());
            }
        }

        return $data;
    }

    // Like a deleted post: the events stay as drafts with their guests, and an admin decides what to delete.
    public function parkCalendar($space)
    {
        $calendar = $space ? CalendarService::findBySpace($space->id) : null;

        if (!$calendar) {
            return;
        }

        $events = $calendar->events()->get();

        DB::transaction(function () use ($calendar, $events) {
            foreach ($events as $event) {
                CommunityEvents::parkEvent($event);
            }

            $calendar->status = 'draft';
            $calendar->meta = Arr::except((array) $calendar->meta, [CalendarService::SPACE_META_KEY]);
            $calendar->save();
        });

        foreach ($events as $event) {
            do_action('fluent_event/event/updated', $event);
        }

        do_action('fluent_event/calendar/updated', $calendar);
    }
}
