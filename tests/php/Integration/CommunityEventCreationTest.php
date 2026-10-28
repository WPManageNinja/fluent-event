<?php

namespace FluentEvent\Tests\Integration;

use FluentCommunity\App\Http\Controllers\FeedsController as FcomFeedsController;
use FluentCommunity\App\Models\Feed as FcomFeed;
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

        $this->host = $this->createUser('editor', ['write_events']);
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
        $this->assertSame('public', $event->status);
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

    public function test_an_event_post_is_refused_without_event_access_outside_a_discussion_space_or_with_bad_dates()
    {
        $spaceId = $this->createSpace();

        $this->actAs($this->createUser('subscriber'));
        $this->assertWPErrorCode('fluent_event_forbidden', $this->validate(['space_id' => $spaceId], $this->postRequest()));

        $this->actAs($this->host);
        $courseSpaceId = $this->createSpace('course');
        $this->assertWPErrorCode('fluent_event_space', $this->validate(['space_id' => $courseSpaceId], $this->postRequest()));

        $endsBeforeItStarts = $this->postRequest([
            'start_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days')),
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('+2 days')),
        ]);
        $this->assertInstanceOf(\WP_Error::class, $this->validate(['space_id' => $spaceId], $endsBeforeItStarts));
    }

    public function test_a_posted_event_keeps_its_registration_window_and_one_closing_first_is_refused()
    {
        $spaceId = $this->createSpace();
        $window = ['registration_opens' => ['value' => 5, 'unit' => 'days'], 'registration_closes' => ['value' => 3, 'unit' => 'hours']];
        $request = $this->postRequest($window);

        $data = $this->validate(['space_id' => $spaceId], $request);
        $this->assertNotWPError($data);
        $feed = $this->savePost($data);
        do_action('fluent_community/feed/just_created_type_event', $feed, $request);

        $event = Event::query()->find((int) FcomFeed::query()->find($feed->id)->meta['event_id']);
        $this->assertSame($window['registration_opens'], $event->meta['registration_opens']);
        $this->assertSame($window['registration_closes'], $event->meta['registration_closes']);

        $closesFirst = $this->postRequest([
            'registration_opens'  => ['value' => 1, 'unit' => 'hours'],
            'registration_closes' => ['value' => 1, 'unit' => 'days'],
        ]);
        $this->assertInstanceOf(\WP_Error::class, $this->validate(['space_id' => $spaceId], $closesFirst));
    }

    public function test_fcom_still_fires_the_hooks_event_creation_hangs_on()
    {
        $store = new \ReflectionMethod(FcomFeedsController::class, 'store');
        $lines = file($store->getFileName());
        $body = implode('', array_slice($lines, $store->getStartLine() - 1, $store->getEndLine() - $store->getStartLine() + 1));

        $this->assertStringContainsString("'fluent_community/feed/new_feed_data_type_'", $body);
        $this->assertStringContainsString("'fluent_community/feed/just_created_type_'", $body);
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
