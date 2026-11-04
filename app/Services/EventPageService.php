<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\App;
use FluentEvent\App\Models\Event;

/**
 * The public event page's side card — when, status, where, how to join, add to calendar.
 */
class EventPageService
{
    // Under its calendar; the calendar relation must be loaded.
    public static function url(Event $event)
    {
        return home_url(user_trailingslashit(Settings::portalSlug() . '/' . $event->calendar->slug . '/' . $event->post->post_name));
    }

    /**
     * @param array  $formatted CommunityEvents::formatEvent() shape, for this viewer.
     * @param string $url       The event page.
     */
    public static function sideCard(Event $event, array $formatted, $url)
    {
        $siteZone = wp_timezone_string();
        $isOnline = $event->venue_type === 'online';
        $start = strtotime($event->start_at . ' UTC');
        $end = strtotime($event->end_at . ' UTC');
        $phase = time() >= $end ? 'ended' : (time() >= $start ? 'live' : 'upcoming');
        $isSeated = in_array($formatted['my_status'], Event::SEAT_STATUSES, true);
        $meetingLink = preg_match('#^https?://#i', $formatted['online_meeting_url']) ? $formatted['online_meeting_url'] : '';

        $data = [
            'event'            => $formatted,
            'is_online'        => $isOnline,
            'chip_month'       => self::wallClock($event->start_at_local, 'M'),
            'chip_day'         => self::wallClock($event->start_at_local, 'j'),
            'date_label'       => self::dateRange($event->start_at_local, $event->end_at_local),
            'time_label'       => self::timeRange($event->start_at_local, $event->end_at_local, $event->timezone),
            'local_time_label' => self::localTimeLabel($event, $siteZone),
            'phase'            => $phase,
            /* translators: %s: time until the event starts, e.g. "3 days" */
            'status_label'     => $phase === 'ended' ? __('Ended', 'fluent-event') : sprintf(__('Starts in %s', 'fluent-event'), human_time_diff(time(), $start)),
            /* translators: %s: number of guests going */
            'count_label'      => $formatted['guest_count'] === null ? '' : sprintf(__('%s going', 'fluent-event'), number_format_i18n($formatted['guest_count'])),
            'meeting_link'     => $meetingLink,
            'add_to_calendar'  => null,
        ];

        // Once someone is going, or for a no-entry event anyone can attend.
        if ($phase !== 'ended' && ($formatted['entry_type'] === 'none' || $isSeated)) {
            $place = $isOnline ? ($meetingLink ?: $url) : implode(', ', array_filter([$formatted['venue'], $formatted['address']]));

            // Plain text for calendar apps; block tags become line breaks so words don't run together.
            $text = preg_replace(['/<li[^>]*>/i', '/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/i'], ['• ', "\n"], $event->description);
            $text = trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, 'UTF-8')));
            $summary = mb_substr($text, 0, 600) . "\n\n" . $url;
            $dates = [gmdate('Ymd\THis\Z', $start), gmdate('Ymd\THis\Z', $end)];

            $data['add_to_calendar'] = [
                'google'   => 'https://calendar.google.com/calendar/render?' . http_build_query([
                    'action'   => 'TEMPLATE',
                    'text'     => $event->title,
                    'dates'    => implode('/', $dates),
                    'details'  => $summary,
                    'location' => $place,
                ], '', '&', PHP_QUERY_RFC3986),
                'ics'      => 'data:text/calendar;charset=utf-8,' . rawurlencode(self::icsFile($event, $dates, $summary, $place, $url)),
                'filename' => $event->post->post_name . '.ics',
            ];
        }

        return (string) App::make('view')->make('portal.event-side', $data);
    }

    // *_local and *_site are wall-clock strings; read and printed as UTC, they come out as written.
    protected static function wallClock($value, $format)
    {
        return wp_date($format, strtotime($value . ' UTC'), new \DateTimeZone('UTC'));
    }

    public static function dateRange($start, $end)
    {
        // A single day follows the site's date setting, as the community event page does.
        if (substr($start, 0, 10) === substr($end, 0, 10)) {
            return self::wallClock($start, get_option('date_format'));
        }

        /* translators: 1: start date, 2: end date */
        return sprintf(_x('%1$s – %2$s', 'date range', 'fluent-event'), self::wallClock($start, 'M j'), self::wallClock($end, 'M j, Y'));
    }

    public static function timeRange($start, $end, $zone)
    {
        $format = substr($start, 0, 10) === substr($end, 0, 10) ? get_option('time_format') : 'M j, ' . get_option('time_format');
        /* translators: 1: start time, 2: end time */
        $range = sprintf(_x('%1$s – %2$s', 'time range', 'fluent-event'), self::wallClock($start, $format), self::wallClock($end, $format));

        return $zone ? $range . ' (' . $zone . ')' : $range;
    }

    // The site's clock, which the lists use; only when it reads differently from the event's.
    protected static function localTimeLabel(Event $event, $siteZone)
    {
        $start = $event->start_at_site;
        $end = $event->end_at_site;

        if ($start === $event->start_at_local && $end === $event->end_at_local) {
            return '';
        }

        $endFormat = substr($start, 0, 10) === substr($end, 0, 10) ? get_option('time_format') : 'M j, ' . get_option('time_format');

        /* translators: 1: start date and time, 2: end time or end date and time */
        return sprintf(_x('%1$s – %2$s', 'time range', 'fluent-event'), self::wallClock($start, 'M j, ' . get_option('time_format')), self::wallClock($end, $endFormat))
            . ' (' . $siteZone . ')';
    }

    // The same file the community page builds; its UID matches, so a re-import updates the entry instead of duplicating it.
    protected static function icsFile(Event $event, array $dates, $summary, $place, $url)
    {
        // Commas, semicolons, backslashes and newlines carry meaning in a calendar line.
        $text = function ($value) {
            return str_replace(["\r\n", "\n", "\r"], '\n', addcslashes((string) $value, '\\,;'));
        };

        // Lines are capped at 75 bytes, not characters; fold between whole characters so accents, CJK and emoji survive.
        $fold = function ($line) {
            $lines = [];
            $current = '';

            foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                // A continuation line starts with a space, which counts toward its 75.
                if (strlen($current) + strlen($char) > ($lines ? 74 : 75)) {
                    $lines[] = $current;
                    $current = '';
                }

                $current .= $char;
            }

            $lines[] = $current;

            return implode("\r\n ", $lines);
        };

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Fluent Events//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:fluent-event-' . $event->id . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $dates[0],
            'DTEND:' . $dates[1],
            'SUMMARY:' . $text($event->title),
            'DESCRIPTION:' . $text($summary),
            'LOCATION:' . $text($place),
            'URL:' . $url,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($fold, $lines));
    }
}
