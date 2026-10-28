<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\EventReminderService;
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

        if (in_array($status, ['going', 'waitlisted'], true) && $request->getSafe('send_email', 'rest_sanitize_boolean')) {
            $this->queueStatusEmail($guest);
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
        $status = $guest->status;

        do_action('fluent_event/guest/updated', $guest, $previousStatus);

        if ($status !== $previousStatus && $request->getSafe('send_email', 'rest_sanitize_boolean')) {
            $this->queueStatusEmail($guest);
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

    // One page of selected guests at a time: change their status, or delete them.
    // The writes are single queries, so model events don't fire; the bulk hooks are the contract.
    public function handleBulkActions(Request $request, $eventId)
    {
        $event = Event::query()->findOrFail($eventId);

        $data = GuestRequest::sanitize($request->all());
        $this->validate($data, GuestRequest::bulkRules($data), GuestRequest::messages());

        $guests = Guest::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $data['guest_ids'])
            ->get();

        if ($data['action'] === 'delete') {
            if ($guests->isNotEmpty()) {
                do_action('fluent_event/guest/before_bulk_deleted', $guests, $event);

                Guest::query()->whereIn('id', $guests->modelKeys())->delete();

                do_action('fluent_event/guest/bulk_deleted', $guests, $event);
            }

            return [
                /* translators: %d: number of guests deleted */
                'message' => sprintf(_n('%d guest has been deleted.', '%d guests have been deleted.', count($guests), 'fluent-event'), count($guests)),
            ];
        }

        $changed = $guests->filter(function ($guest) use ($data) {
            return $guest->status !== $data['status'];
        })->values();

        if ($changed->isNotEmpty()) {
            $previousStatuses = $changed->pluck('status', 'id')->all();

            // Guests already checked in were filtered out above, so every one here gets a fresh time.
            $values = [
                'status'        => $data['status'],
                'checked_in_at' => $data['status'] === 'checked_in' ? current_time('mysql') : null,
            ];

            Guest::query()->whereIn('id', $changed->modelKeys())->update($values);

            foreach ($changed as $guest) {
                $guest->fill($values);
            }

            do_action('fluent_event/guest/bulk_updated', $changed, $previousStatuses, $event);

            if ($request->getSafe('send_email', 'rest_sanitize_boolean')) {
                foreach ($changed as $guest) {
                    $this->queueStatusEmail($guest);
                }
            }
        }

        return [
            /* translators: %d: number of guests whose status changed */
            'message' => sprintf(_n('%d guest has been updated.', '%d guests have been updated.', count($changed), 'fluent-event'), count($changed)),
        ];
    }

    // Each email is its own background job, so a bulk change never sends in a loop on the request.
    protected function queueStatusEmail($guest)
    {
        if ($guest->status === 'going') {
            as_enqueue_async_action('fluent_event/send_registration_confirmation', [(int) $guest->id], 'fluent-event');
        } elseif (in_array($guest->status, ['not_going', 'cancelled'], true)) {
            as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
        } elseif ($guest->status === 'waitlisted') {
            as_enqueue_async_action('fluent_event/send_registration_waitlist', [(int) $guest->id], 'fluent-event');
        }
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
