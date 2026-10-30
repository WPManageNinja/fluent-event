<?php

namespace FluentEvent\Tests\Unit\Services;

use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Tests\TestCase;

class DateTimeHelperTest extends TestCase
{
    /**
     * @dataProvider dates
     */
    public function test_only_a_plain_y_m_d_counts_as_a_date($value, $isDate)
    {
        $this->assertSame($isDate, (bool) DateTimeHelper::isDate($value));
    }

    public function dates()
    {
        return [
            'date'          => ['2026-10-15', true],
            'date and time' => ['2026-10-15 10:00:00', false],
            'injection'     => ["2026-10-15' OR 1=1", false],
            // Converting the filter bounds would throw on a month 13, a 500 instead of no filter.
            'no such month' => ['2026-13-01', false],
            'no such day'   => ['2026-02-31', false],
            'empty'         => ['', false],
            'null'          => [null, false],
        ];
    }

    public function test_local_time_converts_to_utc()
    {
        $this->assertSame('2026-10-15 04:00:00', DateTimeHelper::convertToUtc('2026-10-15 10:00:00', 'Asia/Dhaka'));
    }

    public function test_utc_converts_back_to_local_time()
    {
        $this->assertSame('2026-10-15 10:00:00', DateTimeHelper::convertFromUtc('2026-10-15 04:00:00', 'Asia/Dhaka'));
    }

    public function test_conversion_follows_daylight_saving()
    {
        // US clocks went forward on 2026-03-08: EST is UTC-5 before, EDT is UTC-4 after.
        $this->assertSame('2026-03-07 17:00:00', DateTimeHelper::convertToUtc('2026-03-07 12:00:00', 'America/New_York'));
        $this->assertSame('2026-03-09 16:00:00', DateTimeHelper::convertToUtc('2026-03-09 12:00:00', 'America/New_York'));
    }

    /**
     * @dataProvider timezones
     */
    public function test_an_invalid_timezone_falls_back_to_the_site_timezone($input, $expected)
    {
        $this->assertSame($expected, DateTimeHelper::getValidatedTimeZone($input));
    }

    public function timezones()
    {
        return [
            'valid'   => ['Europe/London', 'Europe/London'],
            'offset'  => ['+06:00', '+06:00'],
            'unknown' => ['Mars/Olympus', 'UTC'],
            'empty'   => ['', 'UTC'],
            'null'    => [null, 'UTC'],
        ];
    }

    /**
     * @dataProvider formats
     */
    public function test_php_date_formats_translate_to_dayjs($php, $dayjs)
    {
        $this->assertSame($dayjs, DateTimeHelper::convertToDayJsFormat($php));
    }

    public function formats()
    {
        return [
            'iso date'       => ['Y-m-d', 'YYYY-MM-DD'],
            'wp default'     => ['F j, Y g:i a', 'MMMM D, YYYY h:mm a'],
            '24 hour'        => ['H:i', 'HH:mm'],
            'escaped letter' => ['\\a\\t g A', '[a][t] h A'],
            'unsupported'    => ['L', ''],
            'ordinal j'      => ['jS F Y', 'Do MMMM YYYY'],
            'ordinal d'      => ['dS F Y', 'Do MMMM YYYY'],
            'escaped j'      => ['\\jS F', '[j] MMMM'],
            'escaped slash'  => ['\\\\jS', '[\\]Do'],
            'iso weekday'    => ['N j', ' D'],
        ];
    }
}
