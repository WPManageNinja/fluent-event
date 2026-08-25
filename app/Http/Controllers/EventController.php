<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\PostService;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Http\Request\Request;

class EventController extends Controller
{
    public function getAllEvents(Request $request)
    {
        $events = Event::query()
            ->with([
                'post'        => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content', 'post_author']);
                },
                'post.author' => function ($query) {
                    $query->select(['ID', 'display_name']);
                },
                'calendar'    => function ($query) {
                    $query->select(['id', 'title', 'meta']);
                },
            ])
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->byCalendar($request->getSafe('calendar_id', 'intval'))
            ->byStatus($request->getSafe('status', 'sanitize_text_field'))
            ->inDateRange(
                $request->getSafe('start_date', 'sanitize_text_field'),
                $request->getSafe('end_date', 'sanitize_text_field')
            )
            ->byPeriod($request->getSafe('period', 'sanitize_text_field'));

        $paginate = $request->getSafe('paginate', 'rest_sanitize_boolean', true);

        return [
            'events' => $paginate
                ? $events->paginate(EventRequest::perPage($request))
                : $events->get()
        ];
    }

    public function getEvent(Request $request, $id)
    {
        $event = Event::query()
            ->with([
                'post'        => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content', 'post_author']);
                },
                'post.author' => function ($query) {
                    $query->select(['ID', 'display_name']);
                },
                'calendar'    => function ($query) {
                    $query->select(['id', 'title', 'status', 'meta']);
                },
            ])
            ->findOrFail($id);

        return [
            'event' => $event
        ];
    }

    public function createEvent(Request $request)
    {
        $data = EventRequest::sanitize($request->all());
        $this->validate($data, EventRequest::rules($data), EventRequest::messages());
        EventRequest::validateDateWindow($data);

        $data     = EventRequest::format($data);
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        // The post and its details row must land together — a post without its
        // typed row is an event the app cannot read.
        $event = DB::transaction(function () use ($data, $timezone) {
            return Event::create([
                'post_id'         => PostService::createEventPost($data),
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

        return [
            'message' => __('Event has been successfully created', 'fluent-event'),
            'event'   => $event
        ];
    }

    public function updateEvent(Request $request, $id)
    {
        $event = Event::query()->findOrFail($id);

        $data = EventRequest::sanitize($request->all());
        $this->validate($data, EventRequest::rules($data), EventRequest::messages());
        EventRequest::validateDateWindow($data);

        $data     = EventRequest::format($data, $event->meta);
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        DB::transaction(function () use ($event, $data, $timezone) {
            PostService::updateEventPost($event->post_id, $data);

            $event->fill([
                'calendar_id' => Arr::get($data, 'calendar_id'),
                'status'      => Arr::get($data, 'status'),
                'start_at'    => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'      => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'    => $timezone,
                'venue_type'  => Arr::get($data, 'venue_type'),
                'meta'        => Arr::get($data, 'meta', []),
            ])->save();
        });

        do_action('fluent_event/event/updated', $event);

        return [
            'message' => __('Event has been successfully updated', 'fluent-event'),
            'event'   => $event
        ];
    }

    public function deleteEvent(Request $request, $id)
    {
        $event = Event::query()->findOrFail($id);

        do_action('fluent_event/event/before_deleted', $event);

        $event->delete();

        do_action('fluent_event/event/deleted', $event);

        return [
            'message' => __('Event has been successfully deleted', 'fluent-event')
        ];
    }
}
