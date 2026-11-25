<?php

namespace FluentEvent\Tests\Unit\Services;

use FluentEvent\App\Services\EventService;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Tests\TestCase;

/**
 * When an event takes registrations: offsets counted back from its start, on its own clock, so the moment
 * matches what the event form previews. With no limit a side falls on the event's own start or end.
 */
class RegistrationWindowTest extends TestCase
{
    // [opens, closes] on the event's clock, for an event starting at $localStart and lasting two hours.
    private function window($localStart, $timezone, $opens = null, $closes = null)
    {
        $startAt = DateTimeHelper::convertToUtc($localStart, $timezone);
        $endAt = gmdate(DateTimeHelper::FORMAT, strtotime($startAt . ' UTC') + 2 * HOUR_IN_SECONDS);

        $window = EventService::calculateRegistrationWindow($startAt, $endAt, $timezone, $opens, $closes);

        return [
            DateTimeHelper::convertFromUtc($window['opens_at'], $timezone),
            DateTimeHelper::convertFromUtc($window['closes_at'], $timezone),
        ];
    }

    public function test_with_no_limit_registration_opens_at_the_start_and_closes_at_the_end()
    {
        $this->assertSame(['2026-11-10 18:00:00', '2026-11-10 20:00:00'], $this->window('2026-11-10 18:00:00', 'Asia/Dhaka'));
    }

    public function test_both_moments_are_stored_in_utc()
    {
        $window = EventService::calculateRegistrationWindow('2026-11-10 12:00:00', '2026-11-10 14:00:00', 'Asia/Dhaka', ['value' => 1, 'unit' => 'hours'], null);

        $this->assertSame(['opens_at' => '2026-11-10 11:00:00', 'closes_at' => '2026-11-10 14:00:00'], $window);
    }

    /**
     * @dataProvider offsets
     */
    public function test_each_unit_counts_back_from_the_start($limit, $expected)
    {
        [$opens] = $this->window('2026-11-10 18:00:00', 'Asia/Dhaka', $limit);

        $this->assertSame($expected, $opens);
    }

    public function offsets()
    {
        return [
            'minutes'           => [['value' => 45, 'unit' => 'minutes'], '2026-11-10 17:15:00'],
            'hours'             => [['value' => 30, 'unit' => 'hours'], '2026-11-09 12:00:00'],
            'days'              => [['value' => 7, 'unit' => 'days'], '2026-11-03 18:00:00'],
            'zero is the start' => [['value' => 0, 'unit' => 'hours'], '2026-11-10 18:00:00'],
        ];
    }

    public function test_opens_and_closes_are_worked_out_independently()
    {
        $this->assertSame(
            ['2026-11-03 18:00:00', '2026-11-10 16:00:00'],
            $this->window('2026-11-10 18:00:00', 'UTC', ['value' => 7, 'unit' => 'days'], ['value' => 2, 'unit' => 'hours'])
        );
    }

    /**
     * The form previews "7 days before" as the same wall-clock time a week
     * earlier; a fixed 7 × 24 hours would land an hour off across a DST change.
     *
     * @dataProvider daylightSavingChanges
     */
    public function test_days_keep_the_wall_clock_time_across_a_daylight_saving_change($localStart, $expected)
    {
        [$opens] = $this->window($localStart, 'America/New_York', ['value' => 7, 'unit' => 'days']);

        $this->assertSame($expected, $opens);
    }

    public function daylightSavingChanges()
    {
        return [
            'clocks go back in between'    => ['2026-11-03 10:00:00', '2026-10-27 10:00:00'],
            'clocks go forward in between' => ['2027-03-16 10:00:00', '2027-03-09 10:00:00'],
        ];
    }

    // 2:30 AM never happens in New York on 2027-03-14; read as 3:30 AM it would open after the 3:00 AM close.
    public function test_a_time_skipped_by_daylight_saving_opens_when_the_clocks_jump_not_after_closing()
    {
        [$opens, $closes] = $this->window('2027-03-14 04:00:00', 'America/New_York', ['value' => 90, 'unit' => 'minutes'], ['value' => 60, 'unit' => 'minutes']);

        $this->assertSame('2027-03-14 03:00:00', $opens);
        $this->assertSame('2027-03-14 03:00:00', $closes);
    }

    // The jump is the zone's own: London's clocks go forward at 1:00, Lord Howe's by half an hour at 2:00.
    public function test_a_skipped_time_follows_each_zones_own_clock_change()
    {
        $this->assertSame('2027-03-28 02:00:00', $this->window('2027-03-28 02:30:00', 'Europe/London', ['value' => 60, 'unit' => 'minutes'])[0]);
        $this->assertSame('2026-10-04 02:30:00', $this->window('2026-10-04 02:40:00', 'Australia/Lord_Howe', ['value' => 30, 'unit' => 'minutes'])[0]);
    }
}
