<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Http\Requests\CalendarRequest;
use FluentEvent\Framework\Http\Request\Request;

class CalendarController extends Controller
{
    public function getAllCalendars(Request $request)
    {
        list($orderBy, $orderType) = CalendarRequest::sorting($request);

        $calendars = Calendar::query()
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->byStatus($request->getSafe('status', 'sanitize_text_field'))
            ->orderBy($orderBy, $orderType)
            ->paginate(CalendarRequest::perPage($request));

        return $this->sendSuccess([
            'calendars' => $calendars
        ]);
    }

    public function getCalendar(Request $request, $id)
    {
        $calendar = Calendar::query()->findOrFail($id);

        return $this->sendSuccess([
            'calendar' => $calendar
        ]);
    }

    public function createCalendar(Request $request)
    {
        $data = CalendarRequest::sanitize($request->all());

        $this->validate($data, CalendarRequest::rules(), CalendarRequest::messages());

        $calendar = Calendar::create(array_merge($data, [
            'created_by' => get_current_user_id()
        ]));

        do_action('fluent_event/calendar_created', $calendar);

        return $this->sendSuccess([
            'message'  => __('Calendar has been successfully created', 'fluent-event'),
            'calendar' => $calendar
        ]);
    }

    public function updateCalendar(Request $request, $id)
    {
        $calendar = Calendar::query()->findOrFail($id);

        $data = CalendarRequest::sanitize($request->all(), $calendar->meta);

        $this->validate($data, CalendarRequest::rules($calendar->id), CalendarRequest::messages());

        $calendar->fill($data)->save();

        do_action('fluent_event/calendar_updated', $calendar);

        return $this->sendSuccess([
            'message'  => __('Calendar has been successfully updated', 'fluent-event'),
            'calendar' => $calendar
        ]);
    }

    public function deleteCalendar(Request $request, $id)
    {
        $calendar = Calendar::query()->findOrFail($id);

        do_action('fluent_event/before_delete_calendar', $calendar);

        $calendar->delete();

        do_action('fluent_event/after_delete_calendar', $id);

        return $this->sendSuccess([
            'message' => __('Calendar has been successfully deleted', 'fluent-event')
        ]);
    }
}
