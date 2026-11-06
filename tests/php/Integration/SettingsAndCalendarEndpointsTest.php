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

        // The suite runs on the site's real options; a switch flipped in the browser would read as a toggle here.
        Settings::update(Settings::GENERAL, ['portal_slug' => 'calendars', 'enable_landing_pages' => 'no']);
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
        $settings = ['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes', 'enable_landing_pages' => 'no', 'portal_slug' => 'calendars'];
        $saved = $this->rest('POST', 'settings/general', $settings);

        $this->assertSame(200, $saved['status'], wp_json_encode($saved['data']));
        $this->assertSame($settings, $this->rest('GET', 'settings/general')['data']['settings']);
    }

    public function test_changing_the_portal_slug_sanitises_it_and_drops_the_rewrite_rules()
    {
        Settings::update(Settings::GENERAL, ['portal_slug' => 'calendars']);
        update_option('rewrite_rules', ['^calendars/?$' => 'index.php?fe_route=calendars']);

        $saved = $this->rest('POST', 'settings/general', ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => 'no', 'portal_slug' => 'Our Events']);

        $this->assertSame(200, $saved['status'], wp_json_encode($saved['data']));
        $this->assertSame('our-events', Settings::portalSlug());
        $this->assertFalse(get_option('rewrite_rules'));
    }

    public function test_saving_the_same_portal_slug_keeps_the_rewrite_rules()
    {
        $rules = ['^calendars/?$' => 'index.php?fe_route=calendars'];
        Settings::update(Settings::GENERAL, ['portal_slug' => 'calendars']);
        update_option('rewrite_rules', $rules);

        $this->rest('POST', 'settings/general', ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => 'no', 'portal_slug' => 'calendars']);

        $this->assertSame($rules, get_option('rewrite_rules'));
    }

    public function test_only_a_site_administrator_can_change_the_settings()
    {
        Settings::update(Settings::GENERAL, ['portal_slug' => 'calendars']);
        $this->actAs($this->createUser('editor'));
        $settings = ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => 'no', 'portal_slug' => 'our-events'];

        $this->assertSame(403, $this->rest('POST', 'settings/general', $settings)['status']);
        $this->assertSame('calendars', Settings::portalSlug());
    }

    public function test_a_portal_slug_with_no_ascii_characters_is_rejected()
    {
        foreach (['!!!', 'ক্যালেন্ডার'] as $slug) {
            $response = $this->rest('POST', 'settings/general', ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => 'no', 'portal_slug' => $slug]);

            $this->assertSame(422, $response['status'], $slug);
            $this->assertContains('The portal slug can only use letters, numbers, hyphens and underscores.', $response['data']['portal_slug'] ?? [], $slug);
        }
    }

    public function test_a_blank_portal_slug_is_accepted()
    {
        $response = $this->rest('POST', 'settings/general', ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'enable_landing_pages' => 'yes', 'portal_slug' => '']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
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

    // The column stays in the schema, but nothing writes it: the sanitizer keeps unknown keys, so $fillable is the guard.
    public function test_a_calendar_description_sent_to_the_api_is_not_saved()
    {
        $created = $this->rest('POST', 'calendars', [
            'title'       => 'Book Club',
            'slug'        => 'book-club',
            'status'      => 'public',
            'description' => '<script>alert(1)</script>',
        ]);

        $this->assertSame(200, $created['status'], wp_json_encode($created['data']));
        $id = $created['data']['calendar']['id'];

        $this->rest('PATCH', "calendars/{$id}", [
            'title'       => 'Book Club',
            'slug'        => 'book-club',
            'status'      => 'public',
            'description' => '<script>alert(2)</script>',
        ]);

        $this->assertNull(Calendar::query()->find($id)->description);
    }

    public function test_the_status_filter_narrows_the_list_but_not_the_tab_counts()
    {
        $private = $this->createCalendar(['status' => 'private']);
        $public = $this->createCalendar(['status' => 'public']);

        $data = $this->rest('GET', 'calendars', ['status' => 'private'])['data'];
        $ids = array_map('intval', array_column($data['calendars'], 'id'));

        $this->assertContains((int) $private->id, $ids);
        $this->assertNotContains((int) $public->id, $ids);
        $this->assertGreaterThanOrEqual(1, $data['counts']['public']);
    }

    // Unlisted is an event status only: a calendar has no page of its own to share by link.
    public function test_a_calendar_cannot_be_unlisted()
    {
        $calendar = $this->createCalendar();

        $response = $this->rest('PATCH', "calendars/{$calendar->id}", ['title' => 'Link only', 'slug' => $calendar->slug, 'status' => 'unlisted']);

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('public', Calendar::query()->find($calendar->id)->status);
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

    public function test_an_invalid_theme_colour_is_rejected_on_update_and_create()
    {
        $calendar = $this->createCalendar(['meta' => ['theme_color' => '#abcdef']]);

        $response = $this->rest('PATCH', "calendars/{$calendar->id}", ['title' => 'X', 'slug' => $calendar->slug, 'status' => 'public', 'theme_color' => 'red']);

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('#abcdef', Calendar::query()->find($calendar->id)->meta['theme_color']);

        $response = $this->rest('POST', 'calendars', ['title' => 'Red', 'slug' => 'fe-test-red-colour', 'status' => 'public', 'theme_color' => 'red']);

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertFalse(Calendar::query()->where('slug', 'fe-test-red-colour')->exists());
    }

    public function test_a_three_digit_theme_colour_is_rejected()
    {
        $calendar = $this->createCalendar(['meta' => ['theme_color' => '#abcdef']]);

        $response = $this->rest('PATCH', "calendars/{$calendar->id}", ['title' => 'X', 'slug' => $calendar->slug, 'status' => 'public', 'theme_color' => '#ff0']);

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('#abcdef', Calendar::query()->find($calendar->id)->meta['theme_color']);
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
}
