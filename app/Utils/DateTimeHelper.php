<?php

namespace FluentEvent\App\Utils;

use DateTime;
use DateTimeZone;

class DateTimeHelper
{
    const FORMAT = 'Y-m-d H:i:s';

    const PHP_TO_DAYJS = [
        // Day
        'd' => 'DD',   // Day of the month, 2 digits with leading zeros
        'D' => 'ddd',  // A textual representation of a day, three letters
        'j' => 'D',    // Day of the month without leading zeros
        'l' => 'dddd', // A full textual representation of the day of the week
        'N' => '',     // Not supported in Day.js (ISO-8601 numeric day of the week)
        'S' => '',     // Only after "j"/"d", where it becomes "Do" (see convertToDayJsFormat)
        'w' => 'd',    // Numeric representation of the day of the week
        'z' => 'DDD',  // The day of the year (starting from 0)

        // Week
        'W' => 'W',    // ISO-8601 week number of year, weeks starting on Monday

        // Month
        'F' => 'MMMM', // A full textual representation of a month
        'm' => 'MM',   // Numeric representation of a month, with leading zeros
        'M' => 'MMM',  // A short textual representation of a month, three letters
        'n' => 'M',    // Numeric representation of a month, without leading zeros
        't' => '',     // Not supported in Day.js (Number of days in the given month)

        // Year
        'L' => '',     // Not supported in Day.js (Whether it's a leap year)
        'o' => 'GGGG', // ISO-8601 week-numbering year
        'Y' => 'YYYY', // A full numeric representation of a year, 4 digits
        'y' => 'YY',   // A two digit representation of a year

        // Time
        'a' => 'a',    // Lowercase Ante meridiem and Post meridiem
        'A' => 'A',    // Uppercase Ante meridiem and Post meridiem
        'B' => '',     // Not supported in Day.js (Swatch Internet time)
        'g' => 'h',    // 12-hour format of an hour without leading zeros
        'G' => 'H',    // 24-hour format of an hour without leading zeros
        'h' => 'hh',   // 12-hour format of an hour with leading zeros
        'H' => 'HH',   // 24-hour format of an hour with leading zeros
        'i' => 'mm',   // Minutes with leading zeros
        's' => 'ss',   // Seconds with leading zeros
        'u' => 'SSS',  // Milliseconds
        'v' => 'SSS',  // Milliseconds

        // Timezone
        'e' => '',     // Not supported in Day.js (Timezone identifier)
        'I' => '',     // Not supported in Day.js (Daylight saving time flag)
        'O' => 'ZZ',   // Difference to GMT in hours
        'P' => 'Z',    // Difference to GMT with colon between hours and minutes
        'T' => '',     // Not supported in Day.js (Timezone abbreviation)
        'Z' => '',     // Not supported in Day.js (Timezone offset in seconds)

        // Full Date/Time
        'c' => 'YYYY-MM-DDTHH:mm:ssZ',          // ISO 8601 date
        'r' => 'ddd, DD MMM YYYY HH:mm:ss ZZ',  // RFC 2822 formatted date
        'U' => 'X',    // Seconds since the Unix Epoch
    ];

    // The Y-m-d form every date filter accepts; one check, so callers can't disagree on what is a date.
    public static function isDate($value)
    {
        return $value
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    public static function convertToDayJsFormat($phpFormat)
    {
        $result = '';
        $length = strlen($phpFormat);

        for ($i = 0; $i < $length; $i++) {
            $char = $phpFormat[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $result .= '[' . $phpFormat[++$i] . ']';
                continue;
            }

            // Day.js only has an ordinal for the day of month, so "jS"/"dS" become "Do".
            if (($char === 'j' || $char === 'd') && ($phpFormat[$i + 1] ?? '') === 'S') {
                $result .= 'Do';
                $i++;
                continue;
            }

            $result .= self::PHP_TO_DAYJS[$char] ?? $char;
        }

        return $result;
    }

    public static function getValidatedTimeZone($timezone)
    {
        try {
            return (new DateTimeZone((string) $timezone))->getName();
        } catch (\Exception $e) {
            return wp_timezone()->getName();
        }
    }

    public static function convertToUtc($dateTime, $timezone, $format = self::FORMAT)
    {
        $timezone = self::getValidatedTimeZone($timezone);

        $dateTime = new DateTime($dateTime, new DateTimeZone($timezone));
        $dateTime->setTimezone(new DateTimeZone('UTC'));

        return $dateTime->format($format);
    }

    public static function convertFromUtc($dateTime, $timezone, $format = self::FORMAT)
    {
        $timezone = self::getValidatedTimeZone($timezone);

        $dateTime = new DateTime($dateTime, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone($timezone));

        return $dateTime->format($format);
    }

    // *_local and *_site are wall-clock strings; read and printed as UTC, they come out as written.
    public static function wallClock($value, $format)
    {
        return wp_date($format, strtotime($value . ' UTC'), new DateTimeZone('UTC'));
    }

    // The date an event ends on: one ending at exactly midnight ends the day before (22:00 → 00:00 is one evening).
    public static function lastDate($start, $end)
    {
        if ($end > $start && substr($end, 11, 8) === '00:00:00') {
            return gmdate('Y-m-d', strtotime(substr($end, 0, 10) . ' UTC') - DAY_IN_SECONDS);
        }

        return substr($end, 0, 10);
    }

    public static function dateRange($start, $end)
    {
        $lastDate = self::lastDate($start, $end);

        // A single day follows the site's date setting, as the community event page does.
        if (substr($start, 0, 10) === $lastDate) {
            return self::wallClock($start, get_option('date_format'));
        }

        /* translators: 1: start date, 2: end date */
        return sprintf(_x('%1$s – %2$s', 'date range', 'fluent-event'), self::wallClock($start, 'M j'), self::wallClock($lastDate . ' 00:00:00', 'M j, Y'));
    }

    public static function timeRange($start, $end, $zone)
    {
        $format = substr($start, 0, 10) === self::lastDate($start, $end) ? get_option('time_format') : 'M j, ' . get_option('time_format');
        /* translators: 1: start, 2: end (each a time, or a date and time) */
        $range = sprintf(_x('%1$s – %2$s', 'time range', 'fluent-event'), self::wallClock($start, $format), self::wallClock($end, $format));

        return $zone ? $range . ' (' . $zone . ')' : $range;
    }

    // A span that names its date, e.g. "Nov 6, 3:52 PM – 5:52 PM (Asia/Dhaka)"; the end's date only when it differs.
    public static function dateTimeRange($start, $end, $zone)
    {
        $endFormat = substr($start, 0, 10) === self::lastDate($start, $end) ? get_option('time_format') : 'M j, ' . get_option('time_format');

        /* translators: 1: start, 2: end (each a time, or a date and time) */
        return sprintf(_x('%1$s – %2$s', 'time range', 'fluent-event'), self::wallClock($start, 'M j, ' . get_option('time_format')), self::wallClock($end, $endFormat))
            . ' (' . $zone . ')';
    }

    /**
     * One event as calendar file text, the same file the community page's buildIcs() makes: the UID
     * matches, so a re-import updates the entry instead of duplicating it.
     *
     * @param array $event id, title, start_at and end_at (stored UTC), description, location, url.
     */
    public static function icsFile(array $event)
    {
        $stamp = function ($value) {
            return gmdate('Ymd\THis\Z', strtotime($value . ' UTC'));
        };

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
            'UID:fluent-event-' . (int) $event['id'] . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $stamp($event['start_at']),
            'DTEND:' . $stamp($event['end_at']),
            'SUMMARY:' . $text($event['title']),
            'DESCRIPTION:' . $text($event['description']),
            'LOCATION:' . $text($event['location']),
            'URL:' . $event['url'],
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($fold, $lines));
    }
}
