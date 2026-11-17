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
 * (logged-in members, going / not going, no seat limit; an open event takes none),
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

    private function registerGuest(Event $event, array $params = [])
    {
        return $this->rest('POST', "community/events/{$event->id}/register-guest", array_merge([
            'name'  => 'Walk-in Visitor',
            'email' => 'visitor@example.test',
            'nonce' => wp_create_nonce('fe_guest_register_' . $event->id),
        ], $params));
    }

    private function asVisitorOnAPublicPortal()
    {
        $this->addTestFilter('pre_option_fluent_community_settings', function () {
            return ['access' => ['acess_level' => 'public', 'access_roles' => []]];
        });

        FcomHelper::generalSettings(false);
        $this->actAs(0);
    }

    public function test_the_community_routes_refuse_while_fluentcommunity_has_fluentevent_switched_off()
    {
        [$event] = $this->communityEvent();
        $fired = $GLOBALS['wp_actions']['fluent_community/fluent_event/init'] ?? 0;
        unset($GLOBALS['wp_actions']['fluent_community/fluent_event/init']);

        try {
            $list = $this->rest('GET', 'community/events');
            $rsvp = $this->rsvp($event, 'going');
        } finally {
            $GLOBALS['wp_actions']['fluent_community/fluent_event/init'] = $fired;
        }

        $this->assertSame(403, $list['status'], wp_json_encode($list['data']));
        $this->assertSame(403, $rsvp['status'], wp_json_encode($rsvp['data']));
        $this->assertSame(0, Guest::query()->where('event_id', $event->id)->count());
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
        $this->assertSame('fluent_community', $guests[0]->source);
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

    // A deleted account's row must not keep refusing the same person, who signs up again with that email.
    public function test_a_new_account_with_a_deleted_members_email_takes_over_their_registration()
    {
        [$event] = $this->communityEvent();
        $leaving = $this->createUser('subscriber');
        FcomHelper::addToSpace($this->spaceId, $leaving, 'member', 'by_admin');
        $this->actAs($leaving);
        $this->rsvp($event, 'going');
        $email = get_userdata($leaving)->user_email;

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($leaving);

        $this->assertNull($this->guestsOf($event)[0]->user_id, 'The row outlives the account as an email guest');

        $returning = wp_insert_user(['user_login' => 'fe-returning-' . strtolower(wp_generate_password(6, false, false)), 'user_email' => $email, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
        FcomHelper::addToSpace($this->spaceId, $returning, 'member', 'by_admin');
        $this->actAs($returning);

        $response = $this->rsvp($event, 'not_going');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame([(int) $returning, 'not_going'], [(int) $guests[0]->user_id, $guests[0]->status]);
    }

    // Each flip queues an email, and the framework's limiter lets cookie-authenticated members through.
    // 10 a minute counts double, as over HTTP WordPress runs the check twice; in-process it runs once, so 20 pass here.
    public function test_a_member_flipping_their_answer_is_rate_limited()
    {
        [$event] = $this->communityEvent();
        $GLOBALS['wp_rest_auth_cookie'] = true;
        $statuses = [];

        try {
            for ($i = 0; $i < 21; $i++) {
                $statuses[] = $this->rsvp($event, $i % 2 ? 'not_going' : 'going')['status'];
            }
        } finally {
            unset($GLOBALS['wp_rest_auth_cookie']);
        }

        $this->assertSame(array_merge(array_fill(0, 20, 200), [429]), $statuses);
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

    /**
     * @dataProvider visitorRowStatuses
     */
    public function test_a_member_claiming_a_visitor_row_replaces_the_typed_name($status)
    {
        [$event] = $this->communityEvent();
        $member = get_userdata($this->member);
        $this->createGuest($event, [
            'name'   => 'Verify at evil.example',
            'email'  => $member->user_email,
            'status' => $status,
            'source' => 'fluent_community',
        ]);

        $this->assertSame(200, $this->rsvp($event, 'going')['status']);

        $guest = $this->guestsOf($event)[0];
        $this->assertSame($this->member, $guest->user_id);
        $this->assertSame($member->display_name, $guest->name);
    }

    public function visitorRowStatuses()
    {
        return ['already going' => ['going'], 'coming back after a cancel' => ['cancelled']];
    }

    public function test_a_member_claiming_an_admin_added_row_keeps_the_admins_name()
    {
        [$event] = $this->communityEvent();
        $this->createGuest($event, ['name' => 'Name From Admin', 'email' => get_userdata($this->member)->user_email]);

        $this->rsvp($event, 'going');

        $this->assertSame('Name From Admin', $this->guestsOf($event)[0]->name);
    }

    public function test_an_email_already_registered_to_another_member_is_refused()
    {
        [$event] = $this->communityEvent();
        $this->createGuest($event, ['email' => get_userdata($this->member)->user_email, 'user_id' => $this->createUser('subscriber')]);

        $this->assertSame(422, $this->rsvp($event, 'going')['status']);
    }

    // The portal shows an RSVP's message as a toast, so it names the answer the member just gave.
    public function test_the_reply_message_names_the_answer_or_the_registration()
    {
        [$event] = $this->communityEvent();
        $this->assertSame("RSVP updated. You're going!", $this->rsvp($event, 'going')['data']['message']);
        $this->assertSame("RSVP updated. You're not going.", $this->rsvp($event, 'not_going')['data']['message']);

        [$open] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->assertSame('You are registered.', $this->rsvp($open, 'going')['data']['message']);
    }

    public function test_a_member_registers_for_an_open_event_and_gets_the_email()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);

        $response = $this->rest('POST', "community/events/{$event->id}/register", ['status' => 'going', 'name' => 'Edited Name', 'email' => 'ignored@example.test']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame('going', $guests[0]->status);
        $this->assertSame('Edited Name', $guests[0]->name);
        $this->assertSame(get_userdata($this->member)->user_email, $guests[0]->email, 'A member registers with the account email');
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guests[0]->id]));
    }

    // Open has no undo in the portal; only an admin removes its guests.
    public function test_an_open_event_takes_no_not_going()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->rsvp($event, 'going');

        $this->assertSame(422, $this->rsvp($event, 'not_going')['status']);
        $this->assertSame('going', $this->guestsOf($event)[0]->status);
    }

    public function test_a_member_cancels_an_open_registration_and_can_register_again()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->rsvp($event, 'going');

        $response = $this->rsvp($event, 'cancelled');

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('Registration cancelled.', $response['data']['message']);
        $guest = $this->guestsOf($event)[0];
        $this->assertSame('cancelled', $guest->status);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $guest->id]));

        $this->assertSame(200, $this->rsvp($event, 'going')['status']);
        $this->assertCount(1, $this->guestsOf($event));
        $this->assertSame('going', $this->guestsOf($event)[0]->status);
    }

    public function test_a_checked_in_member_who_cancels_gets_the_cancellation_email()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->rsvp($event, 'going');
        Guest::query()->where('event_id', $event->id)->update(['status' => 'checked_in']);

        $this->assertSame(200, $this->rsvp($event, 'cancelled')['status']);

        $guest = $this->guestsOf($event)[0];
        $this->assertSame('cancelled', $guest->status);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $guest->id]));
    }

    // Two tabs cancelling together: the other tab's update lands first, so this request's claim on
    // "going" finds nothing to change and must not send a second email.
    public function test_a_cancel_that_loses_the_race_sends_no_second_email()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->rsvp($event, 'going');
        $guestId = (int) $this->guestsOf($event)[0]->id;
        $raced = false;

        $this->addTestFilter('query', function ($query) use (&$raced, $guestId) {
            if (!$raced && stripos($query, 'update') === 0 && strpos($query, 'fe_guests') !== false && strpos($query, "'going'") !== false) {
                $raced = true;
                $this->wpdb->update($this->table('fe_guests'), ['status' => 'cancelled'], ['id' => $guestId]);
            }

            return $query;
        });

        $this->assertSame(200, $this->rsvp($event, 'cancelled')['status']);
        $this->assertTrue($raced, 'The claim query never ran');
        $this->assertSame('cancelled', $this->guestsOf($event)[0]->status);
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_cancellation', [$guestId]));
    }

    // An admin checks the member in while their cancel is in flight: the cancel must say so, not report success.
    public function test_a_cancel_overtaken_by_a_check_in_asks_to_try_again()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->rsvp($event, 'going');
        $guestId = (int) $this->guestsOf($event)[0]->id;
        $raced = false;

        $this->addTestFilter('query', function ($query) use (&$raced, $guestId) {
            if (!$raced && stripos($query, 'update') === 0 && strpos($query, 'fe_guests') !== false && strpos($query, "'going'") !== false) {
                $raced = true;
                $this->wpdb->update($this->table('fe_guests'), ['status' => 'checked_in'], ['id' => $guestId]);
            }

            return $query;
        });

        $response = $this->rsvp($event, 'cancelled');

        $this->assertTrue($raced, 'The claim query never ran');
        $this->assertSame(409, $response['status']);
        $this->assertSame('checked_in', $this->guestsOf($event)[0]->status);
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_cancellation', [$guestId]));
    }

    // A cancel with nothing to cancel would otherwise create a cancelled row; RSVP answers not going instead.
    public function test_only_a_held_open_registration_can_be_cancelled()
    {
        [$open] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->assertSame(422, $this->rsvp($open, 'cancelled')['status']);
        $this->assertCount(0, $this->guestsOf($open));

        [$rsvp] = $this->communityEvent();
        $this->rsvp($rsvp, 'going');
        $this->assertSame(422, $this->rsvp($rsvp, 'cancelled')['status']);
        $this->assertSame('going', $this->guestsOf($rsvp)[0]->status);
    }

    public function test_a_visitor_registers_for_an_open_event_by_name_and_email()
    {
        [$event] = $this->communityEvent(['venue_type' => 'online'], ['entry_type' => 'open', 'online_meeting_url' => 'https://meet.example.test/open']);
        $this->asVisitorOnAPublicPortal();

        $response = $this->registerGuest($event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $guests = $this->guestsOf($event);
        $this->assertCount(1, $guests);
        $this->assertSame('visitor@example.test', $guests[0]->email);
        $this->assertEmpty($guests[0]->user_id);
        $this->assertSame('going', $guests[0]->status);
        $this->assertSame('fluent_community', $guests[0]->source);
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_registration_confirmation', [(int) $guests[0]->id]));
        $this->assertSame('https://meet.example.test/open', $response['data']['online_meeting_url'], 'The visitor just registered, so the link is theirs');
    }

    public function test_a_visitor_needs_the_events_own_nonce()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        [$other] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->asVisitorOnAPublicPortal();

        $this->assertSame(403, $this->registerGuest($event, ['nonce' => wp_create_nonce('fe_guest_register_' . $other->id)])['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    public function test_a_bot_filling_the_hidden_field_saves_nothing()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->asVisitorOnAPublicPortal();

        $this->assertSame(200, $this->registerGuest($event, ['website' => 'https://spam.example.test'])['status']);
        $this->assertCount(0, $this->guestsOf($event));
    }

    public function test_a_visitor_cannot_register_one_email_twice()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->asVisitorOnAPublicPortal();
        $first = $this->registerGuest($event);
        $again = $this->registerGuest($event, ['name' => 'Someone else']);

        // Same answer as a new registration.
        $this->assertSame(200, $again['status'], wp_json_encode($again['data']));
        $this->assertSame($first['data'], $again['data']);
        $this->assertCount(1, $this->guestsOf($event));
        $this->assertNotSame('Someone else', $this->guestsOf($event)[0]->name);
    }

    // A bad name fails alike for registered and new emails.
    public function test_an_invalid_name_does_not_tell_registered_emails_apart()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'open']);
        $this->asVisitorOnAPublicPortal();
        $this->registerGuest($event);

        foreach (['', str_repeat('a', 256)] as $name) {
            $registered = $this->registerGuest($event, ['name' => $name]);
            $unknown = $this->registerGuest($event, ['name' => $name, 'email' => 'nobody@example.test']);

            $this->assertSame(422, $registered['status'], wp_json_encode($registered['data']));
            $this->assertSame($unknown['status'], $registered['status']);
        }

        $this->assertCount(1, $this->guestsOf($event));
    }

    public function test_the_visitor_form_is_for_visitors_on_open_events_only()
    {
        [$open] = $this->communityEvent([], ['entry_type' => 'open']);
        [$rsvp] = $this->communityEvent();

        $this->assertSame(403, $this->registerGuest($open)['status'], 'A member registers in one click instead');

        $this->asVisitorOnAPublicPortal();
        $this->assertSame(422, $this->registerGuest($rsvp)['status']);
        $this->assertCount(0, $this->guestsOf($rsvp));
    }

    public function test_a_no_entry_event_takes_no_registrations()
    {
        [$event] = $this->communityEvent([], ['entry_type' => 'none']);

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

    private function spaceSlug()
    {
        return $this->wpdb->get_var($this->wpdb->prepare("SELECT slug FROM {$this->table('fcom_spaces')} WHERE id = %d", $this->spaceId));
    }

    private function bySlug($space, $slug)
    {
        return $this->rest('GET', 'community/events/by-slug', ['space' => $space, 'slug' => $slug]);
    }

    // The event page's URL carries the space and the event's own slug; its post, which fcom reads, has another.
    public function test_an_event_is_found_by_its_space_and_its_own_slug()
    {
        [$event, $feed] = $this->communityEvent();

        $response = $this->bySlug($this->spaceSlug(), get_post($event->post_id)->post_name);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame($feed->slug, $response['data']['feed_slug']);
    }

    public function test_an_event_is_not_found_under_another_space_by_another_slug_or_as_a_draft()
    {
        [$event] = $this->communityEvent();
        $slug = get_post($event->post_id)->post_name;

        $this->assertSame(404, $this->bySlug('not-this-space', $slug)['status']);
        $this->assertSame(404, $this->bySlug($this->spaceSlug(), 'no-such-event')['status']);

        Event::query()->where('id', $event->id)->update(['status' => 'draft']);
        $this->assertSame(404, $this->bySlug($this->spaceSlug(), $slug)['status']);
    }

    public function test_the_events_list_names_each_events_space_and_slug()
    {
        [$event] = $this->communityEvent();

        $listed = array_column($this->rest('GET', 'community/events', ['per_page' => 100])['data']['events'], null, 'id');

        $this->assertSame($this->spaceSlug(), $listed[$event->id]['space_slug']);
        $this->assertSame(get_post($event->post_id)->post_name, $listed[$event->id]['slug']);
    }

    // Only the event page shows the description; everything else skips the wp_kses_post it costs per event.
    public function test_only_the_event_page_gets_the_description()
    {
        [$event, $feed] = $this->communityEvent();
        $description = '<p>Written by the integration suite; rolled back.</p>';

        $listed = array_column($this->rest('GET', 'community/events')['data']['events'], null, 'id');
        $this->assertArrayNotHasKey('description', $listed[$event->id]);
        $this->assertArrayNotHasKey('description', $this->rsvp($event, 'going')['data']);

        // A feed page and a single post rendered in one request must not share a cached copy.
        $page = FcomFeedsHelper::transformFeedsCollection(FcomFeed::query()->where('id', $feed->id)->get());
        $single = FcomFeedsHelper::transformFeed(FcomFeed::query()->find($feed->id));

        $this->assertArrayNotHasKey('description', $page->first()->fluent_event);
        $this->assertSame($description, $single->fluent_event['description']);
    }

    // fcom's card view shows a post's featured image; an event post's is its event's cover, in lists and alone.
    public function test_an_event_post_carries_its_events_cover_as_its_featured_image()
    {
        $cover = 'https://example.test/cover.jpg';
        [, $withCover] = $this->communityEvent([], ['cover_url' => $cover]);
        [, $withoutCover] = $this->communityEvent();

        $page = FcomFeedsHelper::transformFeedsCollection(FcomFeed::query()->whereIn('id', [$withCover->id, $withoutCover->id])->get())->keyBy('id');
        $single = FcomFeedsHelper::transformFeed(FcomFeed::query()->find($withCover->id));

        $this->assertSame($cover, $page[$withCover->id]->featured_image);
        $this->assertNull($page[$withoutCover->id]->featured_image);
        $this->assertSame($cover, $single->featured_image);
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

        $this->rsvp($shown, 'going');
        $this->rsvp($hidden, 'going');
        $listed = array_column($this->rest('GET', 'community/events')['data']['events'], null, 'id');

        $this->assertSame(1, $listed[$shown->id]['guest_count']);
        $this->assertNull($listed[$hidden->id]['guest_count']);
    }

    public function test_an_admin_sees_no_guest_count_when_the_event_hides_it()
    {
        [$event] = $this->communityEvent([], ['show_guest_count' => 'no']);
        $admin = $this->createAdmin();
        FcomHelper::addToSpace($this->spaceId, $admin, 'member', 'by_admin');
        $this->actAs($admin);

        $this->rsvp($event, 'going');
        $listed = array_column($this->rest('GET', 'community/events')['data']['events'], null, 'id');

        $this->assertNull($listed[$event->id]['guest_count']);
    }

    public function test_the_meeting_link_is_revealed_only_to_a_member_who_is_going()
    {
        [$event] = $this->communityEvent(['venue_type' => 'online'], ['online_meeting_url' => 'https://meet.example.test/launch']);

        $this->assertSame('https://meet.example.test/launch', $this->rsvp($event, 'going')['data']['online_meeting_url']);
        $this->assertSame('', $this->rsvp($event, 'not_going')['data']['online_meeting_url']);
    }
}
