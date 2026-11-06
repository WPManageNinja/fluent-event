<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Support\Arr;

/**
 * Creating an event, and shaping one for the current viewer — meeting link, guest count, their own
 * status — the same way for the community portal and the public event page.
 */
class EventService
{
    /**
     * @param array $data Output of EventRequest::format().
     */
    public static function createEvent(array $data, $authorId = null)
    {
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        // The post and its details row must land together — a post without its
        // typed row is an event the app cannot read.
        return DB::transaction(function () use ($data, $timezone, $authorId) {
            return Event::create([
                'post_id'         => PostService::createEventPost($data, $authorId),
                'calendar_id'     => Arr::get($data, 'calendar_id'),
                'parent_event_id' => null,
                'status'          => Arr::get($data, 'status'),
                'start_at'        => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'          => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'        => $timezone,
                'venue_type'      => Arr::get($data, 'venue_type'),
                'meta'            => Arr::get($data, 'meta', []),
            ]);
        });
    }

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

        $isAdmin = $userId && user_can($userId, 'manage_options');

        $formatted = [];

        foreach ($events as $event) {
            $eventId = (int) $event->id;

            $formatted[$eventId] = self::formatEvent(
                $event,
                $seats[$eventId] ?? 0,
                $myStatuses[$eventId] ?? null,
                $isAdmin,
                $isAdmin
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
            'slug'               => $event->post->post_name ?? '',
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
