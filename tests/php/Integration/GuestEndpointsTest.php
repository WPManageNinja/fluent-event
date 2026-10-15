<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Guest;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Guest endpoints. A guest row IS a ticket: an order is rows sharing group_id,
 * and the is_buyer=1 row carries the order (CLAUDE.md).
 */
class GuestEndpointsTest extends IntegrationTestCase
{
    /** @var \FluentEvent\App\Models\Event */
    private $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actAs($this->createAdmin());
        $this->event = $this->createEvent();
    }

    private function addGuest(array $params, $event = null)
    {
        $event = $event ?: $this->event;

        return $this->rest('POST', "events/{$event->id}/guests", $params);
    }

    public function test_adding_a_guest_creates_a_one_ticket_order()
    {
        $response = $this->addGuest(['name' => 'Rafi', 'email' => 'rafi@example.test']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $guest = Guest::query()->find($response['data']['guest']['id']);
        $this->assertSame(1, $guest->is_buyer);
        $this->assertSame(32, strlen($guest->group_id));
        $this->assertSame((int) $this->event->calendar_id, $guest->calendar_id);
        $this->assertSame('going', $guest->status);
    }

    public function test_the_same_email_cannot_join_an_event_twice()
    {
        $this->addGuest(['name' => 'Rafi', 'email' => 'rafi@example.test']);

        $this->assertSame(422, $this->addGuest(['name' => 'Rafi again', 'email' => 'rafi@example.test'])['status']);
        $this->assertSame(1, Guest::query()->where('event_id', $this->event->id)->count());
    }

    public function test_the_same_email_may_join_a_different_event()
    {
        $this->addGuest(['name' => 'Rafi', 'email' => 'rafi@example.test']);

        $other = $this->createEvent(['calendar_id' => $this->event->calendar_id]);

        $this->assertSame(200, $this->addGuest(['name' => 'Rafi', 'email' => 'rafi@example.test'], $other)['status']);
    }

    public function test_checking_in_stamps_the_time_and_going_back_clears_it()
    {
        $id = $this->addGuest(['name' => 'Rafi', 'email' => 'rafi@example.test', 'status' => 'checked_in'])['data']['guest']['id'];

        $this->assertNotNull(Guest::query()->find($id)->checked_in_at);

        $response = $this->rest('PATCH', "events/{$this->event->id}/guests/{$id}", ['name' => 'Rafi', 'email' => 'rafi@example.test', 'status' => 'going']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Guest::query()->find($id)->checked_in_at);
    }

    public function test_a_confirmation_is_queued_only_when_asked_for()
    {
        $silent = $this->addGuest(['name' => 'Quiet', 'email' => 'quiet@example.test'])['data']['guest']['id'];
        $notified = $this->addGuest(['name' => 'Told', 'email' => 'told@example.test', 'send_email' => 1])['data']['guest']['id'];

        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $silent]));
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $notified]));
    }

    public function test_a_guest_cannot_be_edited_through_another_events_url()
    {
        $guest = $this->createGuest($this->event, ['name' => 'Original']);
        $other = $this->createEvent(['calendar_id' => $this->event->calendar_id]);

        $response = $this->rest('PATCH', "events/{$other->id}/guests/{$guest->id}", ['name' => 'Hijacked', 'email' => $guest->email]);

        $this->assertSame(404, $response['status']);
        $this->assertSame('Original', Guest::query()->find($guest->id)->name);
    }

    public function test_guest_counts_include_every_status_even_when_empty()
    {
        $this->createGuest($this->event);

        $response = $this->rest('GET', "events/{$this->event->id}/guests");

        $this->assertSame(200, $response['status']);
        $this->assertSame(
            ['all' => 1, 'going' => 1, 'not_going' => 0, 'checked_in' => 0, 'cancelled' => 0],
            $response['data']['counts']
        );
    }

    public function test_guest_search_treats_wildcards_literally()
    {
        $this->createGuest($this->event, ['name' => 'Ann Lee']);
        $this->createGuest($this->event, ['name' => 'Bob Roy']);

        $this->assertSame(1, $this->rest('GET', "events/{$this->event->id}/guests", ['search' => 'ann'])['data']['guests']['total']);
        $this->assertSame(0, $this->rest('GET', "events/{$this->event->id}/guests", ['search' => '%'])['data']['guests']['total']);
    }

    public function test_removing_a_going_guest_with_email_sends_a_cancellation_without_the_meeting_link()
    {
        $event = $this->createEvent(['venue_type' => 'online'], ['online_meeting_url' => 'https://meet.example.test/secret']);
        $guest = $this->createGuest($event);

        $response = $this->rest('DELETE', "events/{$event->id}/guests/{$guest->id}", ['send_email' => 1]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Guest::query()->find($guest->id));
        $this->assertMailSentTo($guest->email);
        $this->assertStringNotContainsString('meet.example.test', $this->sentMail[0]['message']);
    }
}
