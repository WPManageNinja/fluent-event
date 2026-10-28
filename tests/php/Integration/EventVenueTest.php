<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\VenueService;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * An event picks a saved venue: it links to the term and keeps a copy of the venue's fields,
 * so later edits reach it and a deleted venue leaves it a one-off.
 */
class EventVenueTest extends IntegrationTestCase
{
    private $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actAs($this->createAdmin());

        $this->venue = $this->rest('POST', 'venues', [
            'name'     => 'Linked Test Hall ' . wp_generate_password(6, false, false),
            'address'  => '12 Kingsway',
            'city'     => 'London',
            'zip_code' => 'WC2B 6XF',
            'country'  => 'United Kingdom',
            'map_url'  => 'https://maps.example.com/kingsway',
        ])['data']['venue'];
    }

    private function pick(Event $event, array $overrides = [])
    {
        return $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, array_merge([
            'venue_id' => $this->venue['id'],
            'venue'    => 'Typed name that loses',
            'address'  => 'Typed address that loses',
        ], $overrides)));
    }

    private function meta(Event $event)
    {
        return Event::query()->find($event->id)->meta;
    }

    public function test_picking_a_saved_venue_links_the_event_and_copies_the_venue()
    {
        $event = $this->createEvent();

        $response = $this->pick($event);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $meta = $this->meta($event);
        $this->assertSame($this->venue['name'], $meta['venue']);
        $this->assertSame('12 Kingsway', $meta['address']);
        $this->assertSame('WC2B 6XF', $meta['zip_code']);
        $this->assertSame('https://maps.example.com/kingsway', $meta['map_url']);
        $this->assertSame($this->venue['id'], $this->rest('GET', "events/{$event->id}")['data']['event']['venue_id']);
    }

    public function test_switching_to_a_one_off_venue_unlinks_it_and_clears_the_saved_fields()
    {
        $event = $this->createEvent();
        $this->pick($event);

        $this->pick($event, ['venue_id' => null, 'venue' => 'Corner Café', 'address' => '1 High Street']);

        $meta = $this->meta($event);
        $this->assertSame('Corner Café', $meta['venue']);
        $this->assertSame('1 High Street', $meta['address']);
        $this->assertSame('', $meta['city']);
        $this->assertSame('', $meta['map_url']);
        $this->assertNull(VenueService::eventVenueId($event->post_id));
    }

    public function test_an_online_event_drops_its_venue()
    {
        $event = $this->createEvent();
        $this->pick($event);

        $this->pick($event, ['venue_type' => 'online', 'online_meeting_url' => 'https://meet.example.com/x']);

        $this->assertSame('', $this->meta($event)['venue']);
        $this->assertSame('', $this->meta($event)['country']);
        $this->assertNull(VenueService::eventVenueId($event->post_id));
    }

    public function test_a_venue_that_no_longer_exists_is_refused()
    {
        $event = $this->createEvent();

        $response = $this->pick($event, ['venue_id' => 999999]);

        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString('This venue no longer exists.', wp_json_encode($response['data']));
    }

    public function test_editing_the_venue_updates_every_event_linked_to_it()
    {
        $first = $this->createEvent();
        $second = $this->createEvent();
        $untouched = $this->createEvent([], ['venue' => 'Elsewhere', 'address' => '9 Other Road']);
        $this->pick($first);
        $this->pick($second);

        $this->rest('PATCH', "venues/{$this->venue['id']}", [
            'name'    => 'Renamed Test Hall ' . wp_generate_password(6, false, false),
            'address' => '14 Kingsway',
            'city'    => 'Leeds',
        ]);

        foreach ([$first, $second] as $event) {
            $this->assertSame('14 Kingsway', $this->meta($event)['address']);
            $this->assertSame('Leeds', $this->meta($event)['city']);
            $this->assertSame('', $this->meta($event)['map_url']);
        }

        $this->assertSame('9 Other Road', $this->meta($untouched)['address']);
    }

    public function test_deleting_the_venue_leaves_its_events_a_one_off_copy()
    {
        $event = $this->createEvent();
        $this->pick($event);

        $this->rest('DELETE', "venues/{$this->venue['id']}");

        $this->assertSame($this->venue['name'], $this->meta($event)['venue']);
        $this->assertSame('London', $this->meta($event)['city']);
        $this->assertNull($this->rest('GET', "events/{$event->id}")['data']['event']['venue_id']);
    }

    public function test_an_event_writer_without_a_venue_key_can_load_the_picker_but_not_the_venue_list()
    {
        $this->actAs($this->createUser('editor', ['write_events']));

        $options = $this->rest('GET', 'venues/options');
        $this->assertSame(200, $options['status']);
        $this->assertContains($this->venue['id'], array_column($options['data']['venues'], 'id'));
        $this->assertSame(403, $this->rest('GET', 'venues')['status']);

        $this->actAs($this->createUser('editor', ['read_guests']));
        $this->assertSame(403, $this->rest('GET', 'venues/options')['status']);
    }
}
