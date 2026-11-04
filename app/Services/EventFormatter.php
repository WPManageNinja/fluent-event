<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Framework\Support\Arr;

/**
 * An event as the current viewer may see it, for the community portal and the public event page
 * alike, so the meeting link, the guest count and the viewer's own status follow one set of rules.
 */
class EventFormatter
{
    protected static function editUrl($eventId)
    {
        return admin_url('admin.php?page=fluent-event#/events/' . (int) $eventId . '/edit');
    }

    public static function seatCounts(array $eventIds)
    {
        if (!$eventIds) {
            return [];
        }

        $rows = Guest::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', Event::SEAT_STATUSES)
            ->selectRaw('event_id, COUNT(*) AS total')
            ->groupBy('event_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->event_id] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Portal-ready event data keyed by event id. Draft events are left out: they are
     * still being built, so the community never sees them.
     *
     * @param Event[] $events  Loaded with the `post` relation.
     * @param array   $options withDescription: add the kses'd description, which only the event page shows.
     */
    public static function formatEvents($events, $options = [])
    {
        $events = array_filter((array) $events, function ($event) {
            return $event && $event->status !== 'draft';
        });

        if (!$events) {
            return [];
        }

        $eventIds = array_map(function ($event) {
            return (int) $event->id;
        }, $events);

        $userId = get_current_user_id();
        $seats = self::seatCounts($eventIds);
        $myStatuses = [];

        if ($userId) {
            $guests = Guest::query()
                ->whereIn('event_id', $eventIds)
                ->where('user_id', $userId)
                ->get(['event_id', 'status']);

            foreach ($guests as $guest) {
                $myStatuses[(int) $guest->event_id] = $guest->status;
            }
        }

        $isStaff = $userId && PermissionManager::userCan(['read_guests', 'write_guests', 'read_events', 'write_events'], $userId);
        $canEdit = $userId && PermissionManager::userCan('write_events', $userId);

        $formatted = [];

        foreach ($events as $event) {
            $eventId = (int) $event->id;

            $formatted[$eventId] = self::formatEvent(
                $event,
                $seats[$eventId] ?? 0,
                $myStatuses[$eventId] ?? null,
                $isStaff,
                $canEdit
            );

            if (Arr::get($options, 'withDescription')) {
                $formatted[$eventId]['description'] = $event->description;
            }
        }

        return $formatted;
    }

    public static function formatEvent($event, $seats, $myStatus, $isStaff, $canEdit)
    {
        $meta = array_merge(Event::defaultMeta(), (array) $event->meta);
        $takesRegistrations = in_array($meta['entry_type'], ['rsvp', 'open'], true);
        $isRegistered = in_array($myStatus, Event::SEAT_STATUSES, true);
        $isPast = $event->end_at < gmdate(DateTimeHelper::FORMAT);
        $hasMeetingUrl = $event->venue_type === 'online' && !empty($meta['online_meeting_url']);

        return [
            'id'                 => (int) $event->id,
            'title'              => $event->title,
            'status'             => $event->status,
            'start_at'           => $event->start_at,
            'end_at'             => $event->end_at,
            'start_at_local'     => $event->start_at_local,
            'end_at_local'       => $event->end_at_local,
            'start_at_site'      => $event->start_at_site,
            'end_at_site'        => $event->end_at_site,
            'timezone'           => $event->timezone,
            'venue_type'         => $event->venue_type,
            'venue'              => $meta['venue'],
            'address'            => $meta['address'],
            'cover_url'          => $meta['cover_url'],
            'has_meeting_url'    => $hasMeetingUrl,
            // Only a no-entry event shows its link to every viewer; otherwise it is for guests and staff.
            'online_meeting_url' => $hasMeetingUrl && ($meta['entry_type'] === 'none' || $isRegistered || $isStaff) ? $meta['online_meeting_url'] : '',
            'entry_type'         => $meta['entry_type'],
            'guest_count'        => $takesRegistrations && $meta['show_guest_count'] === 'yes' ? $seats : null,
            'is_past'            => $isPast,
            'my_status'          => $myStatus,
            'registration_open'  => $takesRegistrations && !$isPast,
            // Bound to the event: a visitor's open registration sends it back.
            'register_nonce'     => $meta['entry_type'] === 'open' && !$isPast ? wp_create_nonce('fe_guest_register_' . $event->id) : '',
            'edit_url'           => $canEdit ? self::editUrl($event->id) : '',
        ];
    }
}
