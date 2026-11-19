<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The event endpoints end to end. The post and its event_details row are one
 * record split across two tables, so every write must keep them in step.
 */
class EventEndpointsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actAs($this->createAdmin());
    }

    private function recordDeleteHooks()
    {
        $fired = (object) ['hooks' => []];

        foreach (['fluent_event/event/before_deleted', 'fluent_event/event/deleted'] as $hook) {
            $this->addTestFilter($hook, function ($event) use ($hook, $fired) {
                $fired->hooks[] = $hook;

                return $event;
            });
        }

        return $fired;
    }

    public function test_creating_an_event_writes_its_post_and_details_together()
    {
        $payload = $this->eventPayload($this->createEvent(), ['title' => 'Launch', 'status' => 'private']);

        $response = $this->rest('POST', 'events', $payload);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $event = Event::query()->find($response['data']['event']['id']);
        $this->assertSame('Launch', get_post($event->post_id)->post_title);
        $this->assertSame('private', $event->status);
    }

    public function test_an_event_carries_its_own_public_address()
    {
        $event = $this->createEvent();

        $url = $this->rest('GET', "events/{$event->id}")['data']['public_url'];

        $this->assertSame(get_permalink($event->post_id), $url);
        $this->assertStringContainsString('/event/' . get_post($event->post_id)->post_name, $url);
    }

    public function test_an_event_carries_the_shortcode_that_embeds_it()
    {
        $event = $this->createEvent();

        $shortcode = $this->rest('GET', "events/{$event->id}")['data']['shortcode'];

        $this->assertSame('[fluent_event event="' . get_post($event->post_id)->post_name . '"]', $shortcode);
    }

    public function test_an_event_loads_by_its_slug_as_it_does_by_its_id()
    {
        $event = $this->createEvent();
        $slug = get_post($event->post_id)->post_name;

        $byId = $this->rest('GET', "events/{$event->id}")['data'];
        $bySlug = $this->rest('GET', 'events/by-slug', ['slug' => $slug]);

        $this->assertSame(200, $bySlug['status'], wp_json_encode($bySlug['data']));
        $this->assertEquals($byId, $bySlug['data']);

        $this->assertSame(404, $this->rest('GET', 'events/by-slug', ['slug' => $slug . '-missing'])['status']);
        $this->assertSame(404, $this->rest('GET', 'events/by-slug')['status']);
    }

    public function test_updating_an_event_keeps_its_post_in_step()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['title' => 'Renamed', 'status' => 'private']));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $post = get_post($event->post_id);
        $this->assertSame('Renamed', $post->post_title);
        $this->assertSame('private', $post->post_status);
        $this->assertSame('private', Event::query()->find($event->id)->status);
    }

    public function test_an_event_saves_each_entry_type_and_refuses_any_other()
    {
        $event = $this->createEvent();

        foreach (['open', 'none', 'rsvp'] as $type) {
            $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['entry_type' => $type]));

            $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
            $this->assertSame($type, Event::query()->find($event->id)->meta['entry_type']);
        }

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['entry_type' => 'ticketed']));

        $this->assertSame(422, $response['status']);
    }

    public function test_deleting_a_wordpress_user_keeps_the_events_they_created()
    {
        require_once ABSPATH . 'wp-admin/includes/user.php';

        $authorId = $this->createUser('editor');
        $this->actAs($authorId);
        $event = $this->createEvent();
        $this->actAs($this->createAdmin());

        // No reassign: WordPress's "Delete all content" choice.
        wp_delete_user($authorId);

        $this->assertNotNull(get_post($event->post_id));
    }

    public function test_deleting_an_event_post_outside_the_plugin_deletes_the_event()
    {
        $event = $this->createEvent();
        $this->createGuest($event);
        Meta::query()->create(['object_type' => Meta::OBJECT_TYPE_EVENT, 'object_id' => $event->id, 'meta_key' => 'probe', 'value' => []]);
        $fired = $this->recordDeleteHooks();

        wp_delete_post($event->post_id, true);

        $this->assertNull(Event::query()->find($event->id));
        $this->assertSame(0, Guest::query()->where('event_id', $event->id)->count());
        $this->assertSame(0, Meta::query()->where('object_type', Meta::OBJECT_TYPE_EVENT)->where('object_id', $event->id)->count());
        $this->assertSame(['fluent_event/event/before_deleted', 'fluent_event/event/deleted'], $fired->hooks);
    }

    // The plugin's own delete removes the post too, which must not run the cleanup a second time.
    public function test_deleting_an_event_fires_each_delete_hook_once()
    {
        $event = $this->createEvent();
        $fired = $this->recordDeleteHooks();

        $this->assertSame(200, $this->rest('DELETE', "events/{$event->id}")['status']);
        $this->assertSame(['fluent_event/event/before_deleted', 'fluent_event/event/deleted'], $fired->hooks);
    }

    // Deleting the post now deletes the event and its guests, so only an admin may delete or edit it.
    public function test_only_an_admin_may_edit_or_delete_an_event_post()
    {
        $event = $this->createEvent();
        $editor = $this->createUser('editor');

        $this->assertFalse(user_can($editor, 'delete_post', $event->post_id));
        $this->assertFalse(user_can($editor, 'edit_post', $event->post_id));
        $this->assertTrue(user_can($this->createAdmin(), 'delete_post', $event->post_id));
    }

    // A post row removed straight from the database fires no hook, and must not leave an undeletable event.
    public function test_an_event_whose_post_is_already_gone_can_still_be_deleted()
    {
        $event = $this->createEvent();
        $guest = $this->createGuest($event);
        $this->wpdb->delete($this->wpdb->posts, ['ID' => $event->post_id]);
        clean_post_cache($event->post_id);

        $response = $this->rest('DELETE', "events/{$event->id}");

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Event::query()->find($event->id));
        $this->assertNull(Guest::query()->find($guest->id));
    }

    public function test_a_calendar_holding_an_event_whose_post_is_gone_can_still_be_deleted()
    {
        $calendar = $this->createCalendar();
        $orphan = $this->createEvent(['calendar_id' => $calendar->id]);
        $sibling = $this->createEvent(['calendar_id' => $calendar->id]);
        $this->wpdb->delete($this->wpdb->posts, ['ID' => $orphan->post_id]);
        clean_post_cache($orphan->post_id);

        $response = $this->rest('DELETE', "calendars/{$calendar->id}");

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Event::query()->find($orphan->id));
        $this->assertNull(Event::query()->find($sibling->id));
    }

    public function test_backslashes_in_a_title_and_description_survive_saving()
    {
        $event = $this->createEvent(['title' => 'AC\DC Live']);
        $this->assertSame('AC\DC Live', get_post($event->post_id)->post_title);

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, [
            'title'       => 'AC\DC Live',
            'description_md' => 'Slides in C:\Reports',
        ]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $post = get_post($event->post_id);
        $this->assertSame('AC\DC Live', $post->post_title);
        $this->assertStringContainsString('C:\Reports', $post->post_content);
    }

    // Without unfiltered_html, WordPress stores "&" in a post title as "&amp;".
    public function test_an_ampersand_in_a_title_reads_back_as_typed_for_an_admin_without_unfiltered_html()
    {
        $event = $this->createEvent();
        $this->actAs($this->adminWithoutUnfilteredHtml());

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['title' => 'Wine & Cheese']));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('Wine & Cheese', $this->rest('GET', "events/{$event->id}")['data']['event']['title']);
    }

    /**
     * sanitize_text_field() stores a lone "<" as "&lt;".
     *
     * @dataProvider titlesWithLessThan
     */
    public function test_a_less_than_sign_in_a_title_reads_back_as_typed($title)
    {
        $event = $this->createEvent();
        $this->actAs($this->adminWithoutUnfilteredHtml());

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['title' => $title]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame($title, $this->rest('GET', "events/{$event->id}")['data']['event']['title']);
    }

    /**
     * A link sent as a list must be rejected, not saved as http://Array.
     *
     * @dataProvider linkFields
     */
    public function test_a_link_sent_as_a_list_is_rejected($field, $venueType)
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, [
            'venue_type' => $venueType,
            $field       => ['https://example.com/a.jpg'],
        ]));

        $this->assertSame(422, $response['status'], wp_json_encode($response['data']));
        $this->assertArrayHasKey($field, $response['data']);
        $this->assertSame('', Event::query()->find($event->id)->meta[$field] ?? '');
    }

    public static function linkFields()
    {
        return [
            'cover image'  => ['cover_url', 'offline'],
            'meeting link' => ['online_meeting_url', 'online'],
        ];
    }

    // A multisite admin, or a site with DISALLOW_UNFILTERED_HTML, works without unfiltered_html.
    private function adminWithoutUnfilteredHtml()
    {
        $userId = $this->createAdmin();
        (new \WP_User($userId))->add_cap('unfiltered_html', false);

        return $userId;
    }

    public function titlesWithLessThan()
    {
        return [
            'age limit'      => ['Kids <5'],
            'less or equal'  => ['Ages 5 <= 12'],
            'mixed entities' => ['"Q&A" <3'],
        ];
    }

    public function test_a_local_start_time_is_stored_in_utc()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, [
            'timezone' => 'Asia/Dhaka',
            'start_at' => '2030-01-10 10:00:00',
            'end_at'   => '2030-01-10 12:00:00',
        ]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $fresh = Event::query()->find($event->id);
        $this->assertSame('2030-01-10 04:00:00', $fresh->start_at);
        $this->assertSame('2030-01-10 10:00:00', $fresh->start_at_local);
    }

    public function test_an_event_that_ends_before_it_starts_is_rejected_and_nothing_changes()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, [
            'title'    => 'Should not stick',
            'start_at' => '2030-01-10 12:00:00',
            'end_at'   => '2030-01-10 10:00:00',
        ]));

        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString('end_at', wp_json_encode($response['data']));
        $this->assertSame('Integration event', get_post($event->post_id)->post_title);
    }

    public function test_a_start_that_is_not_a_date_gets_a_readable_message()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['start_at' => 'next-ish']));

        $this->assertSame(422, $response['status']);
        $this->assertSame(['date' => 'Start must be a valid date and time.'], $response['data']['start_at']);
    }

    public function test_an_online_event_without_a_meeting_link_is_rejected()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['venue_type' => 'online', 'online_meeting_url' => '']));

        $this->assertSame(422, $response['status']);
        $this->assertSame(['required'], array_keys($response['data']['online_meeting_url']));
    }

    public function test_an_event_cannot_be_moved_into_a_calendar_that_does_not_exist()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['calendar_id' => 999999999]));

        $this->assertSame(422, $response['status']);
    }

    // Saved with the event and kept when a form that doesn't know it (the community one) saves again.
    public function test_the_start_display_is_saved_and_only_takes_its_two_choices()
    {
        $event = $this->createEvent();

        $this->assertSame('relative', $this->rest('GET', "events/{$event->id}")['data']['event']['meta']['start_display']);

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['start_display' => 'countdown']));
        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['title' => 'Renamed']));
        $this->assertSame('countdown', Event::find($event->id)->meta['start_display']);

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['start_display' => 'flip']));
        $this->assertSame(422, $response['status']);
        $this->assertSame(['in'], array_keys($response['data']['start_display']));
    }

    public function test_script_in_an_event_description_is_saved_as_text()
    {
        $event = $this->createEvent();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, [
            'description_md' => "Bring snacks\n\n<script>alert(1)</script>",
        ]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

        $content = get_post($event->post_id)->post_content;
        $this->assertStringContainsString('<p>Bring snacks</p>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString('<script', $content);
    }

    public function test_moving_an_event_to_another_calendar_moves_its_guests()
    {
        $event = $this->createEvent();
        $guest = $this->createGuest($event);
        $target = $this->createCalendar();

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['calendar_id' => $target->id]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame((int) $target->id, (int) Guest::query()->find($guest->id)->calendar_id);
    }

    public function test_deleting_an_event_removes_its_post_guests_and_meta()
    {
        $event = $this->createEvent();
        $this->createGuest($event);
        Meta::query()->create(['object_type' => Meta::OBJECT_TYPE_EVENT, 'object_id' => $event->id, 'meta_key' => 'probe', 'value' => []]);

        $response = $this->rest('DELETE', "events/{$event->id}");

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertNull(Event::query()->find($event->id));
        $this->assertNull(get_post($event->post_id));
        $this->assertSame(0, Guest::query()->where('event_id', $event->id)->count());
        $this->assertSame(0, Meta::query()->where('object_type', Meta::OBJECT_TYPE_EVENT)->where('object_id', $event->id)->count());
    }

    public function test_a_missing_event_is_a_404()
    {
        $this->assertSame(404, $this->rest('GET', 'events/999999999')['status']);
    }

    // Only a calendar-sized window skips paging; a missing or wider window comes back a page at a time.
    public function test_the_event_list_pages_unless_asked_for_a_short_window()
    {
        $calendarId = $this->createCalendar()->id;
        foreach (range(1, 3) as $i) {
            $this->createEvent(['calendar_id' => $calendarId]);
        }

        $query = ['calendar_id' => $calendarId, 'paginate' => 'false', 'per_page' => 2];
        $unbounded = $this->rest('GET', 'events', $query)['data']['events'];
        $wide = $this->rest('GET', 'events', $query + ['start_date' => '2020-01-01', 'end_date' => '2035-12-31'])['data']['events'];
        $reversed = $this->rest('GET', 'events', $query + ['start_date' => '2035-12-31', 'end_date' => '2020-01-01'])['data']['events'];

        $this->assertSame(3, (int) $unbounded['total']);
        $this->assertCount(2, $unbounded['data']);
        $this->assertSame(3, (int) $wide['total']);
        $this->assertCount(2, $wide['data']);
        $this->assertArrayHasKey('total', $reversed);
    }

    // Only the event's page and edit form show the description; lists skip the wp_kses_post it costs per event.
    public function test_only_the_single_event_response_carries_the_description()
    {
        $event = $this->createEvent();

        $listed = array_column($this->rest('GET', 'events', ['calendar_id' => $event->calendar_id])['data']['events']['data'], null, 'id');
        $single = $this->rest('GET', "events/{$event->id}")['data']['event'];

        $this->assertArrayNotHasKey('description', $listed[$event->id]);
        $this->assertSame('<p>Written by the integration suite; rolled back.</p>', $single['description']);
    }

    // "Clone Event" links to ?clone=<slug>; the post relation is hidden from JSON, so the slug must ride on the event.
    public function test_the_single_event_response_carries_the_slug_for_cloning()
    {
        $event = $this->createEvent();

        $single = $this->rest('GET', "events/{$event->id}")['data']['event'];

        $this->assertSame($event->post->post_name, $single['slug']);
        $this->assertArrayNotHasKey('post', $single);
    }

    // The cards' "N guests": every guest counts, not going and cancelled included.
    public function test_the_event_list_counts_all_guests_of_each_event()
    {
        $event = $this->createEvent();
        $empty = $this->createEvent(['calendar_id' => $event->calendar_id]);

        foreach (['going', 'going', 'checked_in', 'not_going', 'cancelled'] as $status) {
            $this->createGuest($event, ['status' => $status]);
        }

        $listed = array_column($this->rest('GET', 'events', ['calendar_id' => $event->calendar_id])['data']['events']['data'], 'guests_count', 'id');

        $this->assertSame(5, (int) $listed[$event->id]);
        $this->assertSame(0, (int) $listed[$empty->id]);
    }

    // The Overview's "N guests" comes with the event, counted as on the list cards.
    public function test_a_single_event_counts_all_its_guests()
    {
        $event = $this->createEvent();
        $empty = $this->createEvent(['calendar_id' => $event->calendar_id]);

        foreach (['going', 'checked_in', 'not_going', 'cancelled'] as $status) {
            $this->createGuest($event, ['status' => $status]);
        }

        $this->assertSame(4, (int) $this->rest('GET', "events/{$event->id}")['data']['event']['guests_count']);
        $this->assertSame(0, (int) $this->rest('GET', "events/{$empty->id}")['data']['event']['guests_count']);
    }

    public function test_the_event_list_filters_by_calendar()
    {
        $mine = $this->createEvent();
        $this->createEvent();

        $response = $this->rest('GET', 'events', ['calendar_id' => $mine->calendar_id]);

        $this->assertSame(200, $response['status']);
        $this->assertSame([(int) $mine->id], array_map('intval', array_column($response['data']['events']['data'], 'id')));
    }

    // Lists show every event on the site's clock, so a filtered day is a site day, whatever the event's own zone.
    public function test_the_event_list_date_filter_uses_the_sites_days()
    {
        $this->addTestFilter('pre_option_timezone_string', function () {
            return 'Asia/Dhaka';
        });
        $calendarId = $this->createCalendar()->id;
        // 03:00 Oct 20 in Dhaka.
        $lateNight = $this->createEvent(['calendar_id' => $calendarId, 'timezone' => 'Europe/London', 'start_at' => '2030-10-19 22:00:00', 'end_at' => '2030-10-19 23:00:00']);
        // 02:00 Oct 26 in Dhaka.
        $this->createEvent(['calendar_id' => $calendarId, 'timezone' => 'Europe/London', 'start_at' => '2030-10-25 21:00:00', 'end_at' => '2030-10-25 22:00:00']);

        $filter = function ($from, $to) use ($calendarId) {
            $response = $this->rest('GET', 'events', ['calendar_id' => $calendarId, 'start_date' => $from, 'end_date' => $to, 'paginate' => 'false']);
            $this->assertSame(200, $response['status'], wp_json_encode($response['data']));

            return array_map('intval', array_column($response['data']['events'], 'id'));
        };

        $this->assertSame([(int) $lateNight->id], $filter('2030-10-20', '2030-10-25'));
        $this->assertSame([], $filter('2030-10-18', '2030-10-19'));
    }

    public function test_an_event_reads_on_both_its_own_and_the_sites_clock()
    {
        $this->addTestFilter('pre_option_timezone_string', function () {
            return 'Asia/Dhaka';
        });
        $event = $this->createEvent(['timezone' => 'Europe/London', 'start_at' => '2030-10-19 22:00:00', 'end_at' => '2030-10-19 23:00:00']);

        $data = $this->rest('GET', "events/{$event->id}")['data']['event'];

        $this->assertSame('2030-10-19 22:00:00', $data['start_at_local']);
        $this->assertSame('2030-10-20 03:00:00', $data['start_at_site']);
        $this->assertSame('2030-10-20 04:00:00', $data['end_at_site']);
    }

    public function test_events_starting_at_the_same_time_each_appear_on_exactly_one_page()
    {
        $calendarId = $this->createCalendar()->id;
        $ids = [];

        for ($i = 0; $i < 12; $i++) {
            $ids[] = (int) $this->createEvent(['calendar_id' => $calendarId, 'start_at' => '2030-05-01 09:00:00', 'end_at' => '2030-05-01 10:00:00'])->id;
        }

        foreach (['upcoming', 'past', ''] as $period) {
            if ($period === 'past') {
                Event::query()->whereIn('id', $ids)->update(['start_at' => '2020-05-01 09:00:00', 'end_at' => '2020-05-01 10:00:00']);
            }

            $expected = $period === 'past' ? array_reverse($ids) : $ids;

            // Unfiltered, as the list first loads: MySQL reorders equal keys only for some LIMIT/OFFSET pairs.
            foreach ([3, 5, 7] as $perPage) {
                $listed = [];
                $pages = 1;

                for ($page = 1; $page <= $pages; $page++) {
                    $events = $this->rest('GET', 'events', ['period' => $period, 'per_page' => $perPage, 'page' => $page])['data']['events'];
                    $pages = (int) ceil($events['total'] / $perPage);
                    $listed = array_merge($listed, array_map('intval', array_column($events['data'], 'id')));
                }

                $this->assertSame($expected, array_values(array_intersect($listed, $ids)), "period '{$period}', {$perPage} per page");
            }
        }
    }
}
