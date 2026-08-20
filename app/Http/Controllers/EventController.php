<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Http\Requests\EventRequest;
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

        return $this->sendSuccess([
            'events' => $paginate
                ? $events->paginate(EventRequest::perPage($request))
                : $events->get()
        ]);
    }

    public function createEvent(Request $request)
    {
        $data = EventRequest::sanitize($request->all());
        $this->validate($data, EventRequest::createRules($data), EventRequest::messages());
        EventRequest::validateDateWindow($data);

        $event = EventService::createEvent(EventRequest::format($data));

        return $this->sendSuccess([
            'message' => __('Event has been successfully created', 'fluent-event'),
            'event'   => $event
        ]);
    }
}
