<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\FrontEndHandler;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * [fluent_events]: which calendars and events it shows, its calendar chips, and how its props, the
 * view settings and paging shape it. The list reads the visitor's state from the query string.
 */
class CalendarLandingPageTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        $this->createPublicPages();
        Settings::update(Settings::GENERAL, ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no']);
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    // $slug is the visitor's `?fevent_calendar=`; $atts the shortcode's props.
    private function page($slug, array $query = [], array $atts = [])
    {
        $_GET = $slug !== '' ? ['fevent_calendar' => $slug] + $query : $query;

        $handler = new class extends FrontEndHandler {
            public function open(array $atts)
            {
                return $this->eventsBody($atts);
            }
        };

        return ['body' => $handler->open(array_merge(['calendar' => '', 'view' => '', 'filter' => '', 'view_switch' => '', 'show_calendars' => 'yes'], $atts))];
    }

    // The query args of the calendar header link marked data-fe-nav="$key".
    private function navArgs($body, $key)
    {
        preg_match('/<a\b[^>]*?href="([^"]*)"[^>]*?data-fe-nav="' . $key . '"/', $body, $link);
        $this->assertNotEmpty($link, "The {$key} link is there");
        parse_str((string) parse_url(html_entity_decode($link[1]), PHP_URL_QUERY), $args);

        return $args;
    }

    public function test_the_home_lists_public_events_from_every_public_calendar_and_names_each_ones_calendar()
    {
        $first = $this->createCalendar(['title' => 'Zebra First Club']);
        $second = $this->createCalendar(['title' => 'Zebra Second Club']);
        $private = $this->createCalendar(['title' => 'Zebra Private Club', 'status' => 'private']);

        $event = $this->createEvent(['calendar_id' => $first->id, 'title' => 'Zebra first night']);
        $this->createEvent(['calendar_id' => $second->id, 'title' => 'Zebra second night']);
        $this->createEvent(['calendar_id' => $private->id, 'title' => 'Zebra private night']);

        $body = $this->page('', ['view' => 'cards', 'fevent_search' => 'Zebra'])['body'];

        $this->assertStringContainsString('Zebra first night', $body);
        $this->assertStringContainsString('Zebra second night', $body);
        $this->assertStringNotContainsString('Zebra private night', $body);
        // Chips for the public calendars only; each event links to its own page and is labelled with its calendar.
        $this->assertStringContainsString('Zebra Second Club', $body);
        $this->assertStringNotContainsString('Zebra Private Club', $body);
        $this->assertStringContainsString(esc_url(EventPageService::url($event)), $body);
        $this->assertStringContainsString('fe-event-tile__calendar"', $body);
    }

    public function test_a_calendar_page_lists_only_its_events_under_its_selected_chip()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Own Club']);
        $other = $this->createCalendar(['title' => 'Zebra Other Club']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra own night']);
        $this->createEvent(['calendar_id' => $other->id, 'title' => 'Zebra other night']);

        $body = $this->page($calendar->slug, ['view' => 'cards', 'fevent_search' => 'Zebra'])['body'];

        $this->assertStringContainsString('Zebra own night', $body);
        $this->assertStringNotContainsString('Zebra other night', $body);
        $this->assertMatchesRegularExpression('/fe-calendar-chips__chip--active"(?:(?!<\/a>).)*Zebra Own Club/s', $body);
        $this->assertStringContainsString('fe-event-tile__calendar"', $body, 'The label stays, so picking a chip does not reflow the cards');
        // The calendar is a query arg the other controls carry: chips switch it and keep the view, search keeps it.
        $this->assertStringContainsString('?fevent_calendar=' . $other->slug . '&#038;view=cards', $body);
        $this->assertStringContainsString('name="fevent_calendar" value="' . $calendar->slug . '"', $body);
    }

    // Picking a calendar, or searching, while looking at another week stays on that week.
    public function test_the_calendar_view_keeps_its_span_and_date_across_calendar_chips_and_search()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Month Club']);

        $body = $this->page('', ['view' => 'calendar', 'span' => 'week', 'date' => '2027-03-10'])['body'];

        preg_match('/href="([^"]*[?&;]fevent_calendar=' . preg_quote($calendar->slug, '/') . '[^"]*)"/', $body, $chip);
        $this->assertNotEmpty($chip, 'The calendar has a chip');
        parse_str(parse_url(html_entity_decode($chip[1]), PHP_URL_QUERY), $args);
        ksort($args);
        $this->assertSame(['date' => '2027-03-10', 'fevent_calendar' => $calendar->slug, 'filter' => 'upcoming', 'span' => 'week', 'view' => 'calendar'], $args);
        $this->assertStringContainsString('name="span" value="week"', $body);
        $this->assertStringContainsString('name="date" value="2027-03-10"', $body);
        $this->assertStringNotContainsString('No upcoming events.', $body, 'The empty grid says so itself');
    }

    public function test_the_calendar_header_steps_by_its_span_and_today_drops_the_date()
    {
        $body = $this->page('', ['view' => 'calendar', 'date' => '2027-03-10'])['body'];

        // A month steps from its 1st, so the 31st of one can't skip the shorter month after it.
        $this->assertSame('2027-02-01', $this->navArgs($body, 'prev')['date']);
        $this->assertSame('2027-04-01', $this->navArgs($body, 'next')['date']);
        $this->assertSame(['view' => 'calendar', 'filter' => 'upcoming'], $this->navArgs($body, 'today'));
        $this->assertSame(['view' => 'calendar', 'filter' => 'upcoming', 'date' => '2027-03-10', 'span' => 'week'], $this->navArgs($body, 'span-week'));
        $this->assertArrayNotHasKey('span', $this->navArgs($body, 'span-month'), 'Month is the default, left out of the link');

        $week = $this->page('', ['view' => 'calendar', 'span' => 'week', 'date' => '2027-03-10'])['body'];
        $this->assertSame(['2027-03-03', 'week'], [$this->navArgs($week, 'prev')['date'], $this->navArgs($week, 'prev')['span']]);
        $this->assertSame('2027-03-17', $this->navArgs($week, 'next')['date']);
        $this->assertSame('week', $this->navArgs($week, 'today')['span']);

        $day = $this->page('', ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-10'])['body'];
        $this->assertSame('2027-03-09', $this->navArgs($day, 'prev')['date']);
        $this->assertSame('2027-03-11', $this->navArgs($day, 'next')['date']);
    }

    public function test_an_unknown_span_or_date_falls_back_to_this_month()
    {
        $body = $this->page('', ['view' => 'calendar', 'span' => 'year', 'date' => 'soon'])['body'];

        $this->assertStringContainsString('fe-month__grid', $body);
        $this->assertStringContainsString(esc_html(wp_date('F Y')), $body);
    }

    public function test_the_month_shows_whole_weeks_and_a_multi_day_event_on_each_of_its_days_in_its_calendar_colour()
    {
        $calendar = $this->createCalendar(['meta' => ['theme_color' => '#0099ff']]);
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'title'       => 'Three day summit',
            'start_at'    => get_gmt_from_date('2027-03-10 18:00:00'),
            'end_at'      => get_gmt_from_date('2027-03-12 20:00:00'),
        ]);

        $body = $this->page($calendar->slug, ['view' => 'calendar', 'date' => '2027-03-10'])['body'];

        $cells = substr_count($body, 'class="fe-month__cell');
        $this->assertSame(0, $cells % 7, 'Whole weeks');
        $this->assertSame(31, $cells - substr_count($body, 'fe-month__cell--outside'), 'The neighbouring months\' days are marked outside');

        $this->assertSame(3, substr_count($body, '>Three day summit<'));
        $this->assertStringContainsString('Day 2', $body);
        $this->assertStringContainsString('Day 3', $body);
        $this->assertSame(3, substr_count($body, '--fe-event-color: #0099ff;'));
    }

    public function test_the_week_and_day_views_place_an_event_from_its_start_to_its_end()
    {
        $calendar = $this->createCalendar(['meta' => ['theme_color' => '#0099ff']]);
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'title'       => 'Morning standup',
            'start_at'    => get_gmt_from_date('2027-03-10 09:00:00'),
            'end_at'      => get_gmt_from_date('2027-03-10 10:30:00'),
        ]);

        $week = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'week', 'date' => '2027-03-10'])['body'];

        $this->assertSame(7, substr_count($week, 'class="fe-week__col"'));
        $this->assertSame(24, substr_count($week, 'class="fe-week__time"'));
        // 48px an hour: from 9:00, an hour and a half tall less the 2px gap.
        $this->assertStringContainsString('style="top: 432px; height: 70px; --fe-lane: 0; --fe-lanes: 1;--fe-event-color: #0099ff;"', $week);
        $this->assertStringContainsString('Morning standup', $week);

        $day = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-10'])['body'];

        $this->assertStringContainsString('fe-week--day', $day);
        $this->assertSame(1, substr_count($day, 'class="fe-week__col"'));
        $this->assertStringContainsString('top: 432px; height: 70px;', $day);
        $this->assertStringContainsString(esc_html(wp_date(get_option('date_format'), strtotime('2027-03-10 12:00:00 UTC'))), $day);

        $nextDay = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-11'])['body'];
        $this->assertStringNotContainsString('Morning standup', $nextDay);
    }

    // Ending at 00:00 is ending the day before: the next day gets no "Day 2" and no empty block at midnight.
    public function test_an_event_ending_at_midnight_stays_on_its_own_day()
    {
        $calendar = $this->createCalendar();
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'title'       => 'Midnight end party',
            'start_at'    => get_gmt_from_date('2027-03-20 22:00:00'),
            'end_at'      => get_gmt_from_date('2027-03-21 00:00:00'),
        ]);

        $month = $this->page($calendar->slug, ['view' => 'calendar', 'date' => '2027-03-20'])['body'];
        $this->assertSame(1, substr_count($month, '>Midnight end party<'));
        $this->assertStringNotContainsString('Day 2', $month);

        $day = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-20'])['body'];
        $this->assertStringContainsString('title="Midnight end party" style="top: 1056px; height: 94px;', $day, 'From 10 PM to the bottom of the grid');

        $nextDay = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-21'])['body'];
        $this->assertStringNotContainsString('Midnight end party', $nextDay);
    }

    // A daylight-saving day is 23 or 25 hours long, but the grid draws 24 hour labels: events sit by the clock.
    public function test_a_daylight_saving_day_places_events_by_the_clock()
    {
        $zone = get_option('timezone_string');
        update_option('timezone_string', 'America/New_York');

        try {
            $calendar = $this->createCalendar();
            $times = [
                'Spring standup' => ['2027-03-14 09:00:00', '2027-03-14 10:30:00'],
                'Fall late talk' => ['2027-11-07 23:00:00', '2027-11-07 23:30:00'],
            ];

            foreach ($times as $title => [$start, $end]) {
                $this->createEvent(['calendar_id' => $calendar->id, 'title' => $title, 'start_at' => get_gmt_from_date($start), 'end_at' => get_gmt_from_date($end)]);
            }

            $spring = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-14'])['body'];
            $this->assertStringContainsString('title="Spring standup" style="top: 432px; height: 70px;', $spring, 'On the 9 AM line, after the skipped hour');

            $fall = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-11-07'])['body'];
            $this->assertStringContainsString('title="Fall late talk" style="top: 1104px; height: 22px;', $fall, 'On the 11 PM line, inside the grid, after the repeated hour');
        } finally {
            update_option('timezone_string', $zone);
        }
    }

    // A calendar that isn't public, picked by link or prop while the chips show, falls back to All.
    public function test_a_private_draft_or_unknown_calendar_falls_back_to_all()
    {
        $this->createCalendar(['title' => 'Zebra Shown Club']);
        $shown = $this->createCalendar(['title' => 'Zebra Other Shown Club']);
        $this->createEvent(['calendar_id' => $shown->id, 'title' => 'Zebra shown night']);

        foreach ([$this->createCalendar(['status' => 'private'])->slug, $this->createCalendar(['status' => 'draft'])->slug, 'fe-test-no-such-calendar'] as $slug) {
            foreach ([$this->page($slug, ['fevent_search' => 'Zebra']), $this->page('', ['fevent_search' => 'Zebra'], ['calendar' => $slug])] as $page) {
                $this->assertStringContainsString('Zebra shown night', $page['body'], $slug);
                $this->assertMatchesRegularExpression('/fe-calendar-chips__chip--active"(?:(?!<\/a>).)*>All</s', $page['body'], $slug);
            }
        }
    }

    // `calendar` picks the first chip; the visitor can still pick another, or All, which its link has to name.
    public function test_the_calendar_prop_preselects_its_chip_and_all_stays_pickable()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Preset Club']);
        $other = $this->createCalendar(['title' => 'Zebra Unpicked Club']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra preset night']);
        $this->createEvent(['calendar_id' => $other->id, 'title' => 'Zebra unpicked night']);

        $preset = $this->page('', ['view' => 'cards', 'fevent_search' => 'Zebra'], ['calendar' => $calendar->slug])['body'];
        $all = $this->page('', ['view' => 'cards', 'fevent_search' => 'Zebra', 'fevent_calendar' => ''], ['calendar' => $calendar->slug])['body'];
        $switched = $this->page($other->slug, ['view' => 'cards', 'fevent_search' => 'Zebra'], ['calendar' => $calendar->slug])['body'];

        $this->assertStringContainsString('Zebra preset night', $preset);
        $this->assertStringNotContainsString('Zebra unpicked night', $preset);
        $this->assertMatchesRegularExpression('/fe-calendar-chips__chip--active"(?:(?!<\/a>).)*Zebra Preset Club/s', $preset);
        // add_query_arg() writes an empty value as a bare `fevent_calendar`, which PHP still reads as set.
        $this->assertMatchesRegularExpression('/href="[^"]*\\?fevent_calendar(?:&#038;[^"]*)?"[^>]*>\\s*(?:<[^>]+>\\s*)*All/', $preset, 'All names an empty calendar');

        $this->assertStringContainsString('Zebra preset night', $all);
        $this->assertStringContainsString('Zebra unpicked night', $all);
        $this->assertStringContainsString('name="fevent_calendar" value=""', $all, 'Searching keeps All');

        $this->assertStringContainsString('Zebra unpicked night', $switched);
        $this->assertStringNotContainsString('Zebra preset night', $switched);
    }

    // With the chips hidden the list is fixed: to the calendar prop, or to every calendar, whatever the link says.
    public function test_hidden_chips_fix_the_list()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Fixed Club']);
        $other = $this->createCalendar(['title' => 'Zebra Loose Club']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra fixed night']);
        $this->createEvent(['calendar_id' => $other->id, 'title' => 'Zebra loose night']);

        $fixed = $this->page($other->slug, ['view' => 'cards', 'fevent_search' => 'Zebra'], ['calendar' => $calendar->slug, 'show_calendars' => 'no'])['body'];
        $every = $this->page($other->slug, ['view' => 'cards', 'fevent_search' => 'Zebra'], ['show_calendars' => 'no'])['body'];
        $hidden = $this->page('', ['view' => 'cards', 'fevent_search' => 'Zebra'], ['calendar' => $this->createCalendar(['status' => 'private'])->slug, 'show_calendars' => 'no'])['body'];

        $this->assertStringContainsString('Zebra fixed night', $fixed);
        $this->assertStringNotContainsString('Zebra loose night', $fixed);
        $this->assertStringNotContainsString('data-fe-chips', $fixed);
        $this->assertStringNotContainsString('fe-event-tile__calendar"', $fixed, 'One fixed calendar needs no label');
        $this->assertStringNotContainsString('name="fevent_calendar"', $fixed);

        $this->assertStringContainsString('Zebra fixed night', $every);
        $this->assertStringContainsString('Zebra loose night', $every);
        $this->assertStringNotContainsString('data-fe-chips', $every);

        // A fixed calendar that isn't public lists nothing.
        $this->assertStringNotContainsString('Zebra fixed night', $hidden);
        $this->assertStringNotContainsString('Zebra loose night', $hidden);
    }

    // `view` and `filter` are where the list starts; the visitor's link wins unless `view_switch="no"` fixes the view.
    public function test_view_and_filter_props_start_the_list_and_override_the_settings()
    {
        Settings::update(Settings::GENERAL, ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'yes']);
        $calendar = $this->createCalendar();
        $this->createEvent(['calendar_id' => $calendar->id]);

        $started = $this->page('', [], ['view' => 'calendar', 'view_switch' => 'yes'])['body'];
        $linked = $this->page('', ['view' => 'cards'], ['view' => 'calendar', 'view_switch' => 'yes'])['body'];
        $fixed = $this->page('', ['view' => 'cards'], ['view' => 'calendar', 'view_switch' => 'no'])['body'];
        $past = $this->page('', [], ['filter' => 'past'])['body'];

        $this->assertStringContainsString('fe-month__grid', $started);
        $this->assertStringContainsString('fe-switch__option--icon', $started, 'The prop turns the switch back on over the setting');
        $this->assertStringContainsString('fe-event-grid', $linked);
        $this->assertStringContainsString('fe-month__grid', $fixed);
        $this->assertStringNotContainsString('fe-switch__option--icon', $fixed);
        $this->assertMatchesRegularExpression('/fe-switch__option--active"\s*href="[^"]*filter=past/', $past);
    }

    // The remembered view and tab are one per browser, so a choice made on another list mustn't move a preset.
    public function test_a_view_or_filter_remembered_on_another_list_leaves_a_preset_alone()
    {
        Settings::update(Settings::GENERAL, ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no']);
        $this->createEvent(['calendar_id' => $this->createCalendar()->id]);

        $plain = $this->page('')['body'];
        $preset = $this->page('', [], ['view' => 'calendar', 'filter' => 'past'])['body'];
        $viewOnly = $this->page('', [], ['view' => 'cards'])['body'];

        $this->assertStringContainsString('fevent_portal_events_view', $plain);
        $this->assertStringContainsString('fevent_portal_events_filter', $plain);
        $this->assertStringNotContainsString('fevent_portal_events_view', $preset);
        $this->assertStringNotContainsString('fevent_portal_events_filter', $preset);
        $this->assertStringNotContainsString('fevent_portal_events_view', $viewOnly);
        $this->assertStringContainsString('fevent_portal_events_filter', $viewOnly);
    }

    public function test_only_public_events_are_listed()
    {
        $calendar = $this->createCalendar();

        foreach (['public', 'unlisted', 'private', 'draft'] as $status) {
            $this->createEvent(['calendar_id' => $calendar->id, 'status' => $status, 'title' => "Status {$status} night"]);
        }

        $body = $this->page($calendar->slug)['body'];

        $this->assertStringContainsString('Status public night', $body);
        $this->assertStringNotContainsString('Status unlisted night', $body);
        $this->assertStringNotContainsString('Status private night', $body);
        $this->assertStringNotContainsString('Status draft night', $body);
    }

    public function test_a_locked_view_ignores_the_link_and_hides_the_switch()
    {
        Settings::update(Settings::GENERAL, ['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes']);
        $calendar = $this->createCalendar();
        $this->createEvent(['calendar_id' => $calendar->id]);

        $body = $this->page($calendar->slug, ['view' => 'calendar'])['body'];

        $this->assertStringContainsString('fe-event-grid', $body);
        $this->assertStringNotContainsString('fe-month__grid', $body);
        $this->assertStringNotContainsString('fe-switch__option--icon', $body);
    }

    // Events on at the same time sit side by side; one that starts after them all are over gets the full width again.
    public function test_overlapping_events_share_the_day_in_lanes()
    {
        $calendar = $this->createCalendar();
        $times = [
            'Lane long'  => ['09:00', '12:00'],
            'Lane short' => ['09:30', '10:00'],
            'Lane later' => ['10:30', '11:00'],
            'Lane alone' => ['14:00', '15:00'],
        ];

        foreach ($times as $title => [$start, $end]) {
            $this->createEvent([
                'calendar_id' => $calendar->id,
                'title'       => $title,
                'start_at'    => get_gmt_from_date("2027-03-10 {$start}:00"),
                'end_at'      => get_gmt_from_date("2027-03-10 {$end}:00"),
            ]);
        }

        $body = $this->page($calendar->slug, ['view' => 'calendar', 'span' => 'day', 'date' => '2027-03-10'])['body'];

        $lanes = [];
        preg_match_all('/title="([^"]+)" style="[^"]*--fe-lane: (\d+); --fe-lanes: (\d+);/', $body, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $lanes[$match[1]] = [(int) $match[2], (int) $match[3]];
        }

        // The later one reuses the lane the short one left free.
        $this->assertSame(['Lane long' => [0, 2], 'Lane short' => [1, 2], 'Lane later' => [1, 2], 'Lane alone' => [0, 1]], $lanes);
    }

    public function test_a_busy_day_lists_every_event_in_its_more_popover()
    {
        $calendar = $this->createCalendar();

        for ($i = 1; $i <= 4; $i++) {
            $this->createEvent([
                'calendar_id' => $calendar->id,
                'title'       => "Popover event {$i}",
                'start_at'    => "2027-03-10 12:0{$i}:00",
                'end_at'      => "2027-03-10 13:0{$i}:00",
            ]);
        }

        $body = $this->page($calendar->slug, ['view' => 'calendar', 'date' => '2027-03-10'])['body'];

        $this->assertStringContainsString('popovertarget="fe-month-day-2027-03-10"', $body);
        $this->assertStringContainsString('+1 more', $body);
        $this->assertStringContainsString('Wednesday, Mar 10', $body);
        // The grid shows three chips; the fourth appears only in the popover.
        $this->assertSame(1, substr_count($body, '>Popover event 4<'));
        $this->assertSame(2, substr_count($body, '>Popover event 1<'));
    }

    public function test_upcoming_events_are_paged_ten_at_a_time()
    {
        $calendar = $this->createCalendar();

        for ($i = 1; $i <= 11; $i++) {
            $this->createEvent([
                'calendar_id' => $calendar->id,
                'title'       => sprintf('Paged event %02d', $i),
                'start_at'    => gmdate('Y-m-d H:i:s', strtotime("+{$i} days")),
                'end_at'      => gmdate('Y-m-d H:i:s', strtotime("+{$i} days +1 hour")),
            ]);
        }

        $first = $this->page($calendar->slug)['body'];
        $second = $this->page($calendar->slug, ['fevent_page' => '2'])['body'];

        $this->assertStringContainsString('Paged event 01', $first);
        $this->assertStringContainsString('Paged event 10', $first);
        $this->assertStringNotContainsString('Paged event 11', $first);
        $this->assertStringContainsString('Page 1 of 2', $first);
        $this->assertStringContainsString('Paged event 11', $second);
        $this->assertStringNotContainsString('Paged event 10', $second);

        $pastTheEnd = $this->page($calendar->slug, ['fe_page' => '99'])['body'];

        $this->assertStringContainsString('Paged event 11', $pastTheEnd);
        $this->assertStringContainsString('Page 2 of 2', $pastTheEnd);
        $this->assertStringNotContainsString('No upcoming events.', $pastTheEnd);
        $this->assertStringContainsString('"fe_page", 2)', $this->footer(), 'The address moves to the page shown');

        $this->page($calendar->slug, ['fe_page' => '2']);

        $this->assertSame('', $this->footer(), 'A page that exists keeps its address');
    }

    // What the footer prints for the lists rendered since the last call.
    private function footer()
    {
        ob_start();
        (new FrontEndHandler())->correctPageInAddress();
        $footer = trim((string) ob_get_clean());

        $shown = new \ReflectionProperty(FrontEndHandler::class, 'shownPage');
        $shown->setAccessible(true);
        $shown->setValue(null, 0);

        return $footer;
    }

    public function test_the_past_tab_and_search_narrow_the_list()
    {
        $calendar = $this->createCalendar();
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Coming soon social']);
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'title'       => 'Last week social',
            'start_at'    => gmdate('Y-m-d H:i:s', strtotime('-7 days')),
            'end_at'      => gmdate('Y-m-d H:i:s', strtotime('-7 days +1 hour')),
        ]);
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'title'       => 'Last month workshop',
            'start_at'    => gmdate('Y-m-d H:i:s', strtotime('-30 days')),
            'end_at'      => gmdate('Y-m-d H:i:s', strtotime('-30 days +1 hour')),
        ]);

        $past = $this->page($calendar->slug, ['filter' => 'past'])['body'];
        $searched = $this->page($calendar->slug, ['filter' => 'past', 'fevent_search' => 'workshop'])['body'];

        $this->assertStringNotContainsString('Coming soon social', $past);
        $this->assertStringContainsString('Last week social', $past);
        $this->assertStringContainsString('Last month workshop', $searched);
        $this->assertStringNotContainsString('Last week social', $searched);
    }

    // WordPress keeps a static front page only while the URL carries none of its own query vars: a chip,
    // a search or an event's calendar link must not, or the All Events Page as front page shows the blog.
    public function test_the_lists_links_and_search_form_carry_no_wordpress_query_var()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Front Club']);
        $this->createCalendar(['title' => 'Zebra Other Front Club']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra front night']);

        // Both spellings, so the list keeps its calendar and search whichever names it reads.
        $body = $this->page($calendar->slug, ['view' => 'cards', 'search' => 'Zebra', 'fevent_search' => 'Zebra', 'fevent_calendar' => $calendar->slug])['body'];
        $eventLink = (new class extends FrontEndHandler {
            public function link($slug)
            {
                return $this->calendarUrl($slug);
            }
        })->link($calendar->slug);

        preg_match_all('/href="([^"]*)"/', $body, $hrefs);
        preg_match('/<form class="fe-events-page__search".*?<\/form>/s', $body, $form);
        preg_match_all('/name="([^"]+)"/', $form[0], $fields);
        $keys = $fields[1];

        foreach (array_merge($hrefs[1], [$eventLink]) as $href) {
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $args);
            $keys = array_merge($keys, array_keys($args));
        }

        $this->assertNotEmpty($keys);
        $this->assertSame([], array_values(array_unique(array_intersect($keys, apply_filters('query_vars', $GLOBALS['wp']->public_query_vars)))));
    }

    // Each row links through get_permalink(), which reads the event's post: a page of events must not cost a query each.
    public function test_the_list_and_calendar_load_their_events_posts_in_one_go()
    {
        global $wpdb;

        $calendar = $this->createCalendar(['title' => 'Zebra Query Club']);
        $ids = [];

        foreach ([1, 2, 3] as $n) {
            $ids[] = (int) $this->createEvent(['calendar_id' => $calendar->id, 'title' => "Zebra query night {$n}"])->post_id;
        }

        $lookups = [];
        $this->addTestFilter('query', function ($sql) use (&$lookups, $wpdb, $ids) {
            if (preg_match("/^SELECT \* FROM {$wpdb->posts} WHERE ID = (\d+)/", $sql, $match) && in_array((int) $match[1], $ids, true)) {
                $lookups[] = (int) $match[1];
            }

            return $sql;
        });

        foreach (['cards', 'calendar'] as $view) {
            array_map('clean_post_cache', $ids);
            $lookups = [];

            $body = $this->page($calendar->slug, ['view' => $view, 'date' => gmdate('Y-m-d', strtotime('+3 days'))])['body'];

            $this->assertStringContainsString('Zebra query night 3', $body, $view);
            $this->assertSame([], $lookups, "{$view}: no event's post is fetched on its own");
        }
    }
}
