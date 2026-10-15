<?php

namespace FluentEvent\Tests\Integration;

use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\Modules\FluentCommunity\CommunityEvents;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Member RSVP from the FluentCommunity portal. RSVP is for logged-in members,
 * going / not going, no seat limit; an open event takes no registrations.
 */
class CommunityRsvpTest extends IntegrationTestCase
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

    public function test_a_logged_out_visitor_cannot_rsvp()
    {
        [$event] = $this->communityEvent();
        $this->actAs(0);

        $this->assertContains($this->rsvp($event, 'going')['status'], [401, 403]);
    }

    public function test_the_meeting_link_is_revealed_only_to_a_member_who_is_going()
    {
        [$event] = $this->communityEvent(['venue_type' => 'online'], ['online_meeting_url' => 'https://meet.example.test/launch']);

        $this->assertSame('https://meet.example.test/launch', $this->rsvp($event, 'going')['data']['event']['online_meeting_url']);
        $this->assertSame('', $this->rsvp($event, 'not_going')['data']['event']['online_meeting_url']);
    }
}
