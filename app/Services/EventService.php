<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\CPT\EventPost;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Models\Event;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Foundation\Exceptions\HttpException;

class EventService
{
    public static function createEvent($data)
    {
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        $event = DB::transaction(function () use ($data, $timezone) {
            $postId = self::createWpPost($data);

            return Event::create([
                'post_id'         => $postId,
                'calendar_id'     => Arr::get($data, 'calendar_id'),
                'parent_event_id' => null,
                'status'          => Arr::get($data, 'status'),
                'start_at'        => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'          => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'        => $timezone,
                'venue_type'      => Arr::get($data, 'venue_type'),
                'meta'            => Arr::get($data, 'meta', []),
            ]);
        });

        do_action('fluent_event/event/created', $event);

        return $event;
    }

    protected static function createWpPost($data)
    {
        $postId = wp_insert_post([
            'post_type'    => EventPost::POST_TYPE,
            'post_title'   => Arr::get($data, 'title'),
            'post_content' => Arr::get($data, 'description', ''),
            'post_status'  => Event::postStatusFor(Arr::get($data, 'status')),
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($postId)) {
            throw new HttpException(
                422,
                __('The event could not be saved. Please try again.', 'fluent-event'),
                'event_post_insert_failed'
            );
        }

        return $postId;
    }
}
