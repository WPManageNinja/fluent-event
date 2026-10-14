<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Utils\DateTimeHelper;

class ReportService
{
    const LIVE_EVENT_STATUSES = ['public', 'private'];
    const DAILY_SPAN_LIMIT = 92;

    public static function getStats()
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        $events = Event::query()
            ->whereIn('status', self::LIVE_EVENT_STATUSES)
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS total,'
                . ' SUM(start_at > ?) AS upcoming,'
                . ' SUM(start_at <= ? AND end_at >= ?) AS ongoing,'
                . ' SUM(end_at < ?) AS completed',
                [$now, $now, $now, $now]
            )
            ->first();

        $guests = self::guestQuery()
            ->toBase()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total_events' => self::tile(
                __('Total Events', 'fluent-event'),
                'Event',
                (int) $events->total
            ),
            'upcoming_events' => self::tile(
                __('Upcoming Events', 'fluent-event'),
                'Upcoming',
                (int) $events->upcoming
            ),
            'ongoing_events' => self::tile(
                __('Ongoing Events', 'fluent-event'),
                'Ongoing',
                (int) $events->ongoing
            ),
            'completed_events' => self::tile(
                __('Completed Events', 'fluent-event'),
                'Completed',
                (int) $events->completed
            ),
            'total_guests' => self::tile(
                __('Total Guests', 'fluent-event'),
                'Guest',
                (int) $guests->sum()
            ),
            'going_guests' => self::tile(
                __('Going Guests', 'fluent-event'),
                'Going',
                (int) $guests->get('going', 0)
            ),
            'checked_in_guests' => self::tile(
                __('Checked In Guests', 'fluent-event'),
                'CheckedIn',
                (int) $guests->get('checked_in', 0)
            ),
            'cancelled_guests' => self::tile(
                __('Cancelled Guests', 'fluent-event'),
                'Cancelled',
                (int) $guests->get('cancelled', 0)
            ),
        ];

        return ['stats' => $stats];
    }

    public static function getCalendarStats($calendarId)
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        $liveEvents = function () use ($calendarId) {
            return Event::query()
                ->whereIn('status', self::LIVE_EVENT_STATUSES)
                ->where('calendar_id', $calendarId);
        };

        $totalEvents = $liveEvents()->count();
        $upcomingEvents = $liveEvents()->where('start_at', '>', $now)->count();
        $totalGuests = self::guestQuery($calendarId)->count();
        $checkedInGuests = self::guestQuery($calendarId)->where('status', 'checked_in')->count();

        return [
            'total_events' => self::tile(
                __('Total Events', 'fluent-event'),
                'Event',
                $totalEvents
            ),
            'upcoming_events' => self::tile(
                __('Upcoming Events', 'fluent-event'),
                'Upcoming',
                $upcomingEvents
            ),
            'total_guests' => self::tile(
                __('Total Guests', 'fluent-event'),
                'Guest',
                $totalGuests
            ),
            'checked_in_guests' => self::tile(
                __('Checked In Guests', 'fluent-event'),
                'CheckedIn',
                $checkedInGuests
            ),
        ];
    }

    public static function getGuestTrends($start, $end)
    {
        $granularity = self::spanDays($start, $end) > self::DAILY_SPAN_LIMIT ? 'month' : 'day';

        $expression = $granularity === 'month'
            ? "DATE_FORMAT(created_at, '%Y-%m')"
            : 'DATE(created_at)';

        $rows = self::guestQuery()
            ->selectRaw($expression . ' AS bucket, status, COUNT(*) AS total')
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->groupBy('bucket', 'status')
            ->get();

        $buckets   = self::buckets($start, $end, $granularity);
        $guests    = array_fill_keys($buckets, 0);
        $checkedIn = array_fill_keys($buckets, 0);
        $cancelled = array_fill_keys($buckets, 0);

        foreach ($rows as $row) {
            if (!isset($guests[$row->bucket])) continue;

            $count = (int) $row->total;
            $guests[$row->bucket] += $count;

            if ($row->status === 'checked_in') {
                $checkedIn[$row->bucket] = $count;
            } elseif ($row->status === 'cancelled') {
                $cancelled[$row->bucket] = $count;
            }
        }

        return [
            'range'       => ['start' => $start, 'end' => $end],
            'granularity' => $granularity,
            'xAxisData'   => $buckets,
            'series'      => [
                [
                    'name' => __('Total Guests', 'fluent-event'),
                    'data' => array_values($guests),
                ],
                [
                    'name' => __('Checked In', 'fluent-event'),
                    'data' => array_values($checkedIn),
                ],
                [
                    'name' => __('Cancelled', 'fluent-event'),
                    'data' => array_values($cancelled),
                ],
            ],
        ];
    }

    // Running events, soonest to end first.
    public static function getOngoingEvents($limit = 3)
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        return self::eventQuery()
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->orderBy('end_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    // Future events, soonest to start first.
    public static function getUpcomingEvents($limit = 3, $calendarId = 0)
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        return self::eventQuery($calendarId)
            ->where('start_at', '>', $now)
            ->orderBy('start_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    // Events ranked by guest count — cancelled and not-going guests don't count toward the rank.
    public static function getTopEvents($limit = 5)
    {
        $rows = self::guestQuery()
            ->selectRaw('event_id, COUNT(*) AS guests_count')
            ->whereNotIn('status', ['cancelled', 'not_going'])
            ->groupBy('event_id')
            ->orderByDesc('guests_count')
            ->limit($limit)
            ->with([
                'event.post' => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content']);
                },
                'event.calendar' => function ($query) {
                    $query->select(['id', 'title', 'meta']);
                },
            ])
            ->get();

        $topEvents = [];
        foreach ($rows as $row) {
            $event = $row->event;
            $event->guests_count = (int) $row->guests_count;
            $topEvents[] = $event;
        }

        return $topEvents;
    }

    private static function guestQuery($calendarId = 0)
    {
        $query = Guest::query()->whereHas('event', function ($query) {
            $query->whereIn('status', self::LIVE_EVENT_STATUSES);
        });

        if ($calendarId) {
            $query->where('calendar_id', $calendarId);
        }

        return $query;
    }

    private static function eventQuery($calendarId = 0)
    {
        $query = Event::query()
            ->whereIn('status', self::LIVE_EVENT_STATUSES)
            ->with([
                'post' => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content']);
                },
                'calendar' => function ($query) {
                    $query->select(['id', 'title', 'meta']);
                },
            ]);

        if ($calendarId) {
            $query->where('calendar_id', $calendarId);
        }

        return $query;
    }

    private static function tile($title, $icon, $amount)
    {
        return [
            'title'     => $title,
            'icon'      => $icon,
            'amount'    => $amount,
            'is_number' => true,
        ];
    }

    private static function spanDays($start, $end)
    {
        return (int) ((strtotime($end) - strtotime($start)) / DAY_IN_SECONDS) + 1;
    }

    private static function buckets($start, $end, $granularity)
    {
        $buckets = [];
        $cursor  = strtotime($start);
        $endTs   = strtotime($end);

        if ($granularity === 'month') {
            $cursor = strtotime(gmdate('Y-m-01', $cursor));

            while ($cursor <= $endTs) {
                $buckets[] = gmdate('Y-m', $cursor);
                $cursor = strtotime('+1 month', $cursor);
            }

            return $buckets;
        }

        while ($cursor <= $endTs) {
            $buckets[] = gmdate('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        return $buckets;
    }
}
