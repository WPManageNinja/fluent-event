<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\FrontEndHandler;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * [fluent_event]: which events open, who sees the meeting link, and the calendar file it offers.
 */
class EventLandingPageTest extends IntegrationTestCase
{
    private $pages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        $this->pages = $this->createPublicPages();
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    private function handler()
    {
        return new class extends FrontEndHandler {
            public function event($eventSlug)
            {
                $event = $this->findEvent($eventSlug);

                return $event ? ['body' => $this->eventBody($event)] : null;
            }

            public function calendar($slug)
            {
                $_GET = ['calendar' => $slug];

                return ['body' => $this->eventsBody(['calendar' => '', 'view' => '', 'filter' => '', 'view_switch' => '', 'show_calendars' => 'yes'])];
            }
        };
    }

    private function page($event)
    {
        return $this->handler()->event($event->post->post_name);
    }

    public function test_a_public_or_unlisted_event_in_a_public_calendar_opens()
    {
        $calendar = $this->createCalendar();

        $public = $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Opening night']);
        $unlisted = $this->createEvent(['calendar_id' => $calendar->id, 'status' => 'unlisted']);

        $this->assertStringContainsString('Opening night', $this->page($public)['body']);
        $this->assertNotNull($this->page($unlisted));
        $this->assertNull($this->handler()->event('fe-test-no-such-event'));

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

    // The page doesn't know its viewers: a going member and staff get the visitor's card, with nothing prefilled.
    public function test_logged_in_viewers_see_the_page_as_a_visitor()
    {
        $online = ['venue_type' => 'online'];
        $link = ['online_meeting_url' => 'https://meet.example.test/fe-room'];
        $rsvp = $this->createEvent($online, $link + ['entry_type' => 'rsvp']);
        $open = $this->createEvent($online, $link + ['entry_type' => 'open']);

        $memberId = $this->createUser('subscriber');
        $member = get_userdata($memberId);
        $this->createGuest($rsvp, ['user_id' => $memberId, 'email' => $member->user_email]);
        $this->createGuest($open, ['user_id' => $memberId, 'email' => $member->user_email]);

        foreach ([$memberId, $this->createAdmin()] as $userId) {
            $this->actAs($userId);
            $email = get_userdata($userId)->user_email;

            foreach ([$rsvp, $open] as $event) {
                $body = $this->page($event)['body'];

                $this->assertStringNotContainsString('fe-room', $body);
                $this->assertStringNotContainsString('fe-event-page__badge', $body);
                $this->assertStringNotContainsString($email, $body);
                $this->assertStringContainsString('name="website"', $body, 'The visitor form, honeypot and all');
            }

            $this->assertStringContainsString('Interested?', $this->page($rsvp)['body']);
        }
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

    // Under the event details page by slug alone: the calendar is a filter on the list, not part of an event's address.
    public function test_listed_events_link_to_their_page_by_slug_alone()
    {
        Settings::update(Settings::GENERAL, ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no']);
        $calendar = $this->createCalendar();
        $event = $this->createEvent(['calendar_id' => $calendar->id]);

        $url = EventPageService::url($event);

        $this->assertSame(user_trailingslashit(untrailingslashit(get_permalink($this->pages['event_page_id'])) . '/' . $event->post->post_name), $url);
        $this->assertStringContainsString('href="' . esc_url($url) . '"', $this->handler()->calendar($calendar->slug)['body']);
    }

    // With no event details page, nothing links to one and its form has nowhere to post.
    public function test_without_an_event_details_page_events_have_no_address()
    {
        Settings::update(Settings::GENERAL, ['event_page_id' => 0]);

        $this->assertSame('', EventPageService::url($this->createEvent()));
    }

    // The details page answers /<its path>/<event slug>/ by handing the slug to its own query var.
    public function test_the_rewrite_rule_maps_an_event_slug_to_the_details_page()
    {
        global $wp_rewrite;

        (new FrontEndHandler())->addRewriteRule();

        $path = get_page_uri($this->pages['event_page_id']);
        $rule = $wp_rewrite->extra_rules_top['^' . preg_quote($path, '#') . '/([^/]+)/?$'] ?? null;

        $this->assertSame('index.php?page_id=' . $this->pages['event_page_id'] . '&fe_event=$matches[1]', $rule);
    }
}
