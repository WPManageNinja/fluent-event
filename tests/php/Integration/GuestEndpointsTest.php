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

    // A checked-in guest holds a seat too, so removing one is a cancellation like a going guest's.
    public function test_removing_a_checked_in_guest_with_email_sends_a_cancellation()
    {
        $event = $this->createEvent();
        $guest = $this->createGuest($event, ['status' => 'checked_in']);

        $response = $this->rest('DELETE', "events/{$event->id}/guests/{$guest->id}", ['send_email' => 1]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertMailSentTo($guest->email);
    }

    // Capacity limits self-registration only; an admin can always add past it.
    public function test_an_admin_can_add_a_guest_to_a_full_event()
    {
        $full = $this->createEvent([], ['capacity' => 1]);
        $this->createGuest($full);

        $response = $this->addGuest(['name' => 'Plus one', 'email' => 'plusone@example.test'], $full);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('going', $response['data']['guest']['status']);
    }

    private function bulk(array $params)
    {
        return $this->rest('POST', "events/{$this->event->id}/guests/bulk-action", $params);
    }

    public function test_a_bulk_status_change_moves_only_the_selected_guests()
    {
        $first = $this->createGuest($this->event, ['status' => 'not_going']);
        $second = $this->createGuest($this->event, ['status' => 'not_going']);
        $untouched = $this->createGuest($this->event, ['status' => 'not_going']);

        $response = $this->bulk(['action' => 'change_status', 'status' => 'going', 'guest_ids' => [$first->id, $second->id]]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('going', Guest::query()->find($first->id)->status);
        $this->assertSame('going', Guest::query()->find($second->id)->status);
        $this->assertSame('not_going', Guest::query()->find($untouched->id)->status);
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $first->id]));
    }

    public function test_a_bulk_status_change_queues_the_matching_email_only_when_asked()
    {
        $told = $this->createGuest($this->event, ['status' => 'going']);
        $silent = $this->createGuest($this->event, ['status' => 'going']);

        $this->bulk(['action' => 'change_status', 'status' => 'cancelled', 'guest_ids' => [$told->id], 'send_email' => 1]);
        $this->bulk(['action' => 'change_status', 'status' => 'cancelled', 'guest_ids' => [$silent->id]]);

        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $told->id]));
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $silent->id]));
    }

    public function test_a_bulk_check_in_stamps_the_time()
    {
        $guest = $this->createGuest($this->event);

        $this->bulk(['action' => 'change_status', 'status' => 'checked_in', 'guest_ids' => [$guest->id]]);

        $this->assertNotEmpty(Guest::query()->find($guest->id)->checked_in_at);
    }

    public function test_a_bulk_delete_removes_only_the_selected_guests()
    {
        $gone = $this->createGuest($this->event);
        $kept = $this->createGuest($this->event);

        $this->assertSame(200, $this->bulk(['action' => 'delete', 'guest_ids' => [$gone->id]])['status']);
        $this->assertNull(Guest::query()->find($gone->id));
        $this->assertNotNull(Guest::query()->find($kept->id));
    }

    public function test_a_bulk_delete_fires_one_hook_pair_around_the_rows()
    {
        $first = $this->createGuest($this->event);
        $second = $this->createGuest($this->event);
        $ids = [(int) $first->id, (int) $second->id];
        $calls = [];

        $this->addTestFilter('fluent_event/guest/before_bulk_deleted', function ($event, $guests) use (&$calls) {
            $keys = array_map('intval', $guests->modelKeys());
            sort($keys);
            $calls[] = ['before', $keys, (int) $event->id, Guest::query()->whereIn('id', $keys)->count()];
        }, 10, 2);
        $this->addTestFilter('fluent_event/guest/bulk_deleted', function ($event, $guests) use (&$calls) {
            $keys = array_map('intval', $guests->modelKeys());
            sort($keys);
            $calls[] = ['after', $keys, Guest::query()->whereIn('id', $keys)->count()];
        }, 10, 2);
        $this->addTestFilter('fluent_event/guest/deleted', function () use (&$calls) {
            $calls[] = ['single'];
        });

        $this->bulk(['action' => 'delete', 'guest_ids' => $ids]);

        $this->assertSame([['before', $ids, (int) $this->event->id, 2], ['after', $ids, 0]], $calls);
    }

    public function test_a_bulk_status_change_fires_one_hook_with_the_previous_statuses()
    {
        $cancelled = $this->createGuest($this->event, ['status' => 'cancelled']);
        $notGoing = $this->createGuest($this->event, ['status' => 'not_going']);
        $alreadyGoing = $this->createGuest($this->event, ['status' => 'going']);
        $calls = [];

        $this->addTestFilter('fluent_event/guest/bulk_updated', function ($event, $guests, $previous) use (&$calls) {
            $statuses = $guests->pluck('status', 'id')->all();
            ksort($statuses);
            ksort($previous);
            $calls[] = [$statuses, $previous, (int) $event->id];
        }, 10, 3);
        $this->addTestFilter('fluent_event/guest/updated', function () use (&$calls) {
            $calls[] = 'single';
        });

        $this->bulk(['action' => 'change_status', 'status' => 'going', 'guest_ids' => [$cancelled->id, $notGoing->id, $alreadyGoing->id]]);

        $this->assertSame([[
            [(int) $cancelled->id => 'going', (int) $notGoing->id => 'going'],
            [(int) $cancelled->id => 'cancelled', (int) $notGoing->id => 'not_going'],
            (int) $this->event->id,
        ]], $calls);
    }

    public function test_a_bulk_action_leaves_guests_of_another_event_alone()
    {
        $other = $this->createGuest($this->createEvent(), ['status' => 'going']);

        $this->bulk(['action' => 'change_status', 'status' => 'cancelled', 'guest_ids' => [$other->id]]);
        $this->bulk(['action' => 'delete', 'guest_ids' => [$other->id]]);

        $this->assertSame('going', Guest::query()->find($other->id)->status);
    }

    /**
     * @dataProvider invalidBulkRequests
     */
    public function test_an_invalid_bulk_request_is_refused($params)
    {
        $guest = $this->createGuest($this->event);

        $this->assertSame(422, $this->bulk(array_merge(['guest_ids' => [$guest->id]], $params))['status']);
    }

    public function test_a_bulk_request_missing_its_action_or_list_gets_the_plugins_own_message()
    {
        $noAction = $this->bulk(['guest_ids' => [$this->createGuest($this->event)->id]]);
        $notAList = $this->bulk(['action' => 'delete', 'guest_ids' => '5']);

        $this->assertStringContainsString('Choose a bulk action.', wp_json_encode($noAction['data']));
        $this->assertStringContainsString('The selected guests must be a list.', wp_json_encode($notAList['data']));
    }

    public function invalidBulkRequests()
    {
        return [
            'unknown action'      => [['action' => 'archive']],
            'status change, none' => [['action' => 'change_status']],
            'unknown status'      => [['action' => 'change_status', 'status' => 'maybe']],
            'no guests'           => [['action' => 'delete', 'guest_ids' => []]],
            'guests not a list'   => [['action' => 'delete', 'guest_ids' => '5']],
        ];
    }
}
