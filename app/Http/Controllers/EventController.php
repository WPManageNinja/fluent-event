<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Services\EventService;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\Framework\Http\Request\Request;

class EventController extends Controller
{
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
