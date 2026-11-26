<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\App;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Registration from the public event page, only on events that page opens: a visitor by name and email on
 * RSVP and open events, a logged-in member by account, as on the portal.
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
            'nonce' => wp_create_nonce('fevent_portal_register_' . $event->id),
        ], $params));
    }

    private function answer(Event $event, $status, array $params = [])
    {
        return $this->rest('POST', "portal/events/{$event->id}/register", array_merge([
            'status' => $status,
            'nonce'  => wp_create_nonce('fevent_portal_register_' . $event->id),
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

        $this->assertSame(403, $this->registerGuest($event, ['nonce' => wp_create_nonce('fevent_portal_register_' . $other->id)])['status']);
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

    // A row linked to an account changes only from that account: typing its email can't bring it back, even signed in
    // to another account.
    public function test_a_visitor_cannot_bring_back_a_members_not_going_row()
    {
        $event = $this->onlineEvent('rsvp');
        $member = $this->createUser('subscriber');
        $guest = $this->createGuest($event, ['email' => 'visitor@example.test', 'user_id' => $member, 'status' => 'not_going']);

        foreach ([0, $this->createUser('subscriber')] as $viewer) {
            $this->actAs($viewer);
            $response = $this->registerGuest($event);

            $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
            $this->assertSame(__('This email is already registered for this event.', 'fluent-event'), $response['data']['message']);
            $this->assertNull($response['data']['status']);
        }

        $this->assertSame('not_going', Guest::query()->find($guest->id)->status);
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guest->id]));
    }

    // An admin cancels the row while the visitor's comeback is in flight: it must not read as registered.
    public function test_a_comeback_overtaken_by_an_admin_cancel_asks_to_try_again()
    {
        $event = $this->onlineEvent('rsvp');
        $guest = $this->createGuest($event, ['email' => 'visitor@example.test', 'status' => 'not_going']);
        $raced = false;

        $this->addTestFilter('query', function ($query) use (&$raced, $guest) {
            if (!$raced && stripos($query, 'update') === 0 && strpos($query, 'fevent_guests') !== false && strpos($query, "'not_going'") !== false) {
                $raced = true;
                $this->wpdb->update($this->table('fevent_guests'), ['status' => 'cancelled'], ['id' => $guest->id]);
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

    // Another email than the account's registers that email as a visitor, unlinked; the member's own card stays open.
    public function test_a_member_registering_another_email_registers_a_visitor()
    {
        $event = $this->onlineEvent('open');
        $this->actAs($this->createUser('subscriber'));

        $response = $this->registerGuest($event, ['name' => 'Stage Name', 'email' => 'stage@example.test']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guest = $this->guestsOf($event)[0];
        $this->assertSame(['Stage Name', 'stage@example.test', 'going'], [$guest->name, $guest->email, $guest->status]);
        $this->assertEmpty($guest->user_id);
        $this->assertStringContainsString('data-fe-register', $response['data']['html']);
    }

    // Keeping the prefilled account email registers the member by account, under the name they typed.
    public function test_a_member_keeping_their_email_registers_by_account()
    {
        $event = $this->onlineEvent('open');
        $memberId = $this->createUser('subscriber');
        $this->actAs($memberId);

        $response = $this->registerGuest($event, ['name' => 'Stage Name', 'email' => strtoupper(get_userdata($memberId)->user_email)]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guest = $this->guestsOf($event)[0];
        $this->assertSame([$memberId, 'Stage Name', 'going'], [(int) $guest->user_id, $guest->name, $guest->status]);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guest->id]));
        $this->assertStringContainsString('data-fe-status="not_going"', $response['data']['html']);
        $this->assertStringContainsString('https://meet.example.test/fe-room', $response['data']['html']);
        $this->assertStringContainsString(esc_html__("You're registered", 'fluent-event'), $response['data']['html']);
    }

    // RSVP Going and Not going straight from the menu, by account, on one row.
    public function test_a_member_answers_an_rsvp_by_account()
    {
        $event = $this->onlineEvent('rsvp');
        $memberId = $this->createUser('subscriber');
        $member = get_userdata($memberId);
        $this->actAs($memberId);

        $going = $this->answer($event, 'going');

        $this->assertSame(200, $going['status'], wp_json_encode($going['data']));
        $guest = $this->guestsOf($event)[0];
        $this->assertSame([$memberId, $member->display_name, $member->user_email, 'going', 'wordpress_pages'], [(int) $guest->user_id, $guest->name, $guest->email, $guest->status, $guest->source]);
        $this->assertStringContainsString('https://meet.example.test/fe-room', $going['data']['html']);
        $this->assertStringContainsString(esc_html__("You're going", 'fluent-event'), $going['data']['html']);

        $notGoing = $this->answer($event, 'not_going');

        $this->assertSame(200, $notGoing['status'], wp_json_encode($notGoing['data']));
        $this->assertCount(1, $this->guestsOf($event));
        $this->assertSame('not_going', $this->guestsOf($event)[0]->status);
        $this->assertStringNotContainsString('meet.example.test', $notGoing['data']['html']);
        $this->assertStringNotContainsString('data-fe-answer', $notGoing['data']['html']);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $guest->id]));
    }

    // An open event takes Not going only from a member holding a seat, and Going only through its form.
    public function test_a_member_gives_up_an_open_registration()
    {
        $event = $this->onlineEvent('open');
        $memberId = $this->createUser('subscriber');
        $this->actAs($memberId);

        $this->assertSame(422, $this->answer($event, 'not_going')['status']);
        $this->assertSame(422, $this->answer($event, 'going')['status']);
        $this->assertCount(0, $this->guestsOf($event));

        $guest = $this->createGuest($event, ['user_id' => $memberId, 'email' => get_userdata($memberId)->user_email]);
        $response = $this->answer($event, 'not_going');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('not_going', Guest::query()->find($guest->id)->status);
        $this->assertStringContainsString('data-fe-register', $response['data']['html'], 'They can register again');
    }

    public function test_the_member_answer_needs_a_login_and_the_events_nonce()
    {
        $event = $this->createEvent();

        $this->assertContains($this->answer($event, 'going')['status'], [401, 403]);

        $this->actAs($this->createUser('subscriber'));
        $this->assertSame(403, $this->answer($event, 'going', ['nonce' => 'wrong'])['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    // An admin's cancel stands against the member's own answer too.
    public function test_a_member_an_admin_cancelled_is_told_so()
    {
        $event = $this->createEvent();
        $memberId = $this->createUser('subscriber');
        $this->createGuest($event, ['user_id' => $memberId, 'status' => 'cancelled']);
        $this->actAs($memberId);

        $response = $this->answer($event, 'going');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull($response['data']['status']);
        $this->assertStringContainsString(esc_html__('Your registration was cancelled', 'fluent-event'), $response['data']['html']);
        $this->assertSame('cancelled', $this->guestsOf($event)[0]->status);
    }

    public function test_a_checked_in_member_saying_not_going_is_told_so_and_stays_checked_in()
    {
        $event = $this->createEvent();
        $memberId = $this->createUser('subscriber');
        $this->createGuest($event, ['user_id' => $memberId, 'status' => 'checked_in']);
        $this->actAs($memberId);

        $response = $this->answer($event, 'not_going');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull($response['data']['status']);
        $this->assertStringContainsString(esc_html__('You are already checked in', 'fluent-event'), $response['data']['html']);
        $this->assertSame('checked_in', $this->guestsOf($event)[0]->status);
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
            'nonce' => wp_create_nonce('fevent_portal_register_' . $event->id),
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

    public function test_a_visitor_joins_the_waitlist_of_a_full_event_with_one()
    {
        $full = $this->createEvent([], ['entry_type' => 'rsvp', 'capacity' => 1, 'waitlist' => 'yes']);
        $this->createGuest($full);

        $response = $this->registerGuest($full);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('waitlisted', $response['data']['status']);
        $this->assertStringContainsString("You&#039;re on the waitlist", $response['data']['html']);
        $this->assertStringNotContainsString('popovertarget="fe-register-' . $full->id . '"', $response['data']['html']);
        $waitlisted = Guest::query()->where('event_id', $full->id)->where('email', 'visitor@example.test')->first();
        $this->assertSame('waitlisted', $waitlisted->status);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_waitlist', [(int) $waitlisted->id]));
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $waitlisted->id]));
    }

    public function test_a_waitlisted_email_registering_again_is_told_it_is_on_the_waitlist()
    {
        $full = $this->createEvent([], ['entry_type' => 'open', 'capacity' => 1, 'waitlist' => 'yes']);
        $this->createGuest($full);
        $this->createGuest($full, ['email' => 'visitor@example.test', 'status' => 'waitlisted']);

        $response = $this->registerGuest($full);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull($response['data']['status']);
        $this->assertSame("You're already on the waitlist for this event.", $response['data']['message']);
        $this->assertStringContainsString("You&#039;re already on the waitlist", $response['data']['html']);
        $this->assertCount(2, $this->guestsOf($full));
    }

    // A member who joined the waitlist from the portal is told so here too, not "already registered".
    public function test_a_members_waitlisted_email_on_the_public_form_is_told_it_is_on_the_waitlist()
    {
        $full = $this->createEvent([], ['entry_type' => 'open', 'capacity' => 1, 'waitlist' => 'yes']);
        $this->createGuest($full);
        $this->createGuest($full, ['email' => 'visitor@example.test', 'status' => 'waitlisted', 'user_id' => 1]);

        $response = $this->registerGuest($full);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame("You're already on the waitlist for this event.", $response['data']['message']);
    }

    // A logged-in member on a full event with a waitlist joins it by account, sees it on the card, and can leave it:
    // Leave waitlist on an open event, Not going on an RSVP.
    public function test_a_member_joins_and_leaves_the_waitlist_by_account()
    {
        foreach (['rsvp', 'open'] as $entryType) {
            $full = $this->createEvent([], ['entry_type' => $entryType, 'capacity' => 1, 'waitlist' => 'yes']);
            $this->createGuest($full);
            $memberId = $this->createUser('subscriber');
            $this->actAs($memberId);

            $response = $entryType === 'rsvp'
                ? $this->answer($full, 'going')
                : $this->registerGuest($full, ['email' => get_userdata($memberId)->user_email]);

            $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
            $this->assertSame('waitlisted', $response['data']['status']);
            // An open event's place has its own Leave waitlist menu; an RSVP's is Waitlisted beside Not going.
            $this->assertStringContainsString($entryType === 'open' ? 'Leave waitlist' : 'data-fe-status="not_going"', $response['data']['html']);
            $this->assertStringNotContainsString('data-fe-register', $response['data']['html']);

            $again = $entryType === 'rsvp'
                ? $this->answer($full, 'going')
                : $this->registerGuest($full, ['email' => get_userdata($memberId)->user_email]);
            $this->assertSame("You're already on the waitlist for this event.", $again['data']['message']);

            $left = $this->answer($full, 'not_going');

            $this->assertSame(200, $left['status'], wp_json_encode($left['data']));
            $this->assertSame('You left the waitlist.', $left['data']['message']);
            $this->assertSame('not_going', Guest::query()->where('event_id', $full->id)->where('user_id', $memberId)->value('status'));
        }
    }
}
