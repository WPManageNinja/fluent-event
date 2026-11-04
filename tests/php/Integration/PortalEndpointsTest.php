<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Registration from the public event page: a visitor by name and email on RSVP and open events,
 * a member like the community page, and only on events that page opens.
 */
class PortalEndpointsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Settings::update(Settings::GENERAL, ['enable_landing_pages' => 'yes']);
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

    private function register(Event $event, array $params)
    {
        return $this->rest('POST', "portal/events/{$event->id}/register", array_merge([
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
        $this->assertSame('website', $guests[0]->source);
        $this->assertEmpty($guests[0]->user_id);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guests[0]->id]));
        // The card comes back final: a fixed ✓ Going, the link, no menu to change it.
        $this->assertStringContainsString('fe-event-page__badge', $response['data']['html']);
        $this->assertStringContainsString('https://meet.example.test/fe-room', $response['data']['html']);
        $this->assertStringNotContainsString('data-fe-status', $response['data']['html']);
    }

    public function test_a_visitor_registers_for_an_open_event()
    {
        $event = $this->onlineEvent('open');

        $response = $this->registerGuest($event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('going', $this->guestsOf($event)[0]->status);
        $this->assertStringContainsString('Registered', $response['data']['html']);
    }

    public function test_the_visitor_form_is_guarded_by_the_events_nonce_honeypot_and_one_row_per_email()
    {
        $event = $this->createEvent();
        $other = $this->createEvent();

        $this->assertSame(403, $this->registerGuest($event, ['nonce' => wp_create_nonce('fe_portal_register_' . $other->id)])['status']);
        $this->assertSame(200, $this->registerGuest($event, ['website' => 'https://spam.example.test'])['status']);
        $this->assertCount(0, $this->guestsOf($event));

        $this->registerGuest($event);
        $this->assertSame(422, $this->registerGuest($event, ['name' => 'Someone else'])['status']);
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

    public function test_a_member_switches_their_rsvp_like_on_the_community_page()
    {
        $event = $this->onlineEvent('rsvp');
        $this->actAs($this->createUser('subscriber'));

        $going = $this->register($event, ['status' => 'going']);
        $this->assertSame(200, $going['status'], wp_json_encode($going['data']));
        $this->assertStringContainsString('https://meet.example.test/fe-room', $going['data']['html']);
        $this->assertStringContainsString('data-fe-status="not_going"', $going['data']['html'], 'A member keeps the menu to change their mind');

        $notGoing = $this->register($event, ['status' => 'not_going']);
        $guest = $this->guestsOf($event)[0];
        $this->assertSame('not_going', $guest->status);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $guest->id]));
        $this->assertStringNotContainsString('https://meet.example.test/fe-room', $notGoing['data']['html']);
    }

    public function test_a_member_registers_for_an_open_event_under_the_name_they_give()
    {
        $event = $this->createEvent([], ['entry_type' => 'open']);
        $this->actAs($this->createUser('subscriber'));

        $this->assertSame(422, $this->register($event, ['status' => 'not_going'])['status']);
        $this->assertSame(200, $this->register($event, ['status' => 'going', 'name' => 'Stage Name'])['status']);
        $this->assertSame('Stage Name', $this->guestsOf($event)[0]->name);
    }

    public function test_members_and_visitors_each_have_their_own_endpoint()
    {
        $event = $this->createEvent();

        $this->assertNotSame(200, $this->register($event, ['status' => 'going'])['status']);

        $this->actAs($this->createUser('subscriber'));
        $this->assertNotSame(200, $this->registerGuest($event)['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    // An unlisted event opens only by its link, so walking ids without its page's nonce finds nothing.
    public function test_a_member_cannot_register_by_event_id_alone()
    {
        $event = $this->createEvent(['status' => 'unlisted']);
        $this->actAs($this->createUser('subscriber'));

        $response = $this->rest('POST', "portal/events/{$event->id}/register", ['status' => 'going']);

        $this->assertSame(403, $response['status']);
        $this->assertArrayNotHasKey('html', $response['data']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    // With landing pages off there is no event page, so its endpoints refuse members and visitors alike.
    public function test_neither_endpoint_answers_when_landing_pages_are_off()
    {
        Settings::update(Settings::GENERAL, ['enable_landing_pages' => 'no']);
        $event = $this->createEvent();

        $this->assertNotSame(200, $this->registerGuest($event)['status']);

        $this->actAs($this->createUser('subscriber'));
        $this->assertNotSame(200, $this->register($event, ['status' => 'going'])['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }
}
