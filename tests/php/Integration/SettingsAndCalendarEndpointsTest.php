<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

class SettingsAndCalendarEndpointsTest extends IntegrationTestCase
{
    private $pages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pages = $this->createPublicPages();
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
        $settings = ['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes'] + $this->pages + ['event_slug' => 'gatherings'];
        $saved = $this->rest('POST', 'settings/general', $settings);

        $this->assertSame(200, $saved['status'], wp_json_encode($saved['data']));
        $this->assertSame($settings, $this->rest('GET', 'settings/general')['data']['settings']);
    }

    public function test_each_settings_box_saves_only_its_own_fields()
    {
        Settings::update(Settings::GENERAL, ['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes']);

        $this->assertSame(200, $this->rest('POST', 'settings/general', ['events_page_id' => 0, 'event_slug' => 'event'])['status']);
        $this->assertSame(['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes', 'events_page_id' => 0, 'event_slug' => 'event'], Settings::get());

        $this->assertSame(200, $this->rest('POST', 'settings/general', ['default_events_view' => 'calendar', 'disable_event_view_switch' => 'no'])['status']);
        $this->assertSame(0, Settings::get()['events_page_id']);

        // Sent, a view field still has to be valid.
        $this->assertSame(422, $this->rest('POST', 'settings/general', ['default_events_view' => ''])['status']);
    }

    public function test_changing_the_event_slug_drops_the_rewrite_rules()
    {
        update_option('rewrite_rules', ['event/([^/]+)/?$' => 'index.php?fe_event=$matches[1]']);

        $saved = $this->rest('POST', 'settings/general', ['events_page_id' => 0, 'event_slug' => 'Our Gatherings']);

        $this->assertSame(200, $saved['status'], wp_json_encode($saved['data']));
        $this->assertSame('our-gatherings', Settings::get()['event_slug']);
        $this->assertFalse(get_option('rewrite_rules'));
    }

    public function test_saving_the_same_event_slug_keeps_the_rewrite_rules()
    {
        $rules = ['event/([^/]+)/?$' => 'index.php?fe_event=$matches[1]'];
        update_option('rewrite_rules', $rules);

        $this->rest('POST', 'settings/general', ['event_slug' => 'event'] + $this->pages);

        $this->assertSame($rules, get_option('rewrite_rules'));
    }

    public function test_an_event_slug_that_sanitizes_to_nothing_is_refused()
    {
        $response = $this->rest('POST', 'settings/general', ['event_slug' => '!!!']);

        $this->assertSame(422, $response['status']);
        $this->assertSame(['required' => 'Enter an event slug.'], $response['data']['event_slug'], 'One error, ours and translatable.');
        $this->assertSame('event', Settings::get()['event_slug']);
    }

    public function test_only_a_site_administrator_can_change_the_settings()
    {
        $this->actAs($this->createUser('editor'));
        $settings = ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'events_page_id' => 0, 'event_slug' => 'taken'];

        $this->assertSame(403, $this->rest('POST', 'settings/general', $settings)['status']);
        $this->assertSame($this->pages['events_page_id'], Settings::get()['events_page_id']);
    }

    public function test_a_page_setting_takes_only_a_published_page()
    {
        $draft = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Draft']);
        $post = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'A post']);

        foreach ([$draft, $post, 999999999] as $id) {
            $response = $this->rest('POST', 'settings/general', ['default_events_view' => 'cards', 'disable_event_view_switch' => 'no', 'events_page_id' => $id]);

            $this->assertSame(422, $response['status'], (string) $id);
        }

        $this->assertSame($this->pages['events_page_id'], Settings::get()['events_page_id']);
    }

    public function test_each_chosen_page_reports_whether_it_holds_its_shortcode()
    {
        $bare = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Bare', 'post_content' => '<p>No events here.</p>']);
        Settings::update(Settings::GENERAL, ['events_page_id' => $bare]);

        $pages = $this->rest('GET', 'settings/general')['data']['pages'];

        $this->assertSame('Bare', $pages['events_page_id']['title']);
        $this->assertFalse($pages['events_page_id']['has_shortcode']);

        Settings::update(Settings::GENERAL, $this->pages);
        $pages = $this->rest('GET', 'settings/general')['data']['pages'];

        $this->assertTrue($pages['events_page_id']['has_shortcode']);
        $this->assertSame('(no title)', $pages['events_page_id']['title']);
    }

    public function test_create_page_makes_a_titled_page_holding_its_block_and_sets_it()
    {
        $fired = did_action('fluent_event/settings/general_updated');
        $response = $this->rest('POST', 'settings/create-page', ['key' => 'events_page_id', 'title' => 'Our Events']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame($fired + 1, did_action('fluent_event/settings/general_updated'), 'Setting the page through "+" is a settings change too.');
        $id = Settings::get()['events_page_id'];
        $this->assertNotSame($this->pages['events_page_id'], $id);
        $page = get_post($id);
        $this->assertSame('page', $page->post_type);
        $this->assertSame('publish', $page->post_status);
        $this->assertSame('Our Events', $page->post_title);
        $this->assertSame('<!-- wp:fluent-event/events {"align":"wide"} /-->', $page->post_content);
        $this->assertTrue($response['data']['pages']['events_page_id']['has_shortcode']);
    }

    public function test_create_page_refuses_any_other_setting_a_blank_title_and_editors()
    {
        $this->assertSame(422, $this->rest('POST', 'settings/create-page', ['key' => 'default_events_view', 'title' => 'Events'])['status']);
        $blank = $this->rest('POST', 'settings/create-page', ['key' => 'events_page_id', 'title' => ' ']);
        $this->assertSame(422, $blank['status']);
        $this->assertStringContainsString('Enter a page title.', wp_json_encode($blank['data']));

        $this->actAs($this->createUser('editor'));
        $this->assertSame(403, $this->rest('POST', 'settings/create-page', ['key' => 'events_page_id', 'title' => 'Events'])['status']);
        $this->assertSame($this->pages['events_page_id'], Settings::get()['events_page_id']);
    }

    // Each result says whether it holds the field's block, so a picked page's warning is right before it is saved.
    public function test_the_page_search_lists_published_pages_and_whether_each_holds_the_list()
    {
        $title = 'Zebra page ' . wp_generate_password(6, false, false);
        $listed = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<!-- wp:fluent-event/events /-->']);
        $plain = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title . ' plain']);
        wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title . ' draft']);

        $pages = $this->rest('GET', 'settings/pages', ['search' => $title, 'key' => 'events_page_id'])['data']['pages'];

        $this->assertSame([$listed => true, $plain => false], array_column($pages, 'has_shortcode', 'id'));
        $this->assertSame(422, $this->rest('GET', 'settings/pages', ['search' => $title, 'key' => 'default_events_view'])['status']);
    }

    public function test_a_bad_from_address_is_rejected()
    {
        $this->assertSame(422, $this->rest('POST', 'settings/email', ['from_name' => 'Events', 'from_email' => 'not-an-email'])['status']);
    }

    public function test_an_empty_from_address_gets_one_error()
    {
        $response = $this->rest('POST', 'settings/email', ['from_name' => 'Events', 'from_email' => '']);

        $this->assertSame(422, $response['status']);
        $this->assertSame(['required' => 'The from email address is required.'], $response['data']['from_email']);
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

    // The time is not rounded or flipped to fit, and each mistake gets one message, not every rule it breaks.
    public function test_a_bad_reminder_time_is_refused_with_one_message()
    {
        $messages = [
            ''  => 'The guest reminder time is required.',
            '1.5' => 'The reminder time must be a whole number.',
            '-5'  => 'The reminder time must be at least 1.',
        ];

        foreach ([['', ''], [1.5, '1.5'], [-5, '-5']] as [$value, $key]) {
            $response = $this->rest('POST', 'settings/email-notifications', $this->reminders([['value' => $value, 'unit' => 'hours']]));
            $errors = wp_json_encode($response['data']);

            $this->assertSame(422, $response['status'], "value {$key}");

            foreach ($messages as $other => $message) {
                (string) (string) $other === $key
                    ? $this->assertStringContainsString($message, $errors)
                    : $this->assertStringNotContainsString($message, $errors);
            }

            $this->assertStringNotContainsString('between 5 minutes and 30 days', $errors);
        }
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

    // 100 by default; a site may raise it with a filter, never past the VARCHAR(255) column.
    public function test_a_calendar_title_is_capped_at_its_filterable_limit()
    {
        $create = function ($length) {
            return $this->rest('POST', 'calendars', ['title' => str_repeat('a', $length), 'slug' => 'limit-' . $length, 'status' => 'public']);
        };

        $this->assertSame(200, $create(100)['status']);
        $tooLong = $create(101);
        $this->assertSame(422, $tooLong['status']);
        $this->assertStringContainsString('Title cannot exceed 100 characters.', wp_json_encode($tooLong['data']));

        $raise = function () {
            return 1000;
        };
        add_filter('fluent_event/calendar_title_max_length', $raise);

        try {
            $this->assertSame(255, Calendar::titleMaxLength());
            $this->assertSame(200, $create(255)['status']);
        } finally {
            remove_filter('fluent_event/calendar_title_max_length', $raise);
        }
    }

    // A "<" the sanitizer stored as "&lt;" comes back as typed, so the admin shows "<3", not "&lt;3"; tags are still stripped.
    public function test_a_calendar_title_comes_back_as_typed()
    {
        $title = 'Tom & Jerry\'s "Club" <3';
        $created = $this->rest('POST', 'calendars', ['title' => $title . '<b>!</b>', 'slug' => 'tom-jerry', 'status' => 'public']);

        $this->assertSame(200, $created['status'], wp_json_encode($created['data']));
        $this->assertSame($title . '!', $created['data']['calendar']['title']);
        $this->assertSame($title . '!', $this->rest('GET', 'calendars/' . $created['data']['calendar']['id'])['data']['calendar']['title']);
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

    // The event form's calendar picker marks draft and private calendars from this status.
    public function test_the_calendar_picker_lists_every_calendar_with_its_status()
    {
        $public = $this->createCalendar();
        $draft = $this->createCalendar(['status' => 'draft']);
        $private = $this->createCalendar(['status' => 'private']);

        $response = $this->rest('GET', 'calendars/options');
        $statuses = array_column(json_decode(wp_json_encode($response['data']['calendars']), true), 'status', 'id');

        $this->assertSame(200, $response['status']);
        $this->assertSame('public', $statuses[$public->id]);
        $this->assertSame('draft', $statuses[$draft->id]);
        $this->assertSame('private', $statuses[$private->id]);
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

    // The tiles count what the Events tab lists: drafts and an event in progress included.
    public function test_the_calendar_tiles_count_every_event_and_guest_in_it()
    {
        $calendar = $this->createCalendar();
        $upcoming = $this->createEvent(['calendar_id' => $calendar->id]);
        $draft = $this->createEvent(['calendar_id' => $calendar->id, 'status' => 'draft']);
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'start_at'    => gmdate('Y-m-d H:i:s', strtotime('-1 hour')),
            'end_at'      => gmdate('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        $this->createEvent([
            'calendar_id' => $calendar->id,
            'start_at'    => gmdate('Y-m-d H:i:s', strtotime('-3 days')),
            'end_at'      => gmdate('Y-m-d H:i:s', strtotime('-3 days +1 hour')),
        ]);
        $this->createGuest($upcoming);
        $this->createGuest($upcoming, ['status' => 'checked_in']);
        $this->createGuest($draft, ['status' => 'not_going']);

        $stats = $this->rest('GET', "calendars/{$calendar->id}")['data']['stats'];

        $this->assertSame(4, $stats['total_events']['amount']);
        $this->assertSame(3, $stats['upcoming_events']['amount']);
        $this->assertSame(3, $stats['total_guests']['amount']);
        $this->assertSame(1, $stats['checked_in_guests']['amount']);
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
