<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\GuestService;
use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\Framework\Http\Request\Request;

class GuestController extends Controller
{
    public function getGuests(Request $request, $eventId)
    {
        $event = Event::query()->findOrFail($eventId);

        [$orderBy, $orderType] = GuestRequest::sorting($request);

        $search = $request->getSafe('search', 'sanitize_text_field');

        $guests = Guest::query()
            ->where('event_id', $event->id)
            ->searchBy($search)
            ->byStatus($request->getSafe('status', 'sanitize_text_field'))
            ->orderBy($orderBy, $orderType)
            ->paginate(GuestRequest::perPage($request), GuestRequest::COLUMNS);

        return $this->sendSuccess([
            'guests' => $guests,
            'counts' => Guest::countsFor($event->id, $search)
        ]);
    }

    public function createGuest(Request $request, $eventId)
    {
        $event = Event::query()->findOrFail($eventId);

        $data = GuestRequest::sanitize($request->all());
        $this->validate($data, GuestRequest::createRules($event->id), GuestRequest::messages());

        $guest = GuestService::createGuest($event, $data);

        return $this->sendSuccess([
            'message' => __('Guest has been successfully saved', 'fluent-event'),
            'guest'   => $guest
        ]);
    }

    public function updateGuest(Request $request, $eventId, $guestId)
    {
        $guest = $this->findGuest($eventId, $guestId);

        $data = GuestRequest::sanitize($request->all());
        $this->validate(
            $data,
            GuestRequest::updateRules($guest->event_id, $guest->id),
            GuestRequest::messages()
        );

        $guest = GuestService::updateGuest($guest, $data);

        return $this->sendSuccess([
            'message' => __('Guest has been successfully updated', 'fluent-event'),
            'guest'   => $guest
        ]);
    }

    public function deleteGuest(Request $request, $eventId, $guestId)
    {
        GuestService::deleteGuest($this->findGuest($eventId, $guestId));

        return $this->sendSuccess([
            'message' => __('Guest has been successfully deleted', 'fluent-event')
        ]);
    }

    protected function findGuest($eventId, $guestId)
    {
        $event = Event::query()->findOrFail($eventId);

        return Guest::query()
            ->where('event_id', $event->id)
            ->findOrFail($guestId);
    }
}
