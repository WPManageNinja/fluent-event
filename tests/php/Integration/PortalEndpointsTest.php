<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\Settings;
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
        $this->assertSame('portal', $guests[0]->source);
        $this->assertEmpty($guests[0]->user_id);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guests[0]->id]));
        // The card comes back final: a fixed ✓ Going, the link, no menu to change it.
        $this->assertStringContainsString('fe-event-page__badge', $response['data']['html']);
        $this->assertStringContainsString('https://meet.example.test/fe-room', $response['data']['html']);
        $this->assertStringNotContainsString('data-fe-status', $response['data']['html']);
        // With the confirmation the page opens over it.
        $this->assertStringContainsString('data-fe-registered', $response['data']['html']);
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

    // An unlisted event opens only by its link, so walking ids without its page's nonce finds nothing.
    public function test_an_event_cannot_be_registered_for_by_id_alone()
    {
        $event = $this->createEvent(['status' => 'unlisted']);

        $response = $this->registerGuest($event, ['nonce' => '']);

        $this->assertSame(403, $response['status']);
        $this->assertArrayNotHasKey('html', $response['data']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    // With landing pages off there is no event page, so its endpoint refuses everyone.
    public function test_the_endpoint_does_not_answer_when_landing_pages_are_off()
    {
        Settings::update(Settings::GENERAL, ['enable_landing_pages' => 'no']);
        $event = $this->createEvent();

        $this->assertNotSame(200, $this->registerGuest($event)['status']);

        $this->actAs($this->createUser('subscriber'));
        $this->assertNotSame(200, $this->registerGuest($event)['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }
}
