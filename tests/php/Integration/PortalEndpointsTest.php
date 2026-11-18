<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\App;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Registration from the public event page: anyone, logged in or not, as a visitor by name and email
 * on RSVP and open events, and only on events that page opens.
 */
class PortalEndpointsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPublicPages();
        $this->actAs(0);
    }

    private function registerGuest(Event $event, array $params = [])
    {
        return $this->rest('POST', "portal/events/{$event->id}/register-guest", array_merge([
            'name'  => 'Walk-in Visitor',
            'email' => 'visitor@example.test',
            'nonce' => wp_create_nonce('fe_portal_register_' . $event->id),
        ], $params));
    }

    private function guestsOf(Event $event)
    {
        return Guest::query()->where('event_id', $event->id)->get();
    }

    private function onlineEvent($entryType)
    {
        return $this->createEvent(['venue_type' => 'online'], ['entry_type' => $entryType, 'online_meeting_url' => 'https://meet.example.test/fe-room']);
    }

    public function test_a_visitor_says_going_to_an_rsvp_event_by_name_and_email()
    {
        $event = $this->onlineEvent('rsvp');

        $response = $this->registerGuest($event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame('going', $guests[0]->status);
        $this->assertSame('wordpress_pages', $guests[0]->source);
        $this->assertEmpty($guests[0]->user_id);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guests[0]->id]));
        // The card comes back final: a fixed ✓ Going, the link, no menu to change it.
        $this->assertStringContainsString('fe-event-page__badge', $response['data']['html']);
        $this->assertStringContainsString('https://meet.example.test/fe-room', $response['data']['html']);
        $this->assertStringNotContainsString('data-fe-status', $response['data']['html']);
        // With the confirmation the page opens over it.
        $this->assertStringContainsString('data-fe-answer', $response['data']['html']);
        $this->assertStringContainsString(esc_html__("You're going", 'fluent-event'), $response['data']['html']);
    }

    public function test_a_visitor_registers_for_an_open_event()
    {
        $event = $this->onlineEvent('open');

        $response = $this->registerGuest($event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('going', $this->guestsOf($event)[0]->status);
        $this->assertStringContainsString('Registered', $response['data']['html']);
        $this->assertStringContainsString(esc_html__("You're registered", 'fluent-event'), $response['data']['html']);
    }

    public function test_the_visitor_form_is_guarded_by_the_events_nonce_honeypot_and_one_row_per_email()
    {
        $event = $this->createEvent();
        $other = $this->createEvent();

        $this->assertSame(403, $this->registerGuest($event, ['nonce' => wp_create_nonce('fe_portal_register_' . $other->id)])['status']);
        $this->assertSame(200, $this->registerGuest($event, ['website' => 'https://spam.example.test'])['status']);
        $this->assertCount(0, $this->guestsOf($event));

        $first = $this->registerGuest($event);
        $again = $this->registerGuest($event, ['name' => 'Someone else']);

        // Warned that the email is already registered, and nothing is saved.
        $this->assertSame(200, $again['status'], wp_json_encode($again['data']));
        $this->assertSame(__('This email is already registered for this event.', 'fluent-event'), $again['data']['message']);
        $this->assertNull($again['data']['status']);
        $this->assertStringContainsString('fe-register-success--warning', $again['data']['html']);
        $this->assertStringContainsString(esc_html__('This email is already registered', 'fluent-event'), $again['data']['html']);
        $this->assertStringNotContainsString(esc_html__("You're going", 'fluent-event'), $again['data']['html']);
        $this->assertStringNotContainsString('fe-register-success--warning', $first['data']['html']);
        $this->assertCount(1, $this->guestsOf($event));
        $this->assertNotSame('Someone else', $this->guestsOf($event)[0]->name);
    }

    public function test_a_visitor_who_said_not_going_comes_back_on_the_same_row()
    {
        $event = $this->onlineEvent('rsvp');
        $existing = $this->createGuest($event, ['name' => 'Name Before', 'email' => 'visitor@example.test', 'status' => 'not_going']);

        $response = $this->registerGuest($event, ['name' => 'Someone else']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('going', $response['data']['status']);
        $this->assertStringContainsString(esc_html__("You're going", 'fluent-event'), $response['data']['html']);
        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame((int) $existing->id, (int) $guests[0]->id);
        $this->assertSame('going', $guests[0]->status);
        $this->assertSame('Name Before', $guests[0]->name);
    }

    public function test_a_visitor_an_admin_cancelled_is_told_so()
    {
        $event = $this->onlineEvent('open');
        $this->createGuest($event, ['email' => 'visitor@example.test', 'status' => 'cancelled']);

        $response = $this->registerGuest($event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame(__('Your registration was cancelled by the host.', 'fluent-event'), $response['data']['message']);
        $this->assertNull($response['data']['status']);
        $this->assertStringContainsString('fe-register-success--warning', $response['data']['html']);
        $this->assertStringContainsString(esc_html__('Your registration was cancelled', 'fluent-event'), $response['data']['html']);
        $this->assertStringNotContainsString('meet.example.test', $response['data']['html']);
        $this->assertSame('cancelled', $this->guestsOf($event)[0]->status);
    }

    // An admin cancels the row while the visitor's comeback is in flight: it must not read as registered.
    public function test_a_comeback_overtaken_by_an_admin_cancel_asks_to_try_again()
    {
        $event = $this->onlineEvent('rsvp');
        $guest = $this->createGuest($event, ['email' => 'visitor@example.test', 'status' => 'not_going']);
        $raced = false;

        $this->addTestFilter('query', function ($query) use (&$raced, $guest) {
            if (!$raced && stripos($query, 'update') === 0 && strpos($query, 'fe_guests') !== false && strpos($query, "'not_going'") !== false) {
                $raced = true;
                $this->wpdb->update($this->table('fe_guests'), ['status' => 'cancelled'], ['id' => $guest->id]);
            }

            return $query;
        });

        $response = $this->registerGuest($event);

        $this->assertTrue($raced, 'The claim query never ran');
        $this->assertSame(409, $response['status'], wp_json_encode($response['data']));
        $this->assertArrayNotHasKey('html', $response['data']);
        $this->assertSame('cancelled', $this->guestsOf($event)[0]->status);
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guest->id]));
    }

    // A bad name fails alike for registered and new emails.
    public function test_an_invalid_name_does_not_tell_registered_emails_apart()
    {
        $event = $this->createEvent();
        $this->registerGuest($event);

        foreach (['', str_repeat('a', 256)] as $name) {
            $registered = $this->registerGuest($event, ['name' => $name]);
            $unknown = $this->registerGuest($event, ['name' => $name, 'email' => 'nobody@example.test']);

            $this->assertSame(422, $registered['status'], wp_json_encode($registered['data']));
            $this->assertSame($unknown['status'], $registered['status']);
        }

        $this->assertCount(1, $this->guestsOf($event));
    }

    public function test_only_events_the_page_opens_take_registrations()
    {
        $hidden = $this->createEvent(['calendar_id' => $this->createCalendar(['status' => 'private'])->id]);
        $private = $this->createEvent(['status' => 'private']);
        $none = $this->createEvent([], ['entry_type' => 'none']);
        $ended = $this->createEvent([
            'start_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days')),
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('-2 days +1 hour')),
        ]);

        $this->assertSame(404, $this->registerGuest($hidden)['status']);
        $this->assertSame(404, $this->registerGuest($private)['status']);
        $this->assertSame(422, $this->registerGuest($none)['status']);
        $this->assertSame(422, $this->registerGuest($ended)['status']);
    }

    // The page doesn't know its viewers: a logged-in user registers by the name and email they type, unlinked to their account.
    public function test_a_logged_in_user_registers_as_a_visitor()
    {
        $event = $this->onlineEvent('rsvp');
        $this->actAs($this->createUser('subscriber'));

        $response = $this->registerGuest($event, ['name' => 'Stage Name', 'email' => 'stage@example.test']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guest = $this->guestsOf($event)[0];
        $this->assertSame(['Stage Name', 'stage@example.test', 'going'], [$guest->name, $guest->email, $guest->status]);
        $this->assertEmpty($guest->user_id);
        $this->assertStringContainsString('fe-event-page__badge', $response['data']['html']);
        $this->assertStringNotContainsString('data-fe-status', $response['data']['html']);
    }

    // The framework's limiter lets cookie-authenticated requests through; a logged-in viewer is counted all the same.
    // 5 a minute, counted once although WordPress runs the check again for the Allow header.
    public function test_a_logged_in_viewer_is_held_to_the_visitor_rate_limit()
    {
        $event = $this->onlineEvent('rsvp');
        $this->actAs($this->createUser('subscriber'));
        $GLOBALS['wp_rest_auth_cookie'] = true;
        $statuses = [];

        try {
            for ($i = 1; $i <= 6; $i++) {
                $statuses[] = $this->registerGuest($event, ['email' => "flood{$i}@example.test"])['status'];
            }
        } finally {
            unset($GLOBALS['wp_rest_auth_cookie']);
        }

        $this->assertSame(array_merge(array_fill(0, 5, 200), [429]), $statuses);
        $this->assertCount(5, $this->guestsOf($event));
    }

    // One counter for the whole form: a new event ID is not a new allowance.
    public function test_the_visitor_rate_limit_covers_every_event_together()
    {
        $events = [$this->onlineEvent('rsvp'), $this->onlineEvent('rsvp')];
        $statuses = [];

        for ($i = 1; $i <= 6; $i++) {
            $statuses[] = $this->registerGuest($events[$i % 2], ['email' => "spread{$i}@example.test"])['status'];
        }

        $this->assertSame(array_merge(array_fill(0, 5, 200), [429]), $statuses);
    }

    // The Allow-header pass must not count again: it would read this request's cached count
    // and write it back over what parallel requests added meanwhile.
    public function test_the_allow_header_pass_does_not_lower_the_shared_count()
    {
        if (wp_using_ext_object_cache()) {
            $this->markTestSkipped('Needs transients in the options table.');
        }

        global $wpdb;
        $event = $this->onlineEvent('rsvp');
        $key = App::make('config')->get('app.slug') . '_rate_limit_' . md5('127.0.0.1|register_guest');

        // Parallel requests raise the count in the database while this one is still running.
        $parallelRequests = function () use ($wpdb, $key) {
            $wpdb->update(
                $wpdb->options,
                ['option_value' => serialize(array_fill(0, 4, time()))],
                ['option_name' => '_transient_' . $key]
            );
        };

        $response = $this->rest('POST', "portal/events/{$event->id}/register-guest", [
            'name'  => 'Walk-in Visitor',
            'email' => 'visitor@example.test',
            'nonce' => wp_create_nonce('fe_portal_register_' . $event->id),
        ], $parallelRequests);

        wp_cache_delete('_transient_' . $key, 'options');

        $this->assertSame(200, $response['status']);
        $this->assertCount(4, get_transient($key));
    }

    public function test_an_email_sent_as_a_list_is_refused_not_a_server_error()
    {
        $event = $this->onlineEvent('rsvp');

        $response = $this->registerGuest($event, ['email' => ['visitor@example.test']]);

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertCount(0, $this->guestsOf($event));
    }

    // An unlisted event opens only by its link, so walking ids without its page's nonce finds nothing.
    public function test_an_event_cannot_be_registered_for_by_id_alone()
    {
        $event = $this->createEvent(['status' => 'unlisted']);

        $response = $this->registerGuest($event, ['nonce' => '']);

        $this->assertSame(403, $response['status']);
        $this->assertArrayNotHasKey('html', $response['data']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    // The public page holds the same seat limit as the portal.
    public function test_a_visitor_is_refused_a_full_event()
    {
        $full = $this->createEvent([], ['entry_type' => 'rsvp', 'capacity' => 1]);
        $this->createGuest($full);

        $response = $this->registerGuest($full);

        $this->assertSame(422, $response['status']);
        $this->assertSame('This event is full.', $response['data']['message']);
        $this->assertCount(1, $this->guestsOf($full));
    }

    // Coming back from not going takes a seat like a new registration does.
    public function test_a_visitor_who_said_not_going_cannot_take_a_seat_once_the_event_is_full()
    {
        $full = $this->createEvent([], ['entry_type' => 'rsvp', 'capacity' => 1]);
        $this->createGuest($full);
        $comeback = $this->createGuest($full, ['email' => 'visitor@example.test', 'status' => 'not_going']);

        $response = $this->registerGuest($full);

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('This event is full.', $response['data']['message']);
        $this->assertSame('not_going', Guest::query()->find($comeback->id)->status);
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $comeback->id]));
    }
}
