<?php

namespace FluentEvent\Tests\Integration;

use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\FeedsHelper as FcomFeedsHelper;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\Modules\FluentCommunity\CommunityEvents;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The endpoints the FluentCommunity portal calls: the events list and member RSVP
 * (logged-in members, going / not going up to the event's capacity; an open event takes none),
 * who may reach them, and what event data they return.
 */
class CommunityEndpointsTest extends IntegrationTestCase
{
    /** @var int */
    private $spaceId;

    /** @var int */
    private $member;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            $this->markTestSkipped('FluentCommunity is not active.');
        }

        $this->spaceId = (int) $this->wpdb->get_var(
            "SELECT id FROM {$this->table('fcom_spaces')} WHERE type = 'community' AND privacy = 'public' AND status = 'published' ORDER BY id LIMIT 1"
        );

        if (!$this->spaceId) {
            $this->markTestSkipped('No public discussion space on this install to post into.');
        }

        $this->member = $this->createUser('subscriber');
        FcomHelper::addToSpace($this->spaceId, $this->member, 'member', 'by_admin');

        $this->actAs($this->member);
    }

    /**
     * An event linked to a published post in the space, as FeedHandler::createEvent() leaves it.
     */
    private function communityEvent(array $overrides = [], array $meta = [])
    {
        $event = $this->createEvent($overrides, $meta);

        $feed = FcomFeed::create([
            'user_id'          => $this->member,
            'title'            => 'Integration event post',
            'slug'             => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'message'          => 'Rolled back.',
            'message_rendered' => '<p>Rolled back.</p>',
            'type'             => 'text',
            'content_type'     => CommunityEvents::CONTENT_TYPE,
            'space_id'         => $this->spaceId,
            'privacy'          => 'public',
            'status'           => 'published',
            'meta'             => ['event_id' => (int) $event->id],
        ]);

        Meta::query()->create([
            'object_type' => Meta::OBJECT_TYPE_EVENT,
            'object_id'   => $event->id,
            'meta_key'    => CommunityEvents::FEED_META_KEY,
            'value'       => (int) $feed->id,
        ]);

        return [$event, $feed];
    }

    private function rsvp(Event $event, $status)
    {
        return $this->rest('POST', "community/events/{$event->id}/register", ['status' => $status]);
    }

    private function guestsOf(Event $event)
    {
        return Guest::query()->where('event_id', $event->id)->get();
    }

    public function test_a_member_saying_going_becomes_one_guest_row()
    {
        [$event, $feed] = $this->communityEvent();

        $response = $this->rsvp($event, 'going');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame($this->member, $guests[0]->user_id);
        $this->assertSame('going', $guests[0]->status);
        $this->assertSame((int) $feed->id, (int) $guests[0]->source_id);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guests[0]->id]));
    }

    public function test_saying_going_twice_keeps_one_row()
    {
        [$event] = $this->communityEvent();

        $this->rsvp($event, 'going');
        $this->rsvp($event, 'going');

        $this->assertCount(1, $this->guestsOf($event));
    }

    public function test_changing_to_not_going_queues_a_cancellation()
    {
        [$event] = $this->communityEvent();
        $this->rsvp($event, 'going');

        $response = $this->rsvp($event, 'not_going');

        $this->assertSame(200, $response['status']);
        $guest = $this->guestsOf($event)[0];
        $this->assertSame('not_going', $guest->status);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $guest->id]));
    }

    public function test_a_checked_in_guest_stays_checked_in_when_saying_going_again()
    {
        [$event] = $this->communityEvent();
        $this->createGuest($event, ['user_id' => $this->member, 'email' => get_userdata($this->member)->user_email, 'status' => 'checked_in']);

        $this->assertSame('checked_in', $this->rsvp($event, 'going')['data']['status']);
    }

    public function test_a_row_an_admin_added_under_the_members_email_is_claimed_not_duplicated()
    {
        [$event] = $this->communityEvent();
        $existing = $this->createGuest($event, ['email' => get_userdata($this->member)->user_email, 'status' => 'not_going']);

        $this->assertSame(200, $this->rsvp($event, 'going')['status']);

        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame((int) $existing->id, (int) $guests[0]->id);
        $this->assertSame($this->member, $guests[0]->user_id);
    }

    public function test_an_email_already_registered_to_another_member_is_refused()
    {
        [$event] = $this->communityEvent();
        $this->createGuest($event, ['email' => get_userdata($this->member)->user_email, 'user_id' => $this->createUser('subscriber')]);

        $this->assertSame(422, $this->rsvp($event, 'going')['status']);
    }

    public function test_an_open_event_takes_no_registrations()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);

        $this->assertSame(422, $this->rsvp($event, 'going')['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    public function test_an_event_that_has_ended_takes_no_registrations()
    {
        [$event] = $this->communityEvent([
            'start_at' => gmdate('Y-m-d H:i:s', strtotime('-3 hours')),
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->assertSame(422, $this->rsvp($event, 'going')['status']);
    }

    public function test_the_last_seat_goes_to_the_member_who_takes_it()
    {
        [$event] = $this->communityEvent([], ['capacity' => 2]);
        $this->createGuest($event);

        $response = $this->rsvp($event, 'going');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertTrue($response['data']['event']['is_full']);
    }

    // A capacity lowered below the going count keeps everyone already going and only blocks new seats.
    public function test_a_full_event_refuses_a_new_going_and_keeps_everyone_already_going()
    {
        [$event] = $this->communityEvent([], ['capacity' => 1]);
        $this->createGuest($event);
        $this->createGuest($event);

        $this->assertSame(422, $this->rsvp($event, 'going')['status']);

        $guests = $this->guestsOf($event);
        $this->assertCount(2, $guests);
        $this->assertSame(['going', 'going'], $guests->pluck('status')->all());
    }

    public function test_a_member_who_said_not_going_cannot_take_a_seat_once_the_event_is_full()
    {
        [$event] = $this->communityEvent([], ['capacity' => 1]);
        $this->rsvp($event, 'not_going');
        $this->createGuest($event);

        $this->assertSame(422, $this->rsvp($event, 'going')['status']);
        $this->assertSame('not_going', Guest::query()->where('event_id', $event->id)->where('user_id', $this->member)->value('status'));
    }

    public function test_a_guest_already_going_keeps_their_seat_on_a_full_event()
    {
        [$event] = $this->communityEvent([], ['capacity' => 1]);
        $this->rsvp($event, 'going');

        $this->assertSame(200, $this->rsvp($event, 'going')['status']);
        $this->assertSame(200, $this->rsvp($event, 'not_going')['status']);
    }

    public function test_a_status_other_than_going_or_not_going_is_refused()
    {
        [$event] = $this->communityEvent();

        $this->assertSame(422, $this->rsvp($event, 'checked_in')['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    public function test_a_draft_event_is_not_found()
    {
        [$event] = $this->communityEvent(['status' => 'draft']);

        $this->assertSame(404, $this->rsvp($event, 'going')['status']);
    }

    public function test_an_event_with_no_community_post_is_not_found()
    {
        $this->assertSame(404, $this->rsvp($this->createEvent(), 'going')['status']);
    }

    // Only the event page shows the description; everything else skips the wp_kses_post it costs per event.
    public function test_only_the_event_page_gets_the_description()
    {
        [$event, $feed] = $this->communityEvent();
        $description = '<p>Written by the integration suite; rolled back.</p>';

        $listed = array_column($this->rest('GET', 'community/events')['data']['events'], null, 'id');
        $this->assertArrayNotHasKey('description', $listed[$event->id]);
        $this->assertArrayNotHasKey('description', $this->rsvp($event, 'going')['data']['event']);

        // A feed page and a single post rendered in one request must not share a cached copy.
        $page = FcomFeedsHelper::transformFeedsCollection(FcomFeed::query()->where('id', $feed->id)->get());
        $single = FcomFeedsHelper::transformFeed(FcomFeed::query()->find($feed->id));

        $this->assertArrayNotHasKey('description', $page->first()->fluent_event);
        $this->assertSame($description, $single->fluent_event['description']);
    }

    // An end before the start is no calendar window, so it pages like any other list.
    public function test_a_reversed_date_window_still_pages()
    {
        foreach (range(1, 2) as $i) {
            $this->communityEvent([
                'start_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day')),
                'end_at'   => gmdate('Y-m-d H:i:s', strtotime('+10 days')),
            ]);
        }

        $response = $this->rest('GET', 'community/events', [
            'from'     => gmdate('Y-m-d', strtotime('+5 days')),
            'to'       => gmdate('Y-m-d', strtotime('+3 days')),
            'paginate' => 'false',
            'per_page' => 1,
        ]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertCount(1, $response['data']['events']);
        $this->assertSame(2, (int) $response['data']['last_page']);
    }

    // Blocking only flips the profile status; the member keeps their space memberships.
    public function test_a_blocked_member_can_neither_list_events_nor_rsvp()
    {
        [$event] = $this->communityEvent();
        XProfile::query()->where('user_id', $this->member)->update(['status' => 'blocked']);

        $this->assertSame(403, $this->rest('GET', 'community/events')['status']);
        $this->assertSame(403, $this->rsvp($event, 'going')['status']);
        $this->assertSame(0, Guest::query()->where('event_id', $event->id)->count());
    }

    public function test_a_user_the_portal_shuts_out_can_neither_list_events_nor_rsvp()
    {
        [$event] = $this->communityEvent();
        $this->addTestFilter('fluent_community/can_access_portal', '__return_false');

        $this->assertSame(403, $this->rest('GET', 'community/events')['status']);
        $this->assertSame(403, $this->rsvp($event, 'going')['status']);
        $this->assertSame(0, Guest::query()->where('event_id', $event->id)->count());
    }

    public function test_a_logged_out_visitor_cannot_rsvp()
    {
        [$event] = $this->communityEvent();
        $this->actAs(0);

        $this->assertContains($this->rsvp($event, 'going')['status'], [401, 403]);
    }

    public function test_the_guest_count_follows_the_events_setting()
    {
        [$shown] = $this->communityEvent();
        [$hidden] = $this->communityEvent([], ['show_guest_count' => 'no']);

        $this->assertSame(1, $this->rsvp($shown, 'going')['data']['event']['guest_count']);
        $this->assertNull($this->rsvp($hidden, 'going')['data']['event']['guest_count']);
    }

    public function test_staff_see_no_guest_count_when_the_event_hides_it()
    {
        [$event] = $this->communityEvent([], ['show_guest_count' => 'no']);
        $staff = $this->createUser('editor', ['read_guests', 'read_events']);
        FcomHelper::addToSpace($this->spaceId, $staff, 'member', 'by_admin');
        $this->actAs($staff);

        $this->assertNull($this->rsvp($event, 'going')['data']['event']['guest_count']);
    }

    public function test_the_meeting_link_is_revealed_only_to_a_member_who_is_going()
    {
        [$event] = $this->communityEvent(['venue_type' => 'online'], ['online_meeting_url' => 'https://meet.example.test/launch']);

        $this->assertSame('https://meet.example.test/launch', $this->rsvp($event, 'going')['data']['event']['online_meeting_url']);
        $this->assertSame('', $this->rsvp($event, 'not_going')['data']['event']['online_meeting_url']);
    }
}
