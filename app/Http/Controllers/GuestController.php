<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\EventReminderService;
use FluentEvent\App\Services\RegistrationService;
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
            'source'        => 'manual',
        ]);

        do_action('fluent_event/guest/created', $guest);

        if ($request->getSafe('send_email', 'rest_sanitize_boolean')) {
            RegistrationService::queueEmail($guest, null);
        }

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

        $previousStatus = $guest->status;

        // Partial: a field left out of the request keeps its value; an explicit null still clears user_id.
        $fields = Arr::only($data, ['name', 'email', 'user_id', 'status']);

        if (array_key_exists('user_id', $fields)) {
            $fields['user_id'] = $fields['user_id'] ?: null;
        }

        if (array_key_exists('status', $fields)) {
            $fields['status'] = $fields['status'] ?: Guest::DEFAULT_STATUS;
            $fields['checked_in_at'] = $fields['status'] === 'checked_in'
                ? ($guest->checked_in_at ?: current_time('mysql'))
                : null;
        }

        $guest->fill($fields)->save();

        do_action('fluent_event/guest/updated', $guest, $previousStatus);

        // The member flow's rules: a confirmation on taking a seat, a cancellation only on giving one up.
        if ($request->getSafe('send_email', 'rest_sanitize_boolean')) {
            RegistrationService::queueEmail($guest, $previousStatus);
        }

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

        // Sent now, not queued: the row is gone, so a background job couldn't load it.
        if (
            $request->getSafe('send_email', 'rest_sanitize_boolean')
            && $guest->status === 'going'
        ) {
            EventReminderService::sendCancellation($guest);
        }

        return [
            'message' => __('Guest has been successfully deleted', 'fluent-event')
        ];
    }

    protected function guestCounts($eventId, $search = '')
    {
        $rows = Guest::query()
            ->where('event_id', $eventId)
            ->searchBy($search)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get();

        // A status with no rows is absent from the GROUP BY, but its tab still
        // has to read zero, so seed every known status first.
        $counts = array_fill_keys(explode(',', Guest::STATUSES), 0);
        $all = 0;

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $all += $total;

            if (isset($counts[$row->status])) {
                $counts[$row->status] = $total;
            }
        }

        return ['all' => $all] + $counts;
    }

    protected function findGuest($eventId, $guestId)
    {
        $event = Event::query()->findOrFail($eventId);

        return Guest::query()
            ->where('event_id', $event->id)
            ->findOrFail($guestId);
    }
}
