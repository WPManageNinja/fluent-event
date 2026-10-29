<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Support\Arr;

class EventService
{
    /**
     * @param array $data Output of EventRequest::format().
     */
    public static function createEvent(array $data, $authorId = null)
    {
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        // The post and its details row must land together — a post without its
        // typed row is an event the app cannot read.
        return DB::transaction(function () use ($data, $timezone, $authorId) {
            $event = Event::create([
                'post_id'         => PostService::createEventPost($data, $authorId),
                'calendar_id'     => Arr::get($data, 'calendar_id'),
                'parent_event_id' => null,
                'status'          => Arr::get($data, 'status'),
                'start_at'        => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'          => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'        => $timezone,
                'venue_type'      => Arr::get($data, 'venue_type'),
                'meta'            => Arr::get($data, 'meta', []),
            ]);

            VenueService::linkEvent($event->post_id, Arr::get($data, 'venue_id'));

            return $event;
        });
    }
}
