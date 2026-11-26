<?php

namespace FluentEvent\Tests\Unit\Http;

use Brain\Monkey\Functions;
use FluentEvent\App\Http\Requests\CalendarRequest;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\App\Http\Requests\ReportRequest;
use FluentEvent\App\Http\Requests\SettingsRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\Framework\Validator\ValidationException;
use FluentEvent\Tests\TestCase;

/**
 * The Request classes are where input is shaped before it reaches a model, so
 * these pin what is kept, what is dropped and which rules apply.
 */
class RequestRulesTest extends TestCase
{
    private function request(array $params)
    {
        return new class($params) {
            private $params;

            public function __construct(array $params)
            {
                $this->params = $params;
            }

            public function getSafe($key, $callback = null, $default = null)
            {
                if (!array_key_exists($key, $this->params)) {
                    return $default;
                }

                return $callback ? call_user_func($callback, $this->params[$key]) : $this->params[$key];
            }
        };
    }

    private function eventInput(array $overrides = [])
    {
        return array_merge([
            'calendar_id'        => 3,
            'title'              => 'Launch party',
            'description_md'     => 'Come along',
            'status'             => 'public',
            'start_at'           => '2026-11-01 18:00:00',
            'end_at'             => '2026-11-01 21:00:00',
            'timezone'           => 'Asia/Dhaka',
            'venue_type'         => 'offline',
            'venue'              => 'Hall A',
            'address'            => '12 Road',
            'online_meeting_url' => 'https://meet.example.com/x',
            'entry_type'         => 'rsvp',
            'show_guest_count'   => 'yes',
        ], $overrides);
    }

    public function test_an_online_event_needs_a_meeting_link()
    {
        $this->assertSame('required', EventRequest::rules(['venue_type' => 'online'])['online_meeting_url']);
        $this->assertSame('url', EventRequest::rules(['venue_type' => 'online', 'online_meeting_url' => 'meet'])['online_meeting_url']);
        $this->assertSame('nullable|url', EventRequest::rules(['venue_type' => 'offline'])['online_meeting_url']);
    }

    // 100 by default; a site may raise it with a filter, never past 255.
    public function test_a_venue_name_is_capped_at_its_filterable_limit()
    {
        $this->assertSame('nullable|string|max:100', EventRequest::rules([])['venue']);

        Functions\when('apply_filters')->justReturn(1000);
        $this->assertSame(255, Event::venueMaxLength());
    }

    public function test_formatting_an_event_drops_fields_the_request_does_not_own()
    {
        $formatted = EventRequest::format($this->eventInput([
            'id'              => 99,
            'post_id'         => 42,
            'parent_event_id' => 7,
            'post_author'     => 1,
        ]));

        foreach (['id', 'post_id', 'parent_event_id', 'post_author', 'venue', 'address'] as $key) {
            $this->assertArrayNotHasKey($key, $formatted, "{$key} leaked into the formatted event");
        }

        $this->assertSame('Launch party', $formatted['title']);
        $this->assertSame(3, $formatted['calendar_id']);
    }

    public function test_event_meta_layers_defaults_then_stored_then_input()
    {
        $formatted = EventRequest::format(
            $this->eventInput(['cover_url' => '', 'show_guest_count' => 'no']),
            ['cover_url' => 'https://cdn.example.com/old.jpg', 'show_guest_count' => 'yes']
        );

        // A cover cleared in the form must replace the stored one, not fall back to it.
        $this->assertSame('', $formatted['meta']['cover_url']);
        $this->assertSame('no', $formatted['meta']['show_guest_count']);
        $this->assertSame('rsvp', $formatted['meta']['entry_type']);
    }

    public function test_the_description_is_rendered_from_its_markdown_and_the_markdown_kept()
    {
        $markdown = "**Bring** a tripod&#x20;\n\n- warm layer\n\n<script>alert(1)</script>\n\n![1.00](https://x.test/a.png \"Sunset\")\n\n![0.43](https://x.test/b.png)\n\n![1.50](https://x.test/c.png)";
        $formatted = EventRequest::format($this->eventInput(['description_md' => $markdown]));

        $this->assertSame($markdown, $formatted['meta']['description_md']);
        // Milkdown's "&#x20;" for an edge space never reaches the page as text.
        $this->assertStringContainsString('<p><strong>Bring</strong> a tripod</p>', $formatted['description']);
        $this->assertStringContainsString('<li>warm layer</li>', $formatted['description']);
        // Typed HTML stays text; the image block's ratio alt gives way to its caption and sizes the image,
        // never past the column.
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $formatted['description']);
        $this->assertStringContainsString('<img src="https://x.test/a.png" alt="Sunset" style="display: block; margin: 0 auto; max-width: 100%;" title="Sunset" />', $formatted['description']);
        $this->assertStringContainsString('<img src="https://x.test/b.png" alt="" style="display: block; margin: 0 auto; max-width: 43%;" />', $formatted['description']);
        $this->assertStringContainsString('<img src="https://x.test/c.png" alt="" style="display: block; margin: 0 auto; max-width: 100%;" />', $formatted['description']);
    }

    public function test_an_offline_event_never_keeps_a_meeting_link()
    {
        $meta = EventRequest::format($this->eventInput(['venue_type' => 'offline']))['meta'];

        $this->assertSame('', $meta['online_meeting_url']);
        $this->assertSame('Hall A', $meta['venue']);
    }

    public function test_an_online_event_never_keeps_a_venue_or_address()
    {
        $meta = EventRequest::format(
            $this->eventInput(['venue_type' => 'online']),
            ['venue' => 'Old hall', 'address' => 'Old road']
        )['meta'];

        $this->assertSame('', $meta['venue']);
        $this->assertSame('', $meta['address']);
        $this->assertSame('https://meet.example.com/x', $meta['online_meeting_url']);
    }

    public function test_an_event_ending_after_it_starts_passes_the_date_window()
    {
        EventRequest::validateDateWindow(['start_at' => '2026-11-01 18:00', 'end_at' => '2026-11-01 18:01']);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider badWindows
     */
    public function test_an_impossible_date_window_is_rejected_on_end_at($start, $end)
    {
        try {
            EventRequest::validateDateWindow(['start_at' => $start, 'end_at' => $end]);
            $this->fail('The date window was accepted');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertArrayHasKey('end_at', $e->errors());
        }
    }

    public function badWindows()
    {
        return [
            'ends when it starts' => ['2026-11-01 18:00', '2026-11-01 18:00'],
            'ends before it starts' => ['2026-11-01 18:00', '2026-11-01 17:00'],
        ];
    }

    /**
     * @dataProvider unreadableDates
     */
    public function test_an_unreadable_date_gets_its_own_message($field, $start, $end, $message)
    {
        try {
            EventRequest::validateDateWindow(['start_at' => $start, 'end_at' => $end]);
            $this->fail('The unreadable date was accepted');
        } catch (ValidationException $e) {
            $this->assertSame([$field => ['date' => $message]], $e->errors());
        }
    }

    public function unreadableDates()
    {
        return [
            'words for an end' => ['end_at', '2026-11-01 18:00', 'soon', 'End must be a valid date and time.'],
            'missing start' => ['start_at', '', '2026-11-01 18:00', 'Start must be a valid date and time.'],
            'day past the month' => ['start_at', '2026-02-30 18:00', '2026-03-01 18:00', 'Start must be a valid date and time.'],
        ];
    }

    public function test_a_calendar_theme_colour_is_moved_into_meta()
    {
        $formatted = CalendarRequest::format(['title' => 'Team', 'theme_color' => '#ff0000']);

        $this->assertArrayNotHasKey('theme_color', $formatted);
        $this->assertSame('#ff0000', $formatted['meta']['theme_color']);
    }

    public function test_an_empty_theme_colour_keeps_the_stored_one()
    {
        $formatted = CalendarRequest::format(['theme_color' => ''], ['theme_color' => '#123456']);

        $this->assertSame('#123456', $formatted['meta']['theme_color']);
    }

    public function test_a_calendar_slug_must_be_unique_except_against_itself()
    {
        $this->assertStringEndsWith('unique:fevent_calendars,slug', CalendarRequest::createRules()['slug']);
        $this->assertStringEndsWith('unique:fevent_calendars,slug,7,id', CalendarRequest::updateRules(7)['slug']);
    }

    public function test_a_guest_email_is_unique_per_event_not_site_wide()
    {
        $this->assertStringContainsString('unique:fevent_guests,email,NULL,id,event_id,42', GuestRequest::createRules(42)['email']);
        $this->assertStringContainsString('unique:fevent_guests,user_id,NULL,id,event_id,42', GuestRequest::createRules(42)['user_id']);
        $this->assertStringContainsString('unique:fevent_guests,email,9,id,event_id,42', GuestRequest::updateRules(42, 9)['email']);
    }

    /**
     * @dataProvider pageSizes
     */
    public function test_page_size_is_clamped($requested, $expected)
    {
        $params = $requested === null ? [] : ['per_page' => $requested];

        $this->assertSame($expected, EventRequest::perPage($this->request($params)));
    }

    public function pageSizes()
    {
        return [
            'default'    => [null, 20],
            'zero'       => [0, 20],
            'negative'   => [-5, 20],
            'in range'   => [50, 50],
            'over the cap' => [5000, 100],
        ];
    }

    public function test_sorting_only_accepts_whitelisted_columns_and_directions()
    {
        $this->assertSame(['start_at', 'DESC'], EventRequest::sorting($this->request(['order_by' => 'start_at', 'order_type' => 'desc'])));
        $this->assertSame(['id', 'ASC'], EventRequest::sorting($this->request(['order_by' => 'post_title; DROP TABLE x', 'order_type' => 'sideways'])));
        $this->assertSame(['id', 'DESC'], GuestRequest::sorting($this->request([])));
    }

    /**
     * @dataProvider reminderTimes
     */
    public function test_a_reminder_must_fall_between_five_minutes_and_thirty_days($value, $unit, $isValid)
    {
        $rule = SettingsRequest::emailNotificationRules()['reminder_to_guest.times.*'][0];

        $message = $rule('reminder_to_guest.times.0', ['value' => $value, 'unit' => $unit]);

        $isValid ? $this->assertNull($message) : $this->assertIsString($message);
    }

    public function reminderTimes()
    {
        return [
            'four minutes'      => [4, 'minutes', false],
            'five minutes'      => [5, 'minutes', true],
            'thirty days'       => [30, 'days', true],
            'thirty-one days'   => [31, 'days', false],
            'thirty days + 1h'  => [721, 'hours', false],
            'zero'              => [0, 'hours', false],
            'negative'          => [-5, 'hours', false],
            'decimal'           => [1.5, 'hours', false],
            'empty'             => ['', 'hours', false],
        ];
    }

    public function test_a_report_range_defaults_to_the_last_seven_days()
    {
        [$start, $end] = ReportRequest::resolveRange($this->request([]));

        $this->assertSame(gmdate('Y-m-d'), $end);
        $this->assertSame(gmdate('Y-m-d', strtotime($end . ' -6 days')), $start);
    }

    public function test_a_reversed_report_range_is_swapped()
    {
        $range = ReportRequest::resolveRange($this->request(['start_date' => '2026-10-10', 'end_date' => '2026-10-01']));

        $this->assertSame(['2026-10-01', '2026-10-10'], $range);
    }

    public function test_an_invalid_report_date_falls_back_to_the_default()
    {
        [$start, $end] = ReportRequest::resolveRange($this->request(['start_date' => "2026-10-01' OR 1=1", 'end_date' => '2026-10-10']));

        $this->assertSame(['2026-10-04', '2026-10-10'], [$start, $end]);
    }

    public function test_a_capacity_is_a_whole_number_and_none_means_unlimited()
    {
        $this->assertSame('nullable|integer|min:0', EventRequest::RULES['capacity']);
        $this->assertSame(25, EventRequest::sanitize(['capacity' => '25'])['capacity']);
        $this->assertSame(0, EventRequest::format($this->eventInput())['meta']['capacity']);
    }

    public function test_the_waitlist_switch_is_yes_or_no_and_starts_off()
    {
        $this->assertSame('nullable|string|in:yes,no', EventRequest::RULES['waitlist']);
        $this->assertSame('no', EventRequest::format($this->eventInput())['meta']['waitlist']);
        $this->assertSame('yes', EventRequest::format($this->eventInput(['waitlist' => 'yes']))['meta']['waitlist']);
    }

    public function test_the_spots_left_switch_is_yes_or_no_and_starts_off()
    {
        $this->assertSame('nullable|string|in:yes,no', EventRequest::RULES['show_spots_left']);
        $this->assertSame('no', EventRequest::format($this->eventInput())['meta']['show_spots_left']);
        $this->assertSame('yes', EventRequest::format($this->eventInput(['show_spots_left' => 'yes']))['meta']['show_spots_left']);
    }
}
