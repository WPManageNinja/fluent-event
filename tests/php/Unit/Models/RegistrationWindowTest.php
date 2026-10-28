<?php

namespace FluentEvent\Tests\Unit\Models;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Tests\TestCase;

/**
 * When an RSVP event takes registrations: offsets counted back from its start,
 * on its own clock, so the moment matches what the event form previews.
 */
class RegistrationWindowTest extends TestCase
{
    private function window($localStart, $timezone, array $meta)
    {
        $event = (object) [
            'start_at' => DateTimeHelper::convertToUtc($localStart, $timezone),
            'timezone' => $timezone,
            'meta'     => $meta,
        ];

        return array_map(function ($at) use ($timezone) {
            return $at === null ? null : DateTimeHelper::convertFromUtc(gmdate(DateTimeHelper::FORMAT, $at), $timezone);
        }, Event::registrationWindow($event));
    }

    public function test_no_limit_is_null_on_both_ends()
    {
        $this->assertSame([null, null], $this->window('2026-11-10 18:00:00', 'UTC', []));
        $this->assertSame([null, null], $this->window('2026-11-10 18:00:00', 'UTC', ['registration_opens' => null, 'registration_closes' => null]));
    }

    public function test_an_event_saved_before_the_window_existed_has_no_limit()
    {
        $event = (object) ['start_at' => '2026-11-10 18:00:00', 'timezone' => 'UTC', 'meta' => null];

        $this->assertSame([null, null], Event::registrationWindow($event));
    }

    /**
     * @dataProvider offsets
     */
    public function test_each_unit_counts_back_from_the_start($limit, $expected)
    {
        [$opens] = $this->window('2026-11-10 18:00:00', 'Asia/Dhaka', ['registration_opens' => $limit]);

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
            $this->window('2026-11-10 18:00:00', 'UTC', [
                'registration_opens'  => ['value' => 7, 'unit' => 'days'],
                'registration_closes' => ['value' => 2, 'unit' => 'hours'],
            ])
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
        [$opens] = $this->window($localStart, 'America/New_York', ['registration_opens' => ['value' => 7, 'unit' => 'days']]);

        $this->assertSame($expected, $opens);
    }

    public function daylightSavingChanges()
    {
        return [
            'clocks go back in between'    => ['2026-11-03 10:00:00', '2026-10-27 10:00:00'],
            'clocks go forward in between' => ['2027-03-16 10:00:00', '2027-03-09 10:00:00'],
        ];
    }

    public function test_the_window_moves_with_the_event()
    {
        $meta = ['registration_closes' => ['value' => 1, 'unit' => 'days']];

        $this->assertSame([null, '2026-11-09 18:00:00'], $this->window('2026-11-10 18:00:00', 'UTC', $meta));
        $this->assertSame([null, '2026-12-01 09:30:00'], $this->window('2026-12-02 09:30:00', 'UTC', $meta));
    }
}
