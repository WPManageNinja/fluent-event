<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\EventService;
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
        $from = $request->getSafe('start_date', 'sanitize_text_field');
        $to = $request->getSafe('end_date', 'sanitize_text_field');

        $events = Event::query()
            ->with([
                'post'        => function ($query) {
                    $query->select(['ID', 'post_title']);
                },
                'calendar'    => function ($query) {
                    $query->select(['id', 'title', 'meta']);
                },
            ])
            // The cards' "N guests": every guest row, as on the calendars list.
            ->withCount('guests')
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->byCalendar($request->getSafe('calendar_id', 'intval'))
            ->byStatus($request->getSafe('status', 'sanitize_text_field'))
            ->inDateRange($from, $to)
            ->byPeriod($request->getSafe('period', 'sanitize_text_field'));

        // As in the community calendar: only a real window of at most two months skips paging.
        $fromTime = DateTimeHelper::isDate($from) ? strtotime($from) : false;
        $toTime = DateTimeHelper::isDate($to) ? strtotime($to) : false;
        $isWindow = $fromTime && $toTime && $toTime >= $fromTime && $toTime - $fromTime <= 62 * DAY_IN_SECONDS;

        $paginate = !$isWindow || $request->getSafe('paginate', 'rest_sanitize_boolean', true);

        $data = [
            'events' => $paginate
                ? $events->paginate(EventRequest::perPage($request))
                : $events->get()
        ];

        /**
         * The admin events list, for add-ons to append fields to each event.
         *
         * @param array $data    ['events' => paginator or collection of Event]
         * @param array $request The request parameters.
         */
        return apply_filters('fluent_event/events_api_response', $data, $request->all());
    }

    public function getEvent(Request $request, $id)
    {
        $event = Event::query()
            ->with([
                'post'        => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content', 'post_name']);
                },
                'calendar'    => function ($query) {
                    $query->select(['id', 'title', 'status', 'meta']);
                },
            ])
            // The Overview's "N guests", counted as on the list cards.
            ->withCount('guests')
            ->findOrFail($id);

        // `post` is hidden from JSON, so "Clone Event" reads the slug from here.
        $event->slug = $event->post->post_name;

        $data = [
            'event'      => $event->append('description'),
            'public_url' => EventPageService::url($event),
            'shortcode'  => '[fluent_event event="' . $event->post->post_name . '"]',
        ];

        /**
         * One event, as the admin form and the portal edit page load it, for add-ons to append fields.
         *
         * @param array $data  ['event' => Event, 'public_url' => string, 'shortcode' => string]
         * @param Event $event
         */
        return apply_filters('fluent_event/event_api_response', $data, $event);
    }

    // The portal edit page is addressed by the event's slug, as its view page is.
    public function getEventBySlug(Request $request)
    {
        $slug = $request->getSafe('slug', 'sanitize_title');

        $eventId = $slug ? Event::query()->whereHas('post', function ($query) use ($slug) {
            $query->where('post_name', $slug);
        })->value('id') : null;

        return $this->getEvent($request, (int) $eventId);
    }

    public function createEvent(Request $request)
    {
        $data = EventRequest::sanitize($request->all());
        $this->validate($data, EventRequest::rules($data), EventRequest::messages());
        EventRequest::validateDateWindow($data);

        $event = EventService::createEvent(EventRequest::format($data));

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

        $previousCalendarId = (int) $event->calendar_id;
        $calendarId         = (int) Arr::get($data, 'calendar_id');

        DB::transaction(function () use ($event, $data, $timezone, $calendarId, $previousCalendarId) {
            PostService::updateEventPost($event->post_id, $data);

            $event->fill([
                'calendar_id' => $calendarId,
                'status'      => Arr::get($data, 'status'),
                'start_at'    => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'      => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'    => $timezone,
                'venue_type'  => Arr::get($data, 'venue_type'),
                'meta'        => Arr::get($data, 'meta', []),
            ]);

            $event->save();

            if ($calendarId !== $previousCalendarId) {
                Guest::query()
                    ->where('event_id', $event->id)
                    ->update(['calendar_id' => $calendarId]);
            }
        });

        do_action('fluent_event/event/updated', $event);

        if ($calendarId !== $previousCalendarId) {
            do_action('fluent_event/event/calendar_changed', $event, $previousCalendarId);
        }

        return [
            'message' => __('Event has been successfully updated', 'fluent-event'),
            'event'   => $event
        ];
    }

    public function deleteEvent(Request $request, $id)
    {
        $event = Event::query()->findOrFail($id);

        DB::transaction(function () use ($event) {
            do_action('fluent_event/event/before_deleted', $event);

            $event->delete();
        });

        do_action('fluent_event/event/deleted', $event);

        return [
            'message' => __('Event has been successfully deleted', 'fluent-event')
        ];
    }
}
