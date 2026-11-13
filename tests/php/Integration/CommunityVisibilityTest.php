<?php

namespace FluentEvent\Tests\Integration;

use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\CalendarService;
use FluentEvent\Modules\FluentCommunity\CommunityEvents;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Which community events a viewer can list and RSVP to: the space privacy boundary.
 * fcom decides access — members see public spaces plus the ones they joined; logged-out
 * visitors see public spaces only when the portal itself is public.
 */
class CommunityVisibilityTest extends IntegrationTestCase
{
    /** @var int */
    private $member;

    /** @var string Unique title prefix, so the list shows only this test's events. */
    private $token;

    /** @var array<string, int> privacy => space id */
    private $spaces = [];

    /** @var array<string, Event> privacy => event in that space */
    private $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            $this->markTestSkipped('FluentCommunity is not active.');
        }

        $this->token = 'fe-vis-' . strtolower(wp_generate_password(8, false, false));
        $this->setPortalAccess('logged_in');

        foreach (['public', 'private', 'secret', 'private_joined'] as $key) {
            $this->spaces[$key] = $this->createSpace($key === 'private_joined' ? 'private' : $key);
            $this->events[$key] = $this->spaceEvent($this->spaces[$key], $key);
        }

        $this->member = $this->createUser('subscriber');
        FcomHelper::addToSpace($this->spaces['private_joined'], $this->member, 'member', 'by_admin');
        $this->actAs($this->member);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            // generalSettings() keeps a static copy; drop the one this test forced, even if teardown failed.
            if (defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
                FcomHelper::generalSettings(false);
            }
        }
    }

    public function test_a_member_lists_events_from_public_spaces_and_spaces_they_joined_only()
    {
        $listed = $this->listedEventIds();

        $this->assertContains($this->events['public']->id, $listed);
        $this->assertContains($this->events['private_joined']->id, $listed);
        $this->assertNotContains($this->events['private']->id, $listed, 'An unjoined private space leaked into the list.');
        $this->assertNotContains($this->events['secret']->id, $listed, 'An unjoined secret space leaked into the list.');
    }

    public function test_a_member_cannot_rsvp_to_events_in_private_or_secret_spaces_they_have_not_joined()
    {
        foreach (['private', 'secret'] as $key) {
            $response = $this->rest('POST', "community/events/{$this->events[$key]->id}/register", ['status' => 'going']);

            $this->assertSame(404, $response['status'], "RSVP to an unjoined {$key} space must look like the event does not exist.");
            $this->assertSame(0, Guest::query()->where('event_id', $this->events[$key]->id)->count());
        }

        $joined = $this->rest('POST', "community/events/{$this->events['private_joined']->id}/register", ['status' => 'going']);
        $this->assertSame(200, $joined['status']);
    }

    public function test_a_draft_event_in_a_visible_space_is_not_listed()
    {
        $draft = $this->spaceEvent($this->spaces['public'], 'draft', ['status' => 'draft']);

        $this->assertNotContains($draft->id, $this->listedEventIds());
    }

    public function test_an_unlisted_event_stays_in_the_portal_list()
    {
        $unlisted = $this->spaceEvent($this->spaces['public'], 'unlisted', ['status' => 'unlisted']);

        $this->assertContains($unlisted->id, $this->listedEventIds());
    }

    public function test_an_event_whose_author_is_not_active_in_the_community_is_hidden_until_reactivated()
    {
        $hidden = $this->spaceEvent($this->spaces['public'], 'blocked-author', [], 'blocked');

        $this->assertNotContains($hidden->id, $this->listedEventIds());
        $this->assertSame(404, $this->rest('POST', "community/events/{$hidden->id}/register", ['status' => 'going'])['status']);

        $this->wpdb->update($this->table('fcom_xprofile'), ['status' => 'active'], ['user_id' => FcomFeed::query()->find(CommunityEvents::feedIdOf($hidden))->user_id]);

        $this->assertContains($hidden->id, $this->listedEventIds());
    }

    public function test_a_logged_out_visitor_on_a_public_portal_lists_public_space_events_only()
    {
        $this->setPortalAccess('public');
        $this->actAs(0);

        $listed = $this->listedEventIds();

        $this->assertContains($this->events['public']->id, $listed);
        $this->assertNotContains($this->events['private_joined']->id, $listed);
        $this->assertNotContains($this->events['private']->id, $listed);
        $this->assertNotContains($this->events['secret']->id, $listed);
    }

    public function test_a_logged_out_visitor_gets_no_events_when_the_portal_is_members_only()
    {
        $this->actAs(0);

        $this->assertContains($this->rest('GET', 'community/events', ['search' => $this->token])['status'], [401, 403]);
    }

    public function test_a_community_wide_event_is_listed_without_a_space_and_left_out_of_a_space_filter()
    {
        $global = $this->spaceEvent(null, 'community-wide');

        $items = array_column($this->listed(), null, 'id');
        $this->assertArrayHasKey($global->id, $items);
        $this->assertSame('', $items[$global->id]['space_slug']);
        $this->assertNull($items[$global->id]['space'], 'A community-wide event must carry no space icon.');
        $this->assertSame('Integration public space', $items[$this->events['public']->id]['space']['title']);

        $publicSlug = $this->wpdb->get_var($this->wpdb->prepare("SELECT slug FROM {$this->table('fcom_spaces')} WHERE id = %d", $this->spaces['public']));
        $this->assertNotContains($global->id, $this->listedEventIds(['space' => $publicSlug]));
    }

    public function test_a_logged_out_visitor_on_a_public_portal_sees_community_wide_events()
    {
        $global = $this->spaceEvent(null, 'community-wide');
        $this->setPortalAccess('public');
        $this->actAs(0);

        $this->assertContains($global->id, $this->listedEventIds());
    }

    public function test_the_event_page_finds_a_community_wide_event_only_without_a_space_in_its_url()
    {
        $global = $this->spaceEvent(null, 'community-wide');
        $slug = get_post($global->post_id)->post_name;
        $spaceEventSlug = get_post($this->events['public']->post_id)->post_name;
        $publicSlug = $this->wpdb->get_var($this->wpdb->prepare("SELECT slug FROM {$this->table('fcom_spaces')} WHERE id = %d", $this->spaces['public']));

        $this->assertSame(200, $this->rest('GET', 'community/events/by-slug', ['slug' => $slug])['status']);
        $this->assertSame(404, $this->rest('GET', 'community/events/by-slug', ['slug' => $slug, 'space' => $publicSlug])['status']);
        $this->assertSame(404, $this->rest('GET', 'community/events/by-slug', ['slug' => $spaceEventSlug])['status'], 'A space event opened without its space must not be found.');
    }

    private function listed(array $params = [])
    {
        $response = $this->rest('GET', 'community/events', array_merge(['search' => $this->token, 'per_page' => 50], $params));
        $this->assertSame(200, $response['status']);

        return $response['data']['events'];
    }

    private function listedEventIds(array $params = [])
    {
        return array_map('intval', array_column($this->listed($params), 'id'));
    }

    private function setPortalAccess($level)
    {
        $this->addTestFilter('pre_option_fluent_community_settings', function () use ($level) {
            return ['access' => ['acess_level' => $level, 'access_roles' => []]];
        });

        FcomHelper::generalSettings(false);
    }

    // Out of a private space they can't see its events, so the reminders must stop mailing them the link.
    public function test_a_member_who_leaves_a_private_space_loses_their_seats_in_its_upcoming_events()
    {
        $spaceId = $this->spaces['private_joined'];
        $calendar = CalendarService::createForSpace($spaceId, 'Private joined', '');
        $upcoming = $this->createEvent(['calendar_id' => $calendar->id]);
        $ended = $this->createEvent(['calendar_id' => $calendar->id, 'start_at' => gmdate('Y-m-d H:i:s', strtotime('-3 days')), 'end_at' => gmdate('Y-m-d H:i:s', strtotime('-2 days'))]);
        $seat = $this->createGuest($upcoming, ['user_id' => $this->member, 'calendar_id' => $calendar->id, 'status' => 'going']);
        $history = $this->createGuest($ended, ['user_id' => $this->member, 'calendar_id' => $calendar->id, 'status' => 'checked_in']);

        FcomHelper::removeFromSpace($spaceId, $this->member, 'by_admin');

        $this->assertSame('cancelled', Guest::query()->find($seat->id)->status);
        $this->assertSame('checked_in', Guest::query()->find($history->id)->status, 'A past attendance stays as it was');
        $this->assertFalse($this->hasQueuedAction('fluent_event/send_registration_cancellation', [(int) $seat->id]), 'Losing access is not a cancellation to email');
    }

    // A public space's events stay visible to someone who leaves it, so their seat stays too.
    public function test_a_member_who_leaves_a_public_space_keeps_their_seat()
    {
        $spaceId = $this->spaces['public'];
        FcomHelper::addToSpace($spaceId, $this->member, 'member', 'by_admin');
        $calendar = CalendarService::createForSpace($spaceId, 'Public', '');
        $event = $this->createEvent(['calendar_id' => $calendar->id]);
        $seat = $this->createGuest($event, ['user_id' => $this->member, 'calendar_id' => $calendar->id, 'status' => 'going']);

        FcomHelper::removeFromSpace($spaceId, $this->member, 'self');

        $this->assertSame('going', Guest::query()->find($seat->id)->status);
    }

    private function createSpace($privacy)
    {
        $this->wpdb->insert($this->table('fcom_spaces'), [
            'title'   => 'Integration ' . $privacy . ' space',
            'slug'    => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'type'    => 'community',
            'privacy' => $privacy,
            'status'  => 'published',
        ]);

        return (int) $this->wpdb->insert_id;
    }

    /**
     * An event posted in the space, linked both ways as FeedHandler::createEvent() leaves it.
     */
    private function spaceEvent($spaceId, $label, array $overrides = [], $authorStatus = 'active')
    {
        $event = $this->createEvent(array_merge(['title' => "{$this->token} {$label}"], $overrides));
        $authorId = $this->createUser('editor');

        $this->wpdb->insert($this->table('fcom_xprofile'), [
            'user_id'  => $authorId,
            'username' => 'fe-test-' . $authorId,
            'status'   => $authorStatus,
        ]);

        $feed = FcomFeed::create([
            'user_id'          => $authorId,
            'title'            => 'Integration event post',
            'slug'             => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'message'          => 'Rolled back.',
            'message_rendered' => '<p>Rolled back.</p>',
            'type'             => 'text',
            'content_type'     => CommunityEvents::CONTENT_TYPE,
            'space_id'         => $spaceId,
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

        return $event;
    }
}
