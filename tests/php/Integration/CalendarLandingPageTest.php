<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\LandingPageHandler;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The public calendar page: who can open it, which events it lists, and how the
 * view settings and paging shape it. The page reads its state from the query string.
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
                return $this->calendarPage($slug);
            }
        };

        return $handler->open($slug);
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
