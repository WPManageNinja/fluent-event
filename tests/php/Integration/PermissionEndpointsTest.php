<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Team keys through real routes and policies: flat read/write keys per module, honoured
 * beside FluentEvent Pro (the team endpoints themselves are Pro's and tested there).
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

    // The event form needs calendar names; the full list and detail carry guest numbers event staff can't read.
    public function test_an_event_writer_can_load_calendar_names_but_not_the_full_calendar_list()
    {
        $calendar = $this->createCalendar();
        $this->actAs($this->createUser('editor', ['write_events']));

        $options = $this->rest('GET', 'calendars/options');

        $this->assertSame(200, $options['status'], wp_json_encode($options['data']));
        $this->assertContains((int) $calendar->id, array_map('intval', array_column($options['data']['calendars'], 'id')));
        $this->assertSame(403, $this->rest('GET', 'calendars')['status']);
        $this->assertSame(403, $this->rest('GET', "calendars/{$calendar->id}")['status']);
    }

    public function test_a_guest_reader_can_list_guests_but_not_add_them_or_see_the_event_list()
    {
        $event = $this->createEvent();
        $this->actAs($this->createUser('editor', ['read_guests']));

        $this->assertSame(200, $this->rest('GET', "events/{$event->id}/guests")['status']);
        $this->assertSame(403, $this->rest('POST', "events/{$event->id}/guests", ['name' => 'X', 'email' => 'x@example.test'])['status']);
        $this->assertSame(403, $this->rest('GET', 'events')['status']);
    }

    public function test_a_settings_writer_saves_settings_but_not_the_public_pages_or_event_slug()
    {
        $this->actAs($this->createUser('editor', ['read_settings', 'write_settings']));
        $view = ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no'];

        $this->assertSame(200, $this->rest('POST', 'settings/general', $view)['status']);
        $this->assertSame(403, $this->rest('POST', 'settings/general', $view + ['event_slug' => 'gatherings'])['status']);
        $this->assertSame(403, $this->rest('POST', 'settings/create-page', ['key' => 'events_page_id', 'title' => 'Events'])['status']);
        $this->assertSame('event', Settings::get()['event_slug']);
    }
}
