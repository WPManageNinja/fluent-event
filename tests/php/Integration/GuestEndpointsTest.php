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

    public function test_a_guest_added_by_an_admin_is_manual_whatever_source_is_sent()
    {
        $response = $this->addGuest(['name' => 'Rafi', 'email' => 'rafi@example.test', 'source' => 'stripe-paid']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('manual', Guest::query()->find($response['data']['guest']['id'])->source);
    }

    // Members can't register for a no-entry event, but an admin still manages its guests, emails included.
    public function test_an_admin_can_add_a_guest_to_a_no_entry_event()
    {
        $event = $this->createEvent([], ['entry_type' => 'none']);

        $response = $this->addGuest(['name' => 'Walk-in', 'email' => 'walkin@example.test', 'send_email' => 1], $event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $response['data']['guest']['id']]));
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

    // The guest list's quick status change sends only the status.
    public function test_changing_only_the_status_keeps_the_rest_of_the_guest()
    {
        $userId = $this->createUser('subscriber');
        $guest = $this->createGuest($this->event, ['user_id' => $userId]);

        $response = $this->rest('PATCH', "events/{$this->event->id}/guests/{$guest->id}", ['status' => 'checked_in']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $stored = Guest::query()->find($guest->id);
        $this->assertSame('checked_in', $stored->status);
        $this->assertNotNull($stored->checked_in_at);
        $this->assertSame($userId, (int) $stored->user_id);
        $this->assertSame($guest->name, $stored->name);
        $this->assertSame($guest->email, $stored->email);
    }

    public function test_a_guest_whose_wordpress_user_was_deleted_can_still_change_status()
    {
        require_once ABSPATH . 'wp-admin/includes/user.php';

        $userId = $this->createUser('subscriber');
        $guest = $this->createGuest($this->event, ['user_id' => $userId]);
        wp_delete_user($userId);

        $response = $this->rest('PATCH', "events/{$this->event->id}/guests/{$guest->id}", ['status' => 'checked_in']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('checked_in', Guest::query()->find($guest->id)->status);
    }

    public function test_an_update_without_a_status_keeps_the_current_one()
    {
        $guest = $this->createGuest($this->event, ['status' => 'checked_in', 'checked_in_at' => current_time('mysql')]);

        $response = $this->rest('PATCH', "events/{$this->event->id}/guests/{$guest->id}", ['name' => 'Renamed']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $stored = Guest::query()->find($guest->id);
        $this->assertSame('Renamed', $stored->name);
        $this->assertSame('checked_in', $stored->status);
        $this->assertNotNull($stored->checked_in_at);
    }

    public function test_an_update_cannot_blank_the_name_or_email()
    {
        $guest = $this->createGuest($this->event);

        $response = $this->rest('PATCH', "events/{$this->event->id}/guests/{$guest->id}", ['name' => '', 'email' => '']);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('name', $response['data']);
        $this->assertArrayHasKey('email', $response['data']);
    }

    public function test_the_edit_form_can_still_unlink_a_guest_from_their_wordpress_user()
    {
        $guest = $this->createGuest($this->event, ['user_id' => $this->createUser('subscriber')]);

        $response = $this->rest('PATCH', "events/{$this->event->id}/guests/{$guest->id}", [
            'name'    => $guest->name,
            'email'   => $guest->email,
            'status'  => 'going',
            'user_id' => null,
        ]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Guest::query()->find($guest->id)->user_id);
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
