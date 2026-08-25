<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\Framework\Support\Arr;
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

        return [
            'guests' => $guests,
            'counts' => $this->guestCounts($event->id, $search)
        ];
    }

    public function createGuest(Request $request, $eventId)
    {
        $event = Event::query()->findOrFail($eventId);

        $data = GuestRequest::sanitize($request->all());
        $this->validate($data, GuestRequest::createRules($event->id), GuestRequest::messages());

        $status = Arr::get($data, 'status') ?: Guest::DEFAULT_STATUS;

        $guest = Guest::create([
            'event_id'      => $event->id,
            'calendar_id'   => $event->calendar_id,
            'name'          => Arr::get($data, 'name'),
            'email'         => Arr::get($data, 'email'),
            'user_id'       => Arr::get($data, 'user_id') ?: null,
            'status'        => $status,
            'checked_in_at' => $status === 'checked_in' ? current_time('mysql') : null,
            'group_id'      => wp_generate_password(32, false, false),
            'is_buyer'      => 1,
            'source'        => Arr::get($data, 'source', 'community'),
        ]);

        do_action('fluent_event/guest/created', $guest);

        return [
            'message' => __('Guest has been successfully saved', 'fluent-event'),
            'guest'   => $guest
        ];
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

        $status = Arr::get($data, 'status') ?: Guest::DEFAULT_STATUS;

        $guest->fill([
            'name'          => Arr::get($data, 'name'),
            'email'         => Arr::get($data, 'email'),
            'user_id'       => Arr::get($data, 'user_id') ?: null,
            'status'        => $status,
            'checked_in_at' => $status === 'checked_in'
                ? ($guest->checked_in_at ?: current_time('mysql'))
                : null,
        ])->save();

        do_action('fluent_event/guest/updated', $guest);

        return [
            'message' => __('Guest has been successfully updated', 'fluent-event'),
            'guest'   => $guest
        ];
    }

    public function deleteGuest(Request $request, $eventId, $guestId)
    {
        $guest = $this->findGuest($eventId, $guestId);

        do_action('fluent_event/guest/before_deleted', $guest);

        $guest->delete();

        do_action('fluent_event/guest/deleted', $guest);

        return [
            'message' => __('Guest has been successfully deleted', 'fluent-event')
        ];
    }

    protected function guestCounts($eventId, $search = '')
    {
        $scoped = function () use ($eventId, $search) {
            return Guest::query()->where('event_id', $eventId)->searchBy($search);
        };

        $counts = ['all' => $scoped()->count()];

        foreach (explode(',', Guest::STATUSES) as $status) {
            $counts[$status] = $scoped()->where('status', $status)->count();
        }

        return $counts;
    }

    protected function findGuest($eventId, $guestId)
    {
        $event = Event::query()->findOrFail($eventId);

        return Guest::query()
            ->where('event_id', $event->id)
            ->findOrFail($guestId);
    }
}
