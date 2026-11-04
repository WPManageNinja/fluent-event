<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\LandingPageHandler;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The public events list behind the landing home and each calendar's page: which calendars and
 * events it shows, its calendar chips, and how the view settings and paging shape it. The page
 * reads its state from the query string.
 */
class CalendarLandingPageTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        Settings::update(Settings::GENERAL, ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no']);
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    private function page($slug, array $query = [])
    {
        $_GET = $query;

        $handler = new class extends LandingPageHandler {
            public function open($slug)
            {
                return $this->eventsPage($slug);
            }
        };

        return $handler->open($slug);
    }

    public function test_the_home_lists_public_events_from_every_public_calendar_and_names_each_ones_calendar()
    {
        $first = $this->createCalendar(['title' => 'Zebra First Club']);
        $second = $this->createCalendar(['title' => 'Zebra Second Club']);
        $unlisted = $this->createCalendar(['title' => 'Zebra Unlisted Club', 'status' => 'unlisted']);
        $private = $this->createCalendar(['title' => 'Zebra Private Club', 'status' => 'private']);

        $event = $this->createEvent(['calendar_id' => $first->id, 'title' => 'Zebra first night']);
        $this->createEvent(['calendar_id' => $second->id, 'title' => 'Zebra second night']);
        $this->createEvent(['calendar_id' => $unlisted->id, 'title' => 'Zebra unlisted night']);
        $this->createEvent(['calendar_id' => $private->id, 'title' => 'Zebra private night']);

        $body = $this->page('', ['view' => 'cards', 'search' => 'Zebra'])['body'];

        $this->assertStringContainsString('Zebra first night', $body);
        $this->assertStringContainsString('Zebra second night', $body);
        $this->assertStringNotContainsString('Zebra unlisted night', $body);
        $this->assertStringNotContainsString('Zebra private night', $body);
        // Chips for the public calendars only; each event links to its own page and is labelled with its calendar.
        $this->assertStringContainsString('Zebra Second Club', $body);
        $this->assertStringNotContainsString('Zebra Unlisted Club', $body);
        $this->assertStringContainsString('/' . Settings::portalSlug() . '/' . $event->post->post_name . '/', $body);
        $this->assertStringContainsString('fe-event-tile__calendar"', $body);
    }

    public function test_a_calendar_page_lists_only_its_events_under_its_selected_chip()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Own Club']);
        $other = $this->createCalendar(['title' => 'Zebra Other Club']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra own night']);
        $this->createEvent(['calendar_id' => $other->id, 'title' => 'Zebra other night']);

        $body = $this->page($calendar->slug, ['view' => 'cards', 'search' => 'Zebra'])['body'];

        $this->assertStringContainsString('Zebra own night', $body);
        $this->assertStringNotContainsString('Zebra other night', $body);
        $this->assertMatchesRegularExpression('/fe-calendar-chips__chip--active"(?:(?!<\/a>).)*Zebra Own Club/s', $body);
        $this->assertStringContainsString('fe-event-tile__calendar"', $body, 'The label stays, so picking a chip does not reflow the cards');
        // The calendar is a query arg the other controls carry: chips switch it and keep the view, search keeps it.
        $this->assertStringContainsString('/' . Settings::portalSlug() . '/?calendar=' . $other->slug . '&#038;view=cards', $body);
        $this->assertStringContainsString('name="calendar" value="' . $calendar->slug . '"', $body);
    }

    // Picking a calendar, or searching, while looking at another month stays on that month.
    public function test_the_month_view_keeps_its_month_across_calendar_chips_and_search()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Month Club']);

        $body = $this->page('', ['view' => 'calendar', 'month' => '2027-03'])['body'];

        preg_match('/href="([^"]*[?&;]calendar=' . preg_quote($calendar->slug, '/') . '[^"]*)"/', $body, $chip);
        $this->assertNotEmpty($chip, 'The calendar has a chip');
        parse_str(parse_url(html_entity_decode($chip[1]), PHP_URL_QUERY), $args);
        $this->assertSame(['view' => 'calendar', 'filter' => 'upcoming', 'calendar' => $calendar->slug, 'month' => '2027-03'], $args);
        $this->assertStringContainsString('name="month" value="2027-03"', $body);
        $this->assertStringNotContainsString('No events this month.', $body, 'The empty grid says so itself');
    }

    public function test_an_unlisted_calendar_gets_its_chip_only_on_its_own_page()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Quiet Club', 'status' => 'unlisted']);

        $this->assertStringNotContainsString('Zebra Quiet Club', $this->page('')['body']);
        $this->assertMatchesRegularExpression('/fe-calendar-chips__chip--active"(?:(?!<\/a>).)*Zebra Quiet Club/s', $this->page($calendar->slug)['body']);
    }

    public function test_a_private_or_draft_calendar_has_no_page()
    {
        $this->assertNull($this->page($this->createCalendar(['status' => 'private'])->slug));
        $this->assertNull($this->page($this->createCalendar(['status' => 'draft'])->slug));
        $this->assertNull($this->page('fe-test-no-such-calendar'));
    }

    public function test_an_unlisted_calendar_opens_by_its_link()
    {
        $calendar = $this->createCalendar(['status' => 'unlisted', 'title' => 'Hidden Ledger Club']);

        $page = $this->page($calendar->slug);

        $this->assertNotNull($page);
        $this->assertStringContainsString('Hidden Ledger Club', $page['body']);
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

        $body = $this->page($calendar->slug, ['view' => 'calendar', 'month' => '2027-03'])['body'];

        $this->assertStringContainsString('popovertarget="fe-month-day-10"', $body);
        $this->assertStringContainsString('+1 more', $body);
        $this->assertStringContainsString('Wednesday, Mar 10', $body);
        // The grid shows three chips; the fourth appears only in the popover.
        $this->assertSame(1, substr_count($body, 'Popover event 4'));
        $this->assertSame(2, substr_count($body, 'Popover event 1'));
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
        $second = $this->page($calendar->slug, ['page' => '2'])['body'];

        $this->assertStringContainsString('Paged event 01', $first);
        $this->assertStringContainsString('Paged event 10', $first);
        $this->assertStringNotContainsString('Paged event 11', $first);
        $this->assertStringContainsString('Page 1 of 2', $first);
        $this->assertStringContainsString('Paged event 11', $second);
        $this->assertStringNotContainsString('Paged event 10', $second);
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
        $searched = $this->page($calendar->slug, ['filter' => 'past', 'search' => 'workshop'])['body'];

        $this->assertStringNotContainsString('Coming soon social', $past);
        $this->assertStringContainsString('Last week social', $past);
        $this->assertStringContainsString('Last month workshop', $searched);
        $this->assertStringNotContainsString('Last week social', $searched);
    }
}
