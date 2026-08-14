<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Utils\DateTimeHelper;

class ReportService
{
    const LIVE_EVENT_STATUSES = ['public', 'private'];
    const DAILY_SPAN_LIMIT = 92;

    public static function getStats()
    {
        $liveEvents = function () {
            return Event::query()->whereIn('status', self::LIVE_EVENT_STATUSES);
        };
        $now = gmdate(DateTimeHelper::FORMAT);

        $overallStats = [
            'calendars' => self::tile(
                __('Total Calendars', 'fluent-event'),
                'Calendar',
                Calendar::query()->count()
            ),
            'events' => self::tile(
                __('Total Events', 'fluent-event'),
                'Event',
                $liveEvents()->count()
            ),
            'guests' => self::tile(
                __('Total Guests', 'fluent-event'),
                'Guest',
                self::guestQuery()->count()
            ),
        ];
        $calendarStats = [
            'active' => self::tile(
                __('Active', 'fluent-event'),
                'Calendar',
                Calendar::query()->where('status', 'active')->count()
            ),
            'draft' => self::tile(
                __('Drafts', 'fluent-event'),
                'Draft',
                Calendar::query()->where('status', 'draft')->count()
            ),
            'archived' => self::tile(
                __('Archived', 'fluent-event'),
                'Archived',
                Calendar::query()->where('status', 'archived')->count()
            ),
        ];
        $eventStats = [
            'upcoming' => self::tile(
                __('Upcoming', 'fluent-event'),
                'Upcoming',
                $liveEvents()->where('start_at', '>', $now)->count()
            ),
            'ongoing' => self::tile(
                __('Ongoing', 'fluent-event'),
                'Ongoing',
                $liveEvents()->where('start_at', '<=', $now)->where('end_at', '>=', $now)->count()
            ),
            'completed' => self::tile(
                __('Completed', 'fluent-event'),
                'Completed',
                $liveEvents()->where('end_at', '<', $now)->count()
            ),
            'draft' => self::tile(
                __('Drafts', 'fluent-event'),
                'Draft',
                Event::query()->where('status', 'draft')->count()
            ),
        ];

        $guestStats = [
            'going' => self::tile(
                __('Going', 'fluent-event'),
                'Going',
                self::guestQuery()->where('status', 'going')->count()
            ),
            'checked_in' => self::tile(
                __('Checked In', 'fluent-event'),
                'CheckedIn',
                self::guestQuery()->where('status', 'checked_in')->count()
            ),
            'cancelled' => self::tile(
                __('Cancelled', 'fluent-event'),
                'Cancelled',
                self::guestQuery()->where('status', 'cancelled')->count()
            ),
        ];

        return [
            'stats' => $overallStats,
            'calendar_stats' => $calendarStats,
            'guest_stats' => $guestStats,
            'event_stats' => $eventStats,
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
    public static function getUpcomingEvents($limit = 3)
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        return self::eventQuery()
            ->where('start_at', '>', $now)
            ->orderBy('start_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    // Events ranked by guest count — cancelled guests don't count toward the rank.
    public static function getTopEvents($limit = 5)
    {
        $rows = self::guestQuery()
            ->selectRaw('event_id, COUNT(*) AS guests_count')
            ->where('status', '!=', 'cancelled')
            ->groupBy('event_id')
            ->orderByDesc('guests_count')
            ->limit($limit)
            ->with([
                'event.post' => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content', 'post_author']);
                },
                'event.post.author' => function ($query) {
                    $query->select(['ID', 'display_name']);
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

    private static function guestQuery()
    {
        return Guest::query()->whereHas('event', function ($query) {
            $query->whereIn('status', self::LIVE_EVENT_STATUSES);
        });
    }

    private static function eventQuery()
    {
        return Event::query()
            ->whereIn('status', self::LIVE_EVENT_STATUSES)
            ->with([
                'post' => function ($query) {
                    $query->select(['ID', 'post_title', 'post_content', 'post_author']);
                },
                'post.author' => function ($query) {
                    $query->select(['ID', 'display_name']);
                },
                'calendar' => function ($query) {
                    $query->select(['id', 'title', 'meta']);
                },
            ]);
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
