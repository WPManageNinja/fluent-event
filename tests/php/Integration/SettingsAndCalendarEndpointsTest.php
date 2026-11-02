<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

class SettingsAndCalendarEndpointsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actAs($this->createAdmin());
    }

    private function reminders(array $times)
    {
        return [
            'registration_confirmation' => ['enabled' => true],
            'registration_cancellation' => ['enabled' => true],
            'reminder_to_guest'         => ['enabled' => true, 'times' => $times],
        ];
    }

    public function test_general_settings_round_trip()
    {
        $saved = $this->rest('POST', 'settings/general', ['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes']);

        $this->assertSame(200, $saved['status'], wp_json_encode($saved['data']));
        $this->assertSame(
            ['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes'],
            $this->rest('GET', 'settings/general')['data']['settings']
        );
    }

    public function test_a_bad_from_address_is_rejected()
    {
        $this->assertSame(422, $this->rest('POST', 'settings/email', ['from_name' => 'Events', 'from_email' => 'not-an-email'])['status']);
    }

    public function test_email_settings_are_sanitised_before_they_are_stored()
    {
        $response = $this->rest('POST', 'settings/email', [
            'from_name'    => '<b>Events</b> Team',
            'from_email'   => 'events@example.test',
            'email_footer' => '<p>Bye</p><script>alert(1)</script>',
        ]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $stored = Settings::get(Settings::EMAIL);
        $this->assertSame('Events Team', $stored['from_name']);
        $this->assertStringNotContainsString('<script', $stored['email_footer']);
    }

    public function test_a_reminder_closer_than_five_minutes_is_rejected()
    {
        $this->assertSame(422, $this->rest('POST', 'settings/email-notifications', $this->reminders([['value' => 4, 'unit' => 'minutes']]))['status']);
    }

    public function test_more_than_five_reminders_are_rejected()
    {
        $times = array_fill(0, 6, ['value' => 1, 'unit' => 'hours']);

        $this->assertSame(422, $this->rest('POST', 'settings/email-notifications', $this->reminders($times))['status']);
    }

    public function test_saving_reminder_settings_requeues_every_upcoming_event()
    {
        $response = $this->rest('POST', 'settings/email-notifications', $this->reminders([['value' => 2, 'unit' => 'hours']]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertTrue($this->hasQueuedAction('fluent_event/reschedule_event_reminders', [0]));
    }

    public function test_a_calendar_can_be_created_without_a_space()
    {
        $response = $this->rest('POST', 'calendars', [
            'title'       => 'Team Meetups',
            'slug'        => 'team-meetups',
            'status'      => 'public',
            'theme_color' => '#0099ff',
        ]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $calendar = Calendar::query()->find($response['data']['calendar']['id']);

        $this->assertSame('team-meetups', $calendar->slug);
        $this->assertSame('#0099ff', $calendar->meta['theme_color']);
    }

    public function test_a_calendar_description_comes_back_without_scripts()
    {
        $created = $this->rest('POST', 'calendars', [
            'title'       => 'Book Club',
            'slug'        => 'book-club',
            'status'      => 'public',
            'description' => '<p>Monthly</p><script>alert(1)</script>',
        ]);

        $this->assertSame(200, $created['status'], wp_json_encode($created['data']));

        $description = $this->rest('GET', "calendars/{$created['data']['calendar']['id']}")['data']['calendar']['description'];

        $this->assertStringContainsString('<p>Monthly</p>', $description);
        $this->assertStringNotContainsString('<script', $description);
    }

    public function test_a_calendar_slug_already_in_use_is_rejected()
    {
        $taken = $this->createCalendar();
        $calendar = $this->createCalendar();

        $response = $this->rest('PATCH', "calendars/{$calendar->id}", ['title' => 'Mine', 'slug' => $taken->slug, 'status' => 'public']);

        $this->assertSame(422, $response['status']);
    }

    public function test_a_calendar_keeps_its_theme_colour_in_meta()
    {
        $calendar = $this->createCalendar();

        $response = $this->rest('PATCH', "calendars/{$calendar->id}", [
            'title'       => 'Renamed',
            'slug'        => $calendar->slug,
            'status'      => 'private',
            'theme_color' => '#123456',
        ]);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('#123456', Calendar::query()->find($calendar->id)->meta['theme_color']);
    }

    public function test_known_issue_177_an_invalid_theme_colour_is_rejected()
    {
        $calendar = $this->createCalendar(['meta' => ['theme_color' => '#abcdef']]);

        $response = $this->rest('PATCH', "calendars/{$calendar->id}", ['title' => 'X', 'slug' => $calendar->slug, 'status' => 'public', 'theme_color' => 'red']);

        $this->assertContains($response['status'], [200, 422], wp_json_encode($response['data']));
        $this->assertSame('#abcdef', Calendar::query()->find($calendar->id)->meta['theme_color'], 'An invalid colour must never be stored');

        // #177: sanitize_hex_color() nulls 'red' before validation, so the regex rule never runs and the save reports success.
        if ($response['status'] === 422) {
            $this->fail('#177 is fixed: assert the 422 directly, remove this guard and the known_issue_177 name.');
        }

        $this->markTestIncomplete('#177: an invalid theme colour is saved as success instead of 422.');
    }

    public function test_deleting_a_calendar_deletes_its_events_their_posts_and_guests()
    {
        $calendar = $this->createCalendar();
        $event = $this->createEvent(['calendar_id' => $calendar->id]);
        $this->createGuest($event);

        $response = $this->rest('DELETE', "calendars/{$calendar->id}");

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Calendar::query()->find($calendar->id));
        $this->assertNull(Event::query()->find($event->id));
        $this->assertNull(get_post($event->post_id));
        $this->assertSame(0, Guest::query()->where('event_id', $event->id)->count());
    }

    public function test_calendar_stats_hide_guest_numbers_from_an_events_only_reader()
    {
        $calendar = $this->createCalendar();
        $this->actAs($this->createUser('editor', ['read_calendars', 'read_events']));

        $response = $this->rest('GET', "calendars/{$calendar->id}");

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertArrayHasKey('total_events', $response['data']['stats']);
        $this->assertArrayNotHasKey('total_guests', $response['data']['stats']);
        $this->assertArrayNotHasKey('checked_in_guests', $response['data']['stats']);
    }
}
