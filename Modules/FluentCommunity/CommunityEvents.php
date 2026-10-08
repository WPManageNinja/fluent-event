<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Models\Space as FcomSpace;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Framework\Support\Arr;

/**
 * What the community side needs about events: which post carries one, who may
 * see it, and the shape the portal renders. Event data stays in FluentEvent;
 * a post only holds `meta.event_id`.
 */
class CommunityEvents
{
    const CONTENT_TYPE = 'event';
    const FEED_META_KEY = 'fcom_feed_id';

    public static function eventIdOf($feed)
    {
        return (int) Arr::get((array) $feed->meta, 'event_id');
    }

    public static function feedIdOf($event)
    {
        return (int) Arr::get((array) $event->meta, self::FEED_META_KEY);
    }

    public static function isDiscussionSpace($space)
    {
        return $space && $space->type === 'community';
    }

    // Discussion spaces whose posts the current viewer can read.
    public static function visibleSpaceIds()
    {
        $user = FcomHelper::getCurrentUser();

        if (!$user) {
            if (!FcomHelper::isPublicAccessible()) {
                return [];
            }

            return FcomSpace::query()->where('privacy', 'public')->pluck('id')->toArray();
        }

        $spaceIds = array_filter((array) $user->getSpaceIds());

        if (!$spaceIds) {
            return [];
        }

        return FcomSpace::query()->whereIn('id', $spaceIds)->pluck('id')->toArray();
    }

    public static function canViewFeed($feed)
    {
        if (!$feed || !in_array($feed->status, ['published', 'unlisted'], true) || !$feed->space_id) {
            return false;
        }

        $space = $feed->space;

        if (!self::isDiscussionSpace($space)) {
            return false;
        }

        $user = FcomHelper::getCurrentUser();

        if (!$user && !FcomHelper::isPublicAccessible()) {
            return false;
        }

        return (bool) $space->verifyUserPermisson($user ?: null, 'can_view_posts', false);
    }

    // The event and its post, when the current viewer may see both.
    public static function visibleEvent($id)
    {
        $event = Event::query()->with(['post'])->find((int) $id);

        if (!$event || $event->status === 'draft') {
            return [null, null];
        }

        $feed = FcomFeed::query()->find(self::feedIdOf($event));

        if (!$feed || self::eventIdOf($feed) !== (int) $event->id || !self::canViewFeed($feed)) {
            return [null, null];
        }

        return [$event, $feed];
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
     * @param Event[] $events Loaded with the `post` relation.
     */
    public static function formatEvents($events)
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
        }

        return $formatted;
    }

    public static function formatEvent($event, $seats, $myStatus, $isStaff, $canEdit)
    {
        $meta = array_merge(Event::defaultMeta(), (array) $event->meta);
        $isRsvp = $meta['entry_type'] === 'rsvp';
        $isRegistered = in_array($myStatus, Event::SEAT_STATUSES, true);
        $isPast = $event->end_at < gmdate(DateTimeHelper::FORMAT);
        $hasMeetingUrl = $event->venue_type === 'online' && !empty($meta['online_meeting_url']);

        return [
            'id'                 => (int) $event->id,
            'title'              => $event->title,
            'description'        => $event->description,
            'status'             => $event->status,
            'start_at'           => $event->start_at,
            'end_at'             => $event->end_at,
            'start_at_local'     => $event->start_at_local,
            'end_at_local'       => $event->end_at_local,
            'timezone'           => $event->timezone,
            'venue_type'         => $event->venue_type,
            'venue'              => $meta['venue'],
            'address'            => $meta['address'],
            'cover_url'          => $meta['cover_url'],
            'has_meeting_url'    => $hasMeetingUrl,
            // An RSVP's link is for its guests and staff; an open event shows it to every viewer.
            'online_meeting_url' => $hasMeetingUrl && (!$isRsvp || $isRegistered || $isStaff) ? $meta['online_meeting_url'] : '',
            'entry_type'         => $meta['entry_type'],
            'guest_count'        => $isRsvp && $meta['show_guest_count'] === 'yes' ? $seats : null,
            'is_past'            => $isPast,
            'my_status'          => $myStatus,
            'registration_open'  => $isRsvp && !$isPast,
            'edit_url'           => $canEdit ? self::editUrl($event->id) : '',
        ];
    }
}
