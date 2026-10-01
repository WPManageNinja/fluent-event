<?php

namespace FluentEvent\App\Services;

use FluentEvent\Facade\DB;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Foundation\Exceptions\ForbiddenHttpException;
use FluentEvent\Framework\Validator\ValidationException;
use FluentEvent\Framework\Validator\Validator;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\App\Services\GuestService;
use FluentEvent\App\Services\CalendarService;

class EventService
{
    const PER_PAGE_MAX = 50;
    const SCOPES = ['upcoming', 'past', 'ongoing', 'all'];
    const WRITE_STATUSES = ['public', 'draft'];
    const WRITABLE = [
        'title',
        'description',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'venue_type',
        'venue',
        'address',
        'online_meeting_url',
        'cover_url',
    ];

    public static function getEvents(int $calendarId, array $args = []): array
    {
        $args = array_merge([
            'scope'    => 'upcoming',
            'from'     => '',
            'to'       => '',
            'search'   => '',
            'order'    => '',
            'page'     => 1,
            'per_page' => 20,
            'actor_id' => 0,
            'space_id' => 0,
        ], $args);

        $spaceId = (int) $args['space_id'];

        if ($spaceId && !CalendarService::belongsToSpace($calendarId, $spaceId)) {
            throw new ValidationException('Unprocessable Entity!', 422, null, [
                'calendar_id' => ['space' => __('This calendar does not belong to this space.', 'fluent-event')],
            ]);
        }

        $scope = in_array($args['scope'], self::SCOPES, true) ? $args['scope'] : 'upcoming';
        $perPage = min(max((int) $args['per_page'], 1), self::PER_PAGE_MAX);
        $page = ((int) $args['page']) > 0 ? (int) $args['page'] : null;
        $userId = (int) $args['actor_id'];
        // Resolved here, never a caller flag — otherwise anyone passes true.
        $canManage = PermissionManager::userCan(['read_events', 'write_events'], $userId);

        // `private` is in neither list — it means not for the community, so no
        // permission unlocks it here.
        $query = Event::query()
            ->with([
                'post'        => function ($query) {
                    $query->select(['ID', 'post_title', 'post_name', 'post_content', 'post_status', 'post_author']);
                },
                'post.author' => function ($query) {
                    $query->select(['ID', 'display_name']);
                },
            ])
            ->where('calendar_id', $calendarId)
            ->byStatus($canManage ? ['public', 'draft'] : ['public'])
            ->whereHas('post', function ($query) use ($canManage) {
                $query->byStatus($canManage ? ['publish', 'draft'] : ['publish']);
            })
            ->searchBy(sanitize_text_field($args['search']));

        /**
         * Applying scope to the query.
         */
        $now = gmdate(DateTimeHelper::FORMAT);

        if ($scope === 'upcoming') {
            $query->where('end_at', '>=', $now);
        } elseif ($scope === 'past') {
            $query->where('end_at', '<', $now);
        } elseif ($scope === 'ongoing') {
            $query->where('start_at', '<=', $now)->where('end_at', '>=', $now);
        }

        /**
         * Applying date range window to the query.
         */
        $isDateTime = function ($value) {
            return $value && (bool) preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value);
        };

        if ($isDateTime($args['from'])) {
            $from = $args['from'];
            $query->where('end_at', '>=', strlen($from) === 10 ? $from . ' 00:00:00' : $from);
        }

        if ($isDateTime($args['to'])) {
            $to = $args['to'];
            $query->where('start_at', '<=', strlen($to) === 10 ? $to . ' 23:59:59' : $to);
        }

        $order = strtolower((string) $args['order']);

        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = $scope === 'past' ? 'desc' : 'asc';
        }

        $events = $query
            ->orderBy('start_at', strtoupper($order))
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'events'   => static::formatEvents($events->items(), $userId),
            'total'    => $events->total(),
            'page'     => $events->currentPage(),
            'per_page' => $events->perPage(),
        ];
    }

    public static function getEvent(int $eventId, array $args = []): ?array
    {
        $args = array_merge([
            'actor_id'    => 0,
            'calendar_id' => 0,
        ], $args);

        $actorId = (int) $args['actor_id'];
        // Resolved here, never a caller flag — otherwise anyone passes true.
        $canManage = PermissionManager::userCan(['read_events', 'write_events'], $actorId);

        $query = Event::query()
            ->with([
                'post'        => function ($query) {
                    $query->select(['ID', 'post_title', 'post_name', 'post_content', 'post_status', 'post_author']);
                },
                'post.author' => function ($query) {
                    $query->select(['ID', 'display_name']);
                },
            ])
            ->byStatus($canManage ? ['public', 'draft'] : ['public'])
            ->whereHas('post', function ($query) use ($canManage) {
                $query->byStatus($canManage ? ['publish', 'draft'] : ['publish']);
            });

        if ($calendarId = (int) $args['calendar_id']) {
            $query->where('calendar_id', $calendarId);
        }

        $event = $query->find($eventId);

        if (!$event) {
            return null;
        }

        $formatted = static::formatEvents([$event], $actorId);

        return $formatted[0];
    }

    public static function createInCalendar(int $calendarId, array $data, int $actorId): array
    {
        if (!PermissionManager::userCan('write_events', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to create events.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        $calendar = static::requireCalendar($calendarId);
        $data = static::prepareEventData($data, $calendar->id);
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        $event = DB::transaction(function () use ($data, $timezone, $calendar, $actorId) {
            return Event::create([
                'post_id'         => PostService::createEventPost($data, $actorId),
                'calendar_id'     => $calendar->id,
                'parent_event_id' => null,
                'status'          => Arr::get($data, 'status'),
                'start_at'        => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'          => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'        => $timezone,
                'venue_type'      => Arr::get($data, 'venue_type'),
                'meta'            => Arr::get($data, 'meta', []),
            ]);
        });

        do_action('fluent_event/event/created', $event);

        return static::getEvent($event->id, ['actor_id' => $actorId, 'calendar_id' => $calendar->id]);
    }

    public static function update(int $eventId, int $calendarId, array $data, int $actorId): array
    {
        if (!PermissionManager::userCan('write_events', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to edit events.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        $calendar = static::requireCalendar($calendarId);
        $event = static::requireEvent($eventId, $calendar->id);

        $data = static::prepareEventData($data, $calendar->id, $event->meta);
        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        DB::transaction(function () use ($event, $data, $timezone) {
            PostService::updateEventPost($event->post_id, $data);

            $event->fill([
                'status'     => Arr::get($data, 'status'),
                'start_at'   => DateTimeHelper::convertToUtc(Arr::get($data, 'start_at'), $timezone),
                'end_at'     => DateTimeHelper::convertToUtc(Arr::get($data, 'end_at'), $timezone),
                'timezone'   => $timezone,
                'venue_type' => Arr::get($data, 'venue_type'),
                'meta'       => Arr::get($data, 'meta', []),
            ])->save();
        });

        do_action('fluent_event/event/updated', $event);

        return static::getEvent($event->id, ['actor_id' => $actorId, 'calendar_id' => $calendar->id]);
    }

    /** Retire path: readable to permission holders, gone for members. */
    public static function unpublish(int $eventId, int $calendarId, int $actorId): array
    {
        if (!PermissionManager::userCan('write_events', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to edit events.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        $event = static::requireEvent($eventId, static::requireCalendar($calendarId)->id);

        DB::transaction(function () use ($event) {
            PostService::updateEventPost($event->post_id, [
                'title'       => $event->title,
                'description' => $event->post->post_content ?? '',
                'status'      => 'draft',
            ]);

            $event->fill(['status' => 'draft'])->save();
        });

        do_action('fluent_event/event/updated', $event);

        return static::getEvent($event->id, ['actor_id' => $actorId, 'calendar_id' => $event->calendar_id]);
    }

    public static function delete(int $eventId, int $calendarId, int $actorId): bool
    {
        if (!PermissionManager::userCan('write_events', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to delete events.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        $event = static::requireEvent($eventId, static::requireCalendar($calendarId)->id);

        DB::transaction(function () use ($event) {
            do_action('fluent_event/event/before_deleted', $event);

            $event->delete();
        });

        do_action('fluent_event/event/deleted', $event);

        return true;
    }

    protected static function prepareEventData(array $data, $calendarId, $existingMeta = [])
    {
        $data = EventRequest::sanitize(Arr::only($data, self::WRITABLE));
        $data['calendar_id'] = (int) $calendarId;

        $rules = EventRequest::rules($data);
        $rules['status'] = 'required|string|in:' . implode(',', self::WRITE_STATUSES);

        $messages = EventRequest::messages();
        $messages['status.in'] = __('Status must be public or draft.', 'fluent-event');

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->validate()->fails()) {
            throw new ValidationException('Unprocessable Entity!', 422, null, $validator->errors());
        }
        EventRequest::validateDateWindow($data);

        return EventRequest::format($data, $existingMeta);
    }

    protected static function requireCalendar($calendarId)
    {
        $calendar = Calendar::query()->find((int) $calendarId);

        if (!$calendar) {
            throw new ValidationException('Unprocessable Entity!', 422, null, ['calendar_id' => ['exists' => __('The selected calendar no longer exists.', 'fluent-event')]]);
        }

        return $calendar;
    }

    protected static function requireEvent($eventId, $calendarId)
    {
        $event = Event::query()
            ->with(['post'])
            ->where('calendar_id', $calendarId)
            ->find((int) $eventId);

        if (!$event) {
            throw new ValidationException('Unprocessable Entity!', 422, null, ['event_id' => ['exists' => __('This event is not in this calendar.', 'fluent-event')]]);
        }

        return $event;
    }

    protected static function formatEvents(array $events, $userId = 0)
    {
        if (!$events) {
            return [];
        }

        $eventIds = array_map(function ($event) {
            return (int) $event->id;
        }, $events);

        $counts = static::countsFor($eventIds);
        $myStatuses = $userId ? static::guestStatusForEvents($eventIds, $userId) : [];

        $formatted = [];

        foreach ($events as $event) {
            $formatted[] = static::format(
                $event,
                $counts[(int) $event->id] ?? ['going' => 0, 'not_going' => 0],
                $userId ? ($myStatuses[(int) $event->id] ?? null) : null
            );
        }

        return $formatted;
    }

    protected static function format($event, array $guestCounts, $myStatus)
    {
        $meta = array_merge(Event::defaultMeta(), (array) $event->meta);

        return [
            'id'             => (int) $event->id,
            'calendar_id'    => (int) $event->calendar_id,
            'title'          => $event->title,
            'slug'           => $event->post->post_name ?? '',
            'description'    => $event->description,
            'status'         => $event->status,
            'start_at'       => $event->start_at,
            'end_at'         => $event->end_at,
            'start_at_local' => $event->start_at_local,
            'end_at_local'   => $event->end_at_local,
            'timezone'       => $event->timezone,
            'venue_type'     => $event->venue_type,
            'meta'           => $meta,
            'guest_counts'   => $guestCounts,
            'author'         => $event->author,
            'my_status'      => $myStatus,
        ];
    }

    protected static function countsFor(array $eventIds)
    {
        $rows = Guest::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', array_keys(GuestService::COUNTS_AS))
            ->selectRaw('event_id, status, COUNT(*) AS total')
            ->groupBy(['event_id', 'status'])
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $eventId = (int) $row->event_id;
            $bucket = GuestService::COUNTS_AS[$row->status];

            $counts[$eventId] = ($counts[$eventId] ?? ['going' => 0, 'not_going' => 0]);
            $counts[$eventId][$bucket] += (int) $row->total;
        }

        return $counts;
    }

    protected static function guestStatusForEvents(array $eventIds, $userId)
    {
        $rows = Guest::query()
            ->whereIn('event_id', $eventIds)
            ->where('user_id', $userId)
            ->get();

        $myStatuses = [];

        foreach ($rows as $row) {
            $myStatuses[(int) $row->event_id] = $row->status;
        }

        return $myStatuses;
    }
}
