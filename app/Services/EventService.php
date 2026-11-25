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
        $startAt = DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone);
        $endAt = DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone);

        $meta = Arr::get($data, 'meta', []);
        $window = self::calculateRegistrationWindow($startAt, $endAt, $timezone, Arr::get($meta, 'registration_opens'), Arr::get($meta, 'registration_closes'));
        $meta['registration_opens_at'] = $window['opens_at'];
        $meta['registration_closes_at'] = $window['closes_at'];

        // The post and its details row must land together — a post without its
        // typed row is an event the app cannot read.
        return DB::transaction(function () use ($data, $timezone, $authorId, $startAt, $endAt, $meta) {
            return Event::create([
                'post_id'         => PostService::createEventPost($data, $authorId),
                'calendar_id'     => Arr::get($data, 'calendar_id'),
                'parent_event_id' => null,
                'status'          => Arr::get($data, 'status'),
                'start_at'        => $startAt,
                'end_at'          => $endAt,
                'timezone'        => $timezone,
                'venue_type'      => Arr::get($data, 'venue_type'),
                'meta'            => $meta,
            ]);
        });
    }

    /**
     * When registration opens and closes, from the event's times and its relative window. Counted back on the
     * event's own clock, so "7 days before" keeps its wall-clock time across a DST change. Stored on every save;
     * anything that moves the start, end or timezone must store it again.
     *
     * @param string     $startAt  UTC 'Y-m-d H:i:s'.
     * @param string     $endAt    UTC 'Y-m-d H:i:s'.
     * @param string     $timezone The event's timezone.
     * @param array|null $opens    {value, unit} before the start; null opens at the start itself.
     * @param array|null $closes   {value, unit} before the start; null closes at the end.
     * @return array ['opens_at' => UTC string, 'closes_at' => UTC string], both always set.
     */
    public static function calculateRegistrationWindow($startAt, $endAt, $timezone, $opens, $closes)
    {
        $timezone = DateTimeHelper::getValidatedTimeZone($timezone);
        $localStart = strtotime(DateTimeHelper::convertFromUtc($startAt, $timezone) . ' UTC');

        $before = function ($offset) use ($localStart, $timezone) {
            $local = gmdate(DateTimeHelper::FORMAT, $localStart - Settings::reminderInSeconds($offset));
            $at = strtotime(DateTimeHelper::convertToUtc($local, $timezone) . ' UTC');

            // A time the clocks skip (2:30 on a spring-forward night) would read as an hour later and could open
            // after closing; it counts as the moment the clocks jump instead.
            $skipped = strtotime(DateTimeHelper::convertFromUtc(gmdate(DateTimeHelper::FORMAT, $at), $timezone) . ' UTC') - strtotime($local . ' UTC');

            if ($skipped > 0) {
                $jumps = (new \DateTimeZone($timezone))->getTransitions($at - $skipped, $at);
                $at = end($jumps)['ts'];
            }

            return gmdate(DateTimeHelper::FORMAT, $at);
        };

        return [
            'opens_at'  => $opens ? $before($opens) : $startAt,
            'closes_at' => $closes ? $before($closes) : $endAt,
        ];
    }

    public static function editUrl($eventId)
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
            $email = wp_get_current_user()->user_email;
            // The member's own rows, else an unlinked one under their email, as RegistrationService::memberStatus() reads.
            $guests = Guest::query()
                ->whereIn('event_id', $eventIds)
                ->where(function ($query) use ($userId, $email) {
                    $query->where('user_id', $userId)->orWhere(function ($query) use ($email) {
                        $query->whereNull('user_id')->where('email', $email);
                    });
                })
                ->get(['event_id', 'status', 'user_id']);

            foreach ($guests as $guest) {
                if ($guest->user_id || !isset($myStatuses[(int) $guest->event_id])) {
                    $myStatuses[(int) $guest->event_id] = $guest->status;
                }
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
        $capacity = $takesRegistrations ? (int) $meta['capacity'] : 0;

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
            'capacity'           => $capacity ?: null,
            // Null unless the host shows the spots left on a capped event.
            'spots_left'         => $capacity > 0 && $meta['show_spots_left'] === 'yes' ? max(0, $capacity - $seats) : null,
            'is_full'            => $capacity > 0 && $seats >= $capacity,
            // A full event then takes new sign-ups onto its waitlist.
            'waitlist'           => $capacity > 0 && $meta['waitlist'] === 'yes',
            'start_display'      => $meta['start_display'],
            'is_past'            => $isPast,
            'my_status'          => $myStatus,
            'registration_open'  => $takesRegistrations && !$isPast,
            // Bound to the event: a visitor's open registration sends it back.
            'register_nonce'     => $meta['entry_type'] === 'open' && !$isPast ? wp_create_nonce('fevent_guest_register_' . $event->id) : '',
            'edit_url'           => $canEdit ? self::editUrl($event->id) : '',
        ];
    }
}
