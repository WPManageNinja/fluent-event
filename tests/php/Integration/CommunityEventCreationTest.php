<?php

namespace FluentEvent\Tests\Integration;

use FluentCommunity\App\Http\Controllers\FeedsController as FcomFeedsController;
use FluentCommunity\App\Http\Controllers\SpaceController as FcomSpaceController;
use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Models\Space as FcomSpace;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\CalendarService;
use FluentEvent\App\Services\Settings;
use FluentEvent\Modules\FluentCommunity\CommunityEvents;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Posting an event from the FluentCommunity composer — the only way an event is created in v1.
 *
 * The hooks are fired the way FeedsController::store() fires them, not through its REST route:
 * fcom's own framework opens a raw START TRANSACTION, which would commit this test's data.
 * test_fcom_still_fires_the_hooks_event_creation_hangs_on guards against fcom renaming them.
 */
class CommunityEventCreationTest extends IntegrationTestCase
{
    /** @var int */
    private $host;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            $this->markTestSkipped('FluentCommunity is not active.');
        }

        $this->host = $this->createAdmin();
        $this->actAs($this->host);
    }

    public function test_posting_an_event_creates_the_event_its_space_calendar_and_reminder()
    {
        Settings::update(Settings::EMAIL_NOTIFICATIONS, Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS]);
        $spaceId = $this->createSpace();
        $request = $this->postRequest(['title' => 'Community meetup']);

        $data = $this->validate(['space_id' => $spaceId], $request);
        $this->assertNotWPError($data);
        $this->assertSame(CommunityEvents::CONTENT_TYPE, $data['content_type']);

        $feed = $this->savePost($data);
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);

        $eventId = (int) (FcomFeed::query()->find($feed->id)->meta['event_id'] ?? 0);
        $event = Event::query()->find($eventId);
        $this->assertNotNull($event, 'The post was not linked to a new event.');
        $this->assertSame('Community meetup', $event->title);
        $this->assertSame('private', $event->status);
        $this->assertSame('private', get_post($event->post_id)->post_status);
        $this->assertSame($this->host, (int) get_post($event->post_id)->post_author);

        $calendar = Calendar::query()->find($event->calendar_id);
        $this->assertSame('private', $calendar->status);
        $this->assertSame($spaceId, (int) $calendar->meta[CalendarService::SPACE_META_KEY]);

        $link = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $event->id)
            ->byKey(CommunityEvents::FEED_META_KEY)
            ->first();
        $this->assertSame((int) $feed->id, (int) $link->value);

        $this->assertTrue($this->hasQueuedAction('fluent_event/send_event_reminders_to_guests', [(int) $event->id, DAY_IN_SECONDS]));
    }

    public function test_a_post_whose_event_fails_to_save_is_deleted()
    {
        $spaceId = $this->createSpace();
        $request = $this->postRequest();
        $feed = $this->savePost($this->validate(['space_id' => $spaceId], $request));
        $eventsBefore = $this->rowCount($this->table('fe_event_details'));

        // wp_insert_post() then returns a WP_Error, which PostService turns into an exception.
        $this->addTestFilter('wp_insert_post_empty_content', '__return_true');

        try {
            do_action('fluent_community/feed/just_created_type_event', $feed, $request);
            $this->fail('A failed event save must not be swallowed.');
        } catch (\Throwable $e) {
            $this->assertSame('The event could not be saved. Please try again.', $e->getMessage());
        }

        $this->assertNull(FcomFeed::query()->find($feed->id), 'An event post without its event would render a dead card.');
        $this->assertSame($eventsBefore, $this->rowCount($this->table('fe_event_details')));
    }

    public function test_deleting_the_post_parks_the_event_as_a_draft_with_its_guests()
    {
        Settings::update(Settings::EMAIL_NOTIFICATIONS, Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS]);
        $request = $this->postRequest();
        $feed = $this->savePost($this->validate(['space_id' => $this->createSpace()], $request));
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);

        $event = Event::query()->find((int) FcomFeed::query()->find($feed->id)->meta['event_id']);
        $this->createGuest($event);
        $reminder = [(int) $event->id, DAY_IN_SECONDS];
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_event_reminders_to_guests', $reminder));

        do_action('fluent_community/feed/before_deleted', $feed);

        $event = Event::query()->find($event->id);
        $this->assertNotNull($event, 'Deleting the post must not delete the event.');
        $this->assertSame('draft', $event->status);
        $this->assertSame('draft', get_post($event->post_id)->post_status);
        $this->assertSame(1, $event->guests()->count());
        $this->assertSame(0, CommunityEvents::feedIdOf($event), 'The event still points at the deleted post.');
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_event_reminders_to_guests', $reminder));
    }

    public function test_editing_the_space_leaves_its_calendar_title_and_slug_alone()
    {
        $spaceId = $this->createSpace();
        $request = $this->postRequest();
        do_action('fluent_community/feed/just_created_type_event', $this->savePost($this->validate(['space_id' => $spaceId], $request)), $request);
        $calendar = CalendarService::findBySpace($spaceId);
        $firedBefore = did_action('fluent_community/space/updated');

        FcomSpace::query()->find($spaceId)->updateCustomData([
            'title' => 'Renamed space',
            'slug'  => 'fe-test-renamed-' . strtolower(wp_generate_password(6, false, false)),
        ]);

        $this->assertSame($firedBefore + 1, did_action('fluent_community/space/updated'), 'fcom no longer fires space/updated on an edit.');
        $stored = Calendar::query()->find($calendar->id);
        $this->assertSame($calendar->title, $stored->title);
        $this->assertSame($calendar->slug, $stored->slug);
        $this->assertSame($spaceId, (int) $stored->meta[CalendarService::SPACE_META_KEY]);
    }

    public function test_deleting_the_space_parks_its_calendar_and_every_event_as_drafts_with_their_guests()
    {
        Settings::update(Settings::EMAIL_NOTIFICATIONS, Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS]);
        $spaceId = $this->createSpace();
        $request = $this->postRequest();
        $feed = $this->savePost($this->validate(['space_id' => $spaceId], $request));
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);

        $event = Event::query()->find((int) FcomFeed::query()->find($feed->id)->meta['event_id']);
        $adminEvent = $this->createEvent(['calendar_id' => $event->calendar_id]);
        $this->createGuest($event);
        $reminder = [(int) $event->id, DAY_IN_SECONDS];
        $this->assertTrue($this->hasQueuedAction('fluent_event/send_event_reminders_to_guests', $reminder));

        // As SpaceController::deleteBySlug(): the hook, then a bulk post delete with no per-post hooks.
        do_action('fluent_community/space/before_delete', FcomSpace::query()->find($spaceId));
        FcomFeed::query()->where('space_id', $spaceId)->delete();

        foreach ([$event, $adminEvent] as $parked) {
            $parked = Event::query()->find($parked->id);
            $this->assertNotNull($parked, 'Deleting the space must not delete its events.');
            $this->assertSame('draft', $parked->status);
            $this->assertSame('draft', get_post($parked->post_id)->post_status);
        }

        $this->assertSame(1, Event::query()->find($event->id)->guests()->count());
        $this->assertSame(0, CommunityEvents::feedIdOf($event), 'The event still points at the deleted post.');
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_event_reminders_to_guests', $reminder));

        $calendar = Calendar::query()->find($event->calendar_id);
        $this->assertNotNull($calendar, 'Deleting the space must not delete its calendar.');
        $this->assertSame('draft', $calendar->status);
        $this->assertNull(CalendarService::findBySpace($spaceId), 'The calendar still points at the deleted space.');
    }

    public function test_a_space_calendar_links_to_its_space_only_while_the_space_exists()
    {
        $spaceId = $this->createSpace();
        $request = $this->postRequest();
        do_action('fluent_community/feed/just_created_type_event', $this->savePost($this->validate(['space_id' => $spaceId], $request)), $request);
        $calendarId = CalendarService::findBySpace($spaceId)->id;

        $response = $this->rest('GET', "calendars/{$calendarId}");
        $this->assertSame(200, $response['status']);
        $this->assertSame(FcomSpace::query()->find($spaceId)->getPermalink(), $response['data']['space_url']);

        $this->wpdb->delete($this->table('fcom_spaces'), ['id' => $spaceId]);
        $this->assertArrayNotHasKey('space_url', $this->rest('GET', "calendars/{$calendarId}")['data']);
    }

    public function test_the_events_list_links_a_community_event_to_its_post()
    {
        $request = $this->postRequest(['title' => 'Feed link probe']);
        $feed = $this->savePost($this->validate(['space_id' => $this->createSpace()], $request));
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);
        $feed = FcomFeed::query()->find($feed->id);
        $plainEvent = $this->createEvent(['title' => 'Feed link probe, no post']);

        $response = $this->rest('GET', 'events', ['search' => 'Feed link probe']);
        $this->assertSame(200, $response['status']);
        $events = array_column($response['data']['events']['data'], null, 'id');

        $this->assertSame($feed->getPermalink(), $events[CommunityEvents::eventIdOf($feed)]['feed_url']);
        $this->assertArrayNotHasKey('feed_url', $events[$plainEvent->id]);
    }

    public function test_fcom_still_fires_the_hook_space_deletion_hangs_on()
    {
        $delete = new \ReflectionMethod(FcomSpaceController::class, 'deleteBySlug');
        $lines = file($delete->getFileName());
        $body = implode('', array_slice($lines, $delete->getStartLine() - 1, $delete->getEndLine() - $delete->getStartLine() + 1));

        $this->assertStringContainsString("'fluent_community/space/before_delete'", $body);
    }

    public function test_an_event_post_is_refused_to_a_non_admin_outside_a_discussion_space_or_with_bad_dates()
    {
        $spaceId = $this->createSpace();

        // Creating events is for site administrators; an editor is a member like any other.
        foreach (['subscriber', 'editor'] as $role) {
            $this->actAs($this->createUser($role));
            $this->assertWPErrorCode('fluent_event_forbidden', $this->validate(['space_id' => $spaceId], $this->postRequest()));
        }

        $this->actAs($this->host);
        $courseSpaceId = $this->createSpace('course');
        $this->assertWPErrorCode('fluent_event_space', $this->validate(['space_id' => $courseSpaceId], $this->postRequest()));

        $endsBeforeItStarts = $this->postRequest([
            'start_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days')),
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('+2 days')),
        ]);
        $this->assertInstanceOf(\WP_Error::class, $this->validate(['space_id' => $spaceId], $endsBeforeItStarts));
    }

    public function test_a_community_wide_post_files_its_event_in_the_one_private_community_calendar()
    {
        $this->setGlobalPosts(true);
        $this->forgetSiteGlobalCalendar();

        $first = $this->postCommunityWide('Town hall');
        $second = $this->postCommunityWide('Town hall, part two');

        $calendar = Calendar::query()->find($first->calendar_id);
        $this->assertSame('Community', $calendar->title);
        $this->assertSame('private', $calendar->status);
        $this->assertTrue($calendar->meta[CalendarService::GLOBAL_META_KEY]);
        $this->assertArrayNotHasKey(CalendarService::SPACE_META_KEY, $calendar->meta);
        $this->assertSame((int) $first->calendar_id, (int) $second->calendar_id, 'A second community-wide event made another calendar.');
        $this->assertSame('private', $first->status);
    }

    public function test_community_wide_events_are_refused_while_fcom_global_posts_are_off()
    {
        $this->setGlobalPosts(false);

        $this->assertWPErrorCode('fluent_event_space', $this->validate(['space_id' => null], $this->postRequest()));

        $feed = $this->savePost($this->validate(['space_id' => $this->createSpace()], $this->postRequest()));
        $moved = apply_filters('fluent_community/feed/update_feed_data_type_event', [], ['move_to_profile' => 'yes'], $feed);
        $this->assertWPErrorCode('fluent_event_space', $moved);
    }

    public function test_moving_an_event_post_between_a_space_and_community_wide_moves_its_event_and_guests()
    {
        $this->setGlobalPosts(true);
        $spaceId = $this->createSpace();
        $request = $this->postRequest();
        $feed = $this->savePost($this->validate(['space_id' => $spaceId], $request));
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);
        $feed = FcomFeed::query()->find($feed->id);
        $event = Event::query()->find(CommunityEvents::eventIdOf($feed));
        $guest = $this->createGuest($event);

        $this->assertNotWPError(apply_filters('fluent_community/feed/update_feed_data_type_event', [], ['move_to_profile' => 'yes'], $feed));
        $feed->space_id = null;
        $feed->save();
        do_action('fluent_community/feed/updated', $feed, ['space_id' => null]);

        $globalId = (int) CalendarService::findGlobal()->id;
        $this->assertSame($globalId, (int) Event::query()->find($event->id)->calendar_id);
        $this->assertSame($globalId, (int) $guest->fresh()->calendar_id);

        $feed->space_id = $spaceId;
        $feed->save();
        do_action('fluent_community/feed/updated', $feed, ['space_id' => $spaceId]);

        $this->assertSame((int) CalendarService::findBySpace($spaceId)->id, (int) Event::query()->find($event->id)->calendar_id);
    }

    public function test_moving_an_event_into_the_community_calendar_in_the_admin_makes_its_post_community_wide()
    {
        $request = $this->postRequest();
        $feed = $this->savePost($this->validate(['space_id' => $this->createSpace()], $request));
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);
        $event = Event::query()->find(CommunityEvents::eventIdOf(FcomFeed::query()->find($feed->id)));

        $event->calendar_id = CalendarService::createGlobal()->id;
        $event->save();
        do_action('fluent_event/event/calendar_changed', $event);

        $feed = FcomFeed::query()->find($feed->id);
        $this->assertNull($feed->space_id);
        $this->assertSame((int) $event->id, CommunityEvents::eventIdOf($feed), 'The post let go of its event.');
    }

    public function test_the_global_calendar_lookup_reads_the_decoded_meta()
    {
        $this->forgetSiteGlobalCalendar();

        // A calendar whose meta only mentions the key, with any value but true, is not the global one.
        $this->wpdb->insert($this->table('fe_calendars'), [
            'title'  => 'Lookalike',
            'slug'   => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'status' => 'private',
            'meta'   => wp_json_encode([CalendarService::GLOBAL_META_KEY => false]),
        ]);

        $this->assertNull(CalendarService::findGlobal());

        $global = CalendarService::createGlobal();
        $this->assertSame((int) $global->id, (int) CalendarService::findGlobal()->id);
    }

    public function test_fcom_still_fires_the_hooks_event_creation_hangs_on()
    {
        $store = new \ReflectionMethod(FcomFeedsController::class, 'store');
        $lines = file($store->getFileName());
        $body = implode('', array_slice($lines, $store->getStartLine() - 1, $store->getEndLine() - $store->getStartLine() + 1));

        $this->assertStringContainsString("'fluent_community/feed/new_feed_data_type_'", $body);
        $this->assertStringContainsString("'fluent_community/feed/just_created_type_'", $body);
    }

    // fcom caches its settings statically, so its own filter is the switch a test can flip.
    private function setGlobalPosts($enabled)
    {
        $this->addTestFilter('fluent_community/has_global_post', $enabled ? '__return_true' : '__return_false');
    }

    // The dev site may already hold a real Community calendar; unflag it inside this test's rolled-back transaction.
    private function forgetSiteGlobalCalendar()
    {
        while ($calendar = CalendarService::findGlobal()) {
            $calendar->meta = array_merge((array) $calendar->meta, [CalendarService::GLOBAL_META_KEY => false]);
            $calendar->save();
        }
    }

    private function postCommunityWide($title)
    {
        $request = $this->postRequest(['title' => $title]);
        $data = $this->validate(['space_id' => null], $request);
        $this->assertNotWPError($data);

        $feed = $this->savePost($data);
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);

        return Event::query()->find(CommunityEvents::eventIdOf(FcomFeed::query()->find($feed->id)));
    }

    private function createSpace($type = 'community')
    {
        $this->wpdb->insert($this->table('fcom_spaces'), [
            'created_by' => $this->host,
            'title'      => 'Integration space',
            'slug'       => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'type'       => $type,
            'privacy'    => 'public',
            'status'     => 'published',
        ]);

        return (int) $this->wpdb->insert_id;
    }

    /**
     * The composer's request: fcom passes it to both hooks as $requestData.
     */
    private function postRequest(array $event = [])
    {
        return [
            'content_type' => CommunityEvents::CONTENT_TYPE,
            'event'        => array_merge([
                'title'            => 'Integration event',
                'description'      => '<p>Written by the integration suite; rolled back.</p>',
                'start_at'         => gmdate('Y-m-d H:i:s', strtotime('+3 days')),
                'end_at'           => gmdate('Y-m-d H:i:s', strtotime('+3 days +2 hours')),
                'timezone'         => 'UTC',
                'venue_type'       => 'offline',
                'venue'            => 'Hall A',
                'address'          => '',
                'entry_type'       => 'rsvp',
                'show_guest_count' => 'yes',
            ], $event),
        ];
    }

    private function validate(array $data, array $request)
    {
        return apply_filters('fluent_community/feed/new_feed_data_type_event', array_merge(['meta' => []], $data), $request);
    }

    private function savePost(array $data)
    {
        return FcomFeed::create(array_merge($data, [
            'user_id'          => $this->host,
            'title'            => 'Integration event post',
            'slug'             => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'message'          => 'Rolled back.',
            'message_rendered' => '<p>Rolled back.</p>',
            'type'             => 'text',
            'privacy'          => 'public',
            'status'           => 'published',
        ]));
    }

    private function assertNotWPError($value)
    {
        $this->assertFalse(is_wp_error($value), is_wp_error($value) ? $value->get_error_message() : '');
    }

    private function assertWPErrorCode($code, $value)
    {
        $this->assertInstanceOf(\WP_Error::class, $value);
        $this->assertSame($code, $value->get_error_code());
    }
}
