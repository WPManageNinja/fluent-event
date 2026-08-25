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
            ->get();

        return [
            'calendars' => $calendars
        ];
    }

    public function getCalendar(Request $request, $id)
    {
        $calendar = Calendar::query()->findOrFail($id);

        $calendar->description = wp_kses_post($calendar->description);

        return [
            'calendar' => $calendar
        ];
    }

    public function createCalendar(Request $request)
    {
        $data = CalendarRequest::sanitize($request->all());

        $this->validate($data, CalendarRequest::createRules(), CalendarRequest::messages());

        $calendar = Calendar::create(CalendarRequest::format($data));

        do_action('fluent_event/calendar/created', $calendar);

        return [
            'message'  => __('Calendar has been successfully created', 'fluent-event'),
            'calendar' => $calendar
        ];
    }

    public function updateCalendar(Request $request, $id)
    {
        $calendar = Calendar::query()->findOrFail($id);

        $data = CalendarRequest::sanitize($request->all());

        $this->validate(
            $data,
            CalendarRequest::updateRules($calendar->id),
            CalendarRequest::messages()
        );

        $calendar->fill(CalendarRequest::format($data, $calendar->meta))->save();

        do_action('fluent_event/calendar/updated', $calendar);

        return [
            'message'  => __('Calendar has been successfully updated', 'fluent-event'),
            'calendar' => $calendar
        ];
    }

    public function deleteCalendar(Request $request, $id)
    {
        $calendar = Calendar::query()->findOrFail($id);

        do_action('fluent_event/calendar/before_deleted', $calendar);

        $calendar->delete();

        do_action('fluent_event/calendar/deleted', $calendar);

        return [
            'message' => __('Calendar has been successfully deleted', 'fluent-event')
        ];
    }
}
