<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\LandingPageHandler;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The public event page: which events open, who sees the meeting link, and the calendar file it offers.
 */
class EventLandingPageTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    private function handler()
    {
        return new class extends LandingPageHandler {
            public function event($calendarSlug, $eventSlug)
            {
                return $this->eventPage($calendarSlug, $eventSlug);
            }
        };
    }

    private function page($event, $calendarSlug = null)
    {
        return $this->handler()->event($calendarSlug ?: $event->calendar->slug, $event->post->post_name);
    }

    public function test_an_event_opens_when_it_and_its_calendar_are_public_or_unlisted()
    {
        $calendar = $this->createCalendar(['status' => 'unlisted']);
        $other = $this->createCalendar();

        $public = $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Opening night']);
        $unlisted = $this->createEvent(['calendar_id' => $calendar->id, 'status' => 'unlisted']);

        $this->assertStringContainsString('Opening night', $this->page($public)['body']);
        $this->assertNotNull($this->page($unlisted));
        // Only under its own calendar's slug.
        $this->assertNull($this->page($public, $other->slug));

        foreach (['private', 'draft'] as $status) {
            $this->assertNull($this->page($this->createEvent(['calendar_id' => $calendar->id, 'status' => $status])));
        }

        $hidden = $this->createEvent(['calendar_id' => $this->createCalendar(['status' => 'private'])->id]);
        $this->assertNull($this->page($hidden));
    }

    public function test_visitors_see_the_meeting_link_only_on_no_entry_events()
    {
        $online = ['venue_type' => 'online'];
        $link = ['online_meeting_url' => 'https://meet.example.test/fe-room'];

        $rsvp = $this->page($this->createEvent($online, $link + ['entry_type' => 'rsvp']))['body'];
        $none = $this->page($this->createEvent($online, $link + ['entry_type' => 'none']))['body'];

        $this->assertStringNotContainsString('fe-room', $rsvp);
        $this->assertStringContainsString('Please register for the event to see the meeting link.', $rsvp);
        $this->assertStringNotContainsString('Add to calendar', $rsvp);

        $this->assertStringContainsString('href="https://meet.example.test/fe-room"', $none);
        $this->assertStringContainsString('Add to calendar', $none);
    }

    public function test_the_calendar_file_escapes_text_and_folds_long_lines()
    {
        $event = $this->createEvent([
            'title'       => 'Talks, demos; and Q&A',
            'description' => '<p>' . str_repeat('Long description ', 12) . '</p><ul><li>Café</li></ul>',
        ], ['entry_type' => 'none']);

        preg_match('/href="data:text\/calendar;charset=utf-8,([^"]+)"/', $this->page($event)['body'], $match);
        $file = rawurldecode($match[1]);
        $lines = explode("\r\n", $file);

        $this->assertContains('SUMMARY:Talks\, demos\; and Q&A', $lines);
        $this->assertStringContainsString('• Café', str_replace("\r\n ", '', $file));
        $this->assertLessThanOrEqual(75, max(array_map('strlen', $lines)));
        $this->assertNotEmpty(preg_grep('/^ /', $lines), 'The long description folds onto continuation lines.');
    }
}
