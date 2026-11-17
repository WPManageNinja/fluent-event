<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\CPT\EventPost;
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
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new \WP_Query();

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
                $_GET = [FrontEndHandler::CALENDAR_VAR => $slug];

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

    // The host may count down to the start instead of "Starts in …"; portal.js ticks it from the start's timestamp.
    public function test_an_upcoming_event_counts_down_to_its_start_when_the_host_chose_it()
    {
        $countdown = $this->createEvent([], ['start_display' => 'countdown']);
        $body = $this->page($countdown)['body'];

        $this->assertStringContainsString('data-fe-countdown="' . strtotime($countdown->start_at . ' UTC') . '"', $body);
        // The end too, so a tab that slept through the event lands on Ended rather than live.
        $this->assertStringContainsString('data-fe-countdown-end="' . strtotime($countdown->end_at . ' UTC') . '"', $body);
        $this->assertStringNotContainsString('Starts in', $body);
        $this->assertStringContainsString('Starts in', $this->page($this->createEvent())['body']);
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

    // Its own WordPress address, by slug alone: the calendar is a filter on the list, not part of an event's address.
    public function test_listed_events_link_to_their_own_address()
    {
        Settings::update(Settings::GENERAL, ['default_events_view' => 'timeline', 'disable_event_view_switch' => 'no']);
        $calendar = $this->createCalendar();
        $event = $this->createEvent(['calendar_id' => $calendar->id]);

        $url = EventPageService::url($event);

        $this->assertSame(get_permalink($event->post_id), $url);
        $this->assertStringContainsString('/event/' . $event->post->post_name, $url);
        $this->assertStringContainsString('href="' . esc_url($url) . '"', $this->handler()->calendar($calendar->slug)['body']);
    }

    public function test_an_events_address_follows_the_event_slug_setting()
    {
        $event = $this->createEvent();
        Settings::update(Settings::GENERAL, ['event_slug' => 'gatherings']);
        (new EventPost())->registerPostType();

        try {
            $this->assertStringContainsString('/gatherings/' . $event->post->post_name, get_permalink($event->post_id));
        } finally {
            Settings::update(Settings::GENERAL, ['event_slug' => 'event']);
            (new EventPost())->registerPostType();
        }
    }

    // WordPress finds any event post by its slug; only one the public may see opens, the rest answer like a missing page.
    public function test_only_an_event_the_public_may_see_opens_at_its_address()
    {
        $calendar = $this->createCalendar();
        $public = $this->createEvent(['calendar_id' => $calendar->id]);
        $unlisted = $this->createEvent(['calendar_id' => $calendar->id, 'status' => 'unlisted']);

        $this->assertFalse($this->open($public)['404']);
        $this->assertFalse($this->open($unlisted)['404']);
        $this->assertTrue($this->open($unlisted)['noindex'], 'An unlisted event stays out of search engines.');
        $this->assertFalse($this->open($public)['noindex']);

        $this->assertTrue($this->open($this->createEvent(['calendar_id' => $calendar->id, 'status' => 'private']))['404']);
        $this->assertTrue($this->open($this->createEvent(['calendar_id' => $this->createCalendar(['status' => 'private'])->id]))['404']);
    }

    // With plain permalinks the link is ?fe_event=<slug>, which WordPress reads as the event's name, so the unlisted
    // event still opens by its own link; an ID-based link (?p=) would be refused like any lookup by ID.
    public function test_an_unlisted_event_opens_by_its_own_link_with_plain_permalinks()
    {
        $unlisted = $this->createEvent(['calendar_id' => $this->createCalendar()->id, 'status' => 'unlisted']);
        $requestUri = $_SERVER['REQUEST_URI'];
        add_filter('pre_option_permalink_structure', '__return_empty_string');
        $GLOBALS['wp_rewrite']->init();

        try {
            $query = (string) parse_url(EventPageService::url($unlisted), PHP_URL_QUERY);
            parse_str($query, $_GET);
            $_SERVER['REQUEST_URI'] = '/?' . $query;
            $GLOBALS['wp']->query_vars = [];
            $GLOBALS['wp']->parse_request();
            $GLOBALS['wp']->query_posts();

            $this->assertSame('fe_event=' . $unlisted->post->post_name, $query);
            $this->assertSame([$unlisted->post_id], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        } finally {
            remove_filter('pre_option_permalink_structure', '__return_empty_string');
            $GLOBALS['wp_rewrite']->init();
            $_SERVER['REQUEST_URI'] = $requestUri;
        }
    }

    // WordPress's own listings (the archive, feeds, sitemaps) carry only what the events list shows.
    public function test_wordpress_listings_hold_only_public_events_in_public_calendars()
    {
        $calendar = $this->createCalendar();
        $public = $this->createEvent(['calendar_id' => $calendar->id]);
        $unlisted = $this->createEvent(['calendar_id' => $calendar->id, 'status' => 'unlisted']);
        $hidden = $this->createEvent(['calendar_id' => $this->createCalendar(['status' => 'private'])->id]);

        $ids = (new \WP_Query(['post_type' => EventPost::POST_TYPE, 'fields' => 'ids', 'posts_per_page' => -1]))->posts;

        $this->assertContains($public->post_id, $ids);
        $this->assertNotContains($unlisted->post_id, $ids);
        $this->assertNotContains($hidden->post_id, $ids);
    }

    // By ID (?p=, its redirect, oEmbed) an event answers only when listed: walking IDs must not hand out unlisted links.
    public function test_an_unlisted_or_hidden_event_does_not_answer_by_its_id()
    {
        $calendar = $this->createCalendar();
        $public = $this->createEvent(['calendar_id' => $calendar->id]);
        $unlisted = $this->createEvent(['calendar_id' => $calendar->id, 'status' => 'unlisted']);
        $hidden = $this->createEvent(['calendar_id' => $this->createCalendar(['status' => 'private'])->id]);
        $handler = new FrontEndHandler();

        $byId = function ($event) {
            return (new \WP_Query(['p' => $event->post_id, 'post_type' => EventPost::POST_TYPE]))->posts;
        };
        $byLink = function ($event) {
            return (new \WP_Query(['name' => $event->post->post_name, 'post_type' => EventPost::POST_TYPE]))->posts;
        };

        $this->assertCount(1, $byId($public));
        $this->assertCount(0, $byId($unlisted));
        $this->assertCount(0, $byId($hidden));
        $this->assertCount(1, $byLink($unlisted), 'An unlisted event still opens by its own link.');
        $this->assertCount(0, $byLink($hidden));

        $this->assertSame($public->post_id, $handler->hideFromEmbeds($public->post_id));
        $this->assertSame(0, $handler->hideFromEmbeds($unlisted->post_id));
        $this->assertSame(0, $handler->hideFromEmbeds($hidden->post_id));

        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new \WP_Query(['p' => $unlisted->post_id, 'post_type' => EventPost::POST_TYPE]);
        $this->assertFalse($handler->hideFromRedirects('https://example.test/event/' . $unlisted->post->post_name . '/'));
    }

    public function test_the_events_archive_gives_way_to_the_all_events_page()
    {
        $this->addTestFilter('wp_redirect', function ($location) {
            throw new \RuntimeException($location);
        });
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new \WP_Query(['post_type' => EventPost::POST_TYPE]);

        try {
            (new FrontEndHandler())->resolveEventRequest();
            $this->fail('The archive did not redirect.');
        } catch (\RuntimeException $redirect) {
            $this->assertSame(get_permalink($this->pages['events_page_id']), $redirect->getMessage());
        }
    }

    // An All Events Page sharing the event slug answers at the archive's own address: no redirect, or it loops.
    public function test_the_archive_does_not_redirect_to_its_own_address()
    {
        $listUrl = get_permalink($this->pages['events_page_id']);
        $this->addTestFilter('post_type_archive_link', function () use ($listUrl) {
            return $listUrl;
        });
        $this->addTestFilter('wp_redirect', function ($location) {
            throw new \RuntimeException($location);
        });
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new \WP_Query(['post_type' => EventPost::POST_TYPE]);

        (new FrontEndHandler())->resolveEventRequest();

        $this->assertTrue(is_post_type_archive(EventPost::POST_TYPE), 'The archive renders the events list itself.');
    }

    // Core's admin pickers (the link dialog, Media → Attach) list every public post type to anyone who can edit a post.
    public function test_admin_pickers_leave_out_hidden_events()
    {
        $token = 'fepicker' . strtolower(wp_generate_password(8, false, false));
        $calendar = $this->createCalendar();
        $public = $this->createEvent(['calendar_id' => $calendar->id, 'title' => "$token public"]);
        $unlisted = $this->createEvent(['calendar_id' => $calendar->id, 'title' => "$token unlisted", 'status' => 'unlisted']);
        $draft = $this->createEvent(['calendar_id' => $calendar->id, 'title' => "$token draft", 'status' => 'draft']);
        $inPrivate = $this->createEvent(['calendar_id' => $this->createCalendar(['status' => 'private'])->id, 'title' => "$token in private"]);

        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        require_once ABSPATH . WPINC . '/class-wp-editor.php';
        $GLOBALS['current_screen'] = \WP_Screen::get('post');

        // The two core queries, as wp-link-ajax and find_posts build them.
        $linked = function () use ($token) {
            return array_map('intval', wp_list_pluck(\_WP_Editors::wp_link_query(['s' => $token]) ?: [], 'ID'));
        };
        $attachable = function () use ($token) {
            $types = get_post_types(['public' => true]);
            unset($types['attachment']);

            return (new \WP_Query(['post_type' => array_keys($types), 'post_status' => 'any', 's' => $token, 'posts_per_page' => 50, 'fields' => 'ids']))->posts;
        };

        try {
            $this->actAs($this->createUser('contributor'));

            foreach ([$linked(), $attachable()] as $ids) {
                $this->assertContains($public->post_id, $ids, 'A public event stays linkable.');
                $this->assertNotContains($unlisted->post_id, $ids, 'An unlisted event opens by its link only.');
                $this->assertNotContains($draft->post_id, $ids);
                $this->assertNotContains($inPrivate->post_id, $ids);
            }

            // As in the block's own picker, an admin may link an unlisted event.
            $this->actAs($this->createAdmin());
            $this->assertContains($unlisted->post_id, $linked());
            $this->assertNotContains($inPrivate->post_id, $linked());
        } finally {
            unset($GLOBALS['current_screen']);
        }
    }

    // A visit to the event's address, in WordPress's order: its query by slug, its own 404 for no post, then our guard.
    private function open($event)
    {
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new \WP_Query(['post_type' => EventPost::POST_TYPE, 'name' => $event->post->post_name]);
        $GLOBALS['wp']->handle_404();

        $handler = new class extends FrontEndHandler {
            public function resolve()
            {
                static::$pageEvent = null;
                $this->resolveEventRequest();

                return ['404' => is_404(), 'noindex' => !empty($this->robots([])['noindex'])];
            }
        };

        return $handler->resolve();
    }

    // A full event shows no Register button, only why.
    public function test_a_full_event_shows_its_capacity_in_place_of_register()
    {
        $capped = ['entry_type' => 'open', 'capacity' => 2, 'show_guest_count' => 'yes'];

        $open = $this->createEvent([], $capped);
        $this->createGuest($open);
        $body = $this->page($open)['body'];

        $this->assertStringContainsString('1 spot left', $body);
        $this->assertStringContainsString('popovertarget="fe-register-' . $open->id . '"', $body);

        $full = $this->createEvent([], $capped);
        $this->createGuest($full);
        $this->createGuest($full);
        $body = $this->page($full)['body'];

        $this->assertStringContainsString('This event is full and no longer taking registrations.', $body);
        $this->assertStringNotContainsString('popovertarget="fe-register-' . $full->id . '"', $body);
    }
}
