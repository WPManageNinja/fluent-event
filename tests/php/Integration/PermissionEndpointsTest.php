<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Permissions through real routes and policies: flat read/write keys per module,
 * and manage_all_data as the superkey that also gates the team tab.
 */
class PermissionEndpointsTest extends IntegrationTestCase
{
    public function test_a_logged_out_visitor_is_refused()
    {
        $this->actAs(0);

        $this->assertContains($this->rest('GET', 'events')['status'], [401, 403]);
    }

    public function test_a_wordpress_editor_with_no_keys_is_refused()
    {
        $this->actAs($this->createUser('editor'));

        $this->assertSame(403, $this->rest('GET', 'events')['status']);
        $this->assertSame(403, $this->rest('GET', 'dashboard')['status']);
    }

    public function test_an_event_reader_can_list_events_but_not_change_them()
    {
        $event = $this->createEvent();
        $this->actAs($this->createUser('editor', ['read_events']));

        $this->assertSame(200, $this->rest('GET', 'events')['status']);
        $this->assertSame(403, $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['title' => 'Nope']))['status']);
        $this->assertSame(403, $this->rest('DELETE', "events/{$event->id}")['status']);
        $this->assertSame('Integration event', get_post($event->post_id)->post_title);
    }

    public function test_a_guest_reader_can_list_guests_but_not_add_them_or_see_the_event_list()
    {
        $event = $this->createEvent();
        $this->actAs($this->createUser('editor', ['read_guests']));

        $this->assertSame(200, $this->rest('GET', "events/{$event->id}/guests")['status']);
        $this->assertSame(403, $this->rest('POST', "events/{$event->id}/guests", ['name' => 'X', 'email' => 'x@example.test'])['status']);
        $this->assertSame(403, $this->rest('GET', 'events')['status']);
    }

    public function test_a_settings_writer_cannot_open_the_team_tab()
    {
        $this->actAs($this->createUser('editor', ['read_settings', 'write_settings']));

        $this->assertSame(200, $this->rest('GET', 'settings/general')['status']);
        $this->assertSame(403, $this->rest('GET', 'settings/team')['status']);
    }

    public function test_the_superkey_opens_the_team_tab()
    {
        $this->actAs($this->createUser('editor', [PermissionManager::ALL_DATA]));

        $this->assertSame(200, $this->rest('GET', 'settings/team')['status']);
    }

    public function test_administrators_cannot_be_added_to_the_team()
    {
        $this->actAs($this->createAdmin());

        $response = $this->rest('POST', 'settings/team', ['user_id' => $this->createAdmin(), 'permissions' => ['read_events']]);

        $this->assertSame(422, $response['status']);
    }

    public function test_only_staff_roles_can_join_the_team()
    {
        $this->actAs($this->createAdmin());

        $response = $this->rest('POST', 'settings/team', ['user_id' => $this->createUser('subscriber'), 'permissions' => ['read_events']]);

        $this->assertSame(422, $response['status']);
    }

    public function test_an_unknown_permission_key_is_refused()
    {
        $this->actAs($this->createAdmin());

        $response = $this->rest('POST', 'settings/team', ['user_id' => $this->createUser('editor'), 'permissions' => ['read_events', 'delete_everything']]);

        $this->assertSame(422, $response['status']);
    }

    public function test_granting_the_superkey_stores_it_alone()
    {
        $this->actAs($this->createAdmin());
        $member = $this->createUser('author');

        $response = $this->rest('POST', 'settings/team', ['user_id' => $member, 'permissions' => ['read_events', PermissionManager::ALL_DATA]]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $stored = Meta::query()->ofUser($member)->byKey(PermissionManager::PERMISSION_META_KEY)->first();
        $this->assertSame([PermissionManager::ALL_DATA], $stored->value);
    }

    public function test_removing_a_member_revokes_their_access()
    {
        $member = $this->createUser('editor', ['read_events']);

        $this->actAs($this->createAdmin());
        $this->assertSame(200, $this->rest('DELETE', "settings/team/{$member}")['status']);

        $this->actAs($member);
        $this->assertSame(403, $this->rest('GET', 'events')['status']);
    }
}
