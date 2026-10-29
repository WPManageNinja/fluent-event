<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Services\VenueService;
use FluentEvent\Tests\IntegrationTestCase;

class VenueEndpointsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actAs($this->createAdmin());
    }

    private function venue(array $overrides = [])
    {
        return array_merge([
            'name'     => 'Integration Test Hall',
            'address'  => '12 Kingsway',
            'city'     => 'London',
            'zip_code' => 'WC2B 6XF',
            'country'  => 'United Kingdom',
            'map_url'  => 'https://maps.example.com/kingsway',
        ], $overrides);
    }

    private function create(array $overrides = [])
    {
        return $this->rest('POST', 'venues', $this->venue($overrides));
    }

    public function test_a_venue_is_saved_with_every_field_and_listed()
    {
        $response = $this->create(['name' => 'Tom & Jerry\'s Hall']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $venue = $response['data']['venue'];
        $this->assertSame('Tom & Jerry\'s Hall', $venue['name']);
        $this->assertSame('WC2B 6XF', $venue['zip_code']);
        $this->assertSame('https://maps.example.com/kingsway', $venue['map_url']);

        // Searched by name: the site may hold venues of its own.
        $listed = $this->rest('GET', 'venues', ['search' => 'Jerry'])['data']['venues'];
        $this->assertSame(1, $listed['total']);
        $this->assertSame($venue, $listed['data'][0]);
    }

    public function test_only_the_name_and_address_are_required()
    {
        $minimal = $this->rest('POST', 'venues', ['name' => 'Corner Café', 'address' => '1 High Street']);
        $this->assertSame(200, $minimal['status'], wp_json_encode($minimal['data']));
        $this->assertSame('', $minimal['data']['venue']['city']);

        $response = $this->rest('POST', 'venues', ['city' => 'London']);
        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString('Venue name is required.', wp_json_encode($response['data']));
        $this->assertStringContainsString('Address is required.', wp_json_encode($response['data']));
    }

    /**
     * @dataProvider mapLinks
     */
    public function test_a_map_link_is_empty_or_a_real_web_address($link, $isValid)
    {
        $response = $this->create(['map_url' => $link]);

        $this->assertSame($isValid ? 200 : 422, $response['status'], wp_json_encode($response['data']));

        if (!$isValid) {
            $this->assertStringContainsString('Please provide a valid URL.', wp_json_encode($response['data']));
        }
    }

    public function mapLinks()
    {
        return [
            'empty'                 => ['', true],
            'a maps link'           => ['https://maps.google.com/?q=Kingsway+Hall', true],
            'a short link'          => ['https://maps.app.goo.gl/AbC123', true],
            'a host with no domain' => ['http://testinglink', false],
            'no scheme'             => ['maps.google.com/?q=x', false],
            'another scheme'        => ['ftp://maps.example.com/x', false],
            'script'                => ['javascript:alert(1)', false],
            'plain words'           => ['near the station', false],
        ];
    }

    public function test_two_venues_cannot_share_a_name()
    {
        $this->create();
        $other = $this->create(['name' => 'Corner Café'])['data']['venue'];

        $this->assertSame(422, $this->create()['status']);
        $this->assertStringContainsString('A venue with this name already exists.', wp_json_encode($this->create()['data']));

        $renamed = $this->rest('PATCH', "venues/{$other['id']}", $this->venue());
        $this->assertSame(422, $renamed['status']);

        $kept = $this->rest('PATCH', "venues/{$other['id']}", $this->venue(['name' => 'Corner Café', 'city' => 'Leeds']));
        $this->assertSame(200, $kept['status'], wp_json_encode($kept['data']));
    }

    public function test_an_update_replaces_every_field_and_can_clear_the_optional_ones()
    {
        $venue = $this->create()['data']['venue'];

        $response = $this->rest('PATCH', "venues/{$venue['id']}", ['name' => 'Integration Test Hall', 'address' => '14 Kingsway']);

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame('14 Kingsway', $response['data']['venue']['address']);
        $this->assertSame('', $response['data']['venue']['city']);
        $this->assertSame('', $response['data']['venue']['map_url']);
    }

    public function test_the_list_pages_newest_first_and_searches_by_name()
    {
        // A run-unique prefix keeps the site's own venues out of the counts.
        $prefix = 'Zq' . wp_generate_password(6, false, false);

        foreach (['Bravo Hall', 'Alpha Hall', 'Charlie Room'] as $name) {
            $this->create(['name' => "{$prefix} {$name}"]);
        }

        $page = $this->rest('GET', 'venues', ['search' => $prefix, 'per_page' => 2, 'page' => 1])['data']['venues'];
        $this->assertSame(3, $page['total']);
        $this->assertSame(["{$prefix} Charlie Room", "{$prefix} Alpha Hall"], array_column($page['data'], 'name'));

        $found = $this->rest('GET', 'venues', ['search' => "{$prefix} Bravo"])['data']['venues'];
        $this->assertSame(1, $found['total']);
    }

    public function test_a_deleted_venue_is_gone_and_a_missing_one_is_a_404()
    {
        $venue = $this->create()['data']['venue'];

        $this->assertSame(200, $this->rest('DELETE', "venues/{$venue['id']}")['status']);
        $this->assertNull(VenueService::find($venue['id']));

        $this->assertSame(404, $this->rest('DELETE', "venues/{$venue['id']}")['status']);
        $this->assertSame(404, $this->rest('PATCH', "venues/{$venue['id']}", $this->venue())['status']);
    }

    public function test_a_venue_reader_can_list_but_not_change_venues()
    {
        $venue = $this->create()['data']['venue'];

        $this->actAs($this->createUser('editor', ['read_venues']));

        $this->assertSame(200, $this->rest('GET', 'venues')['status']);
        $this->assertSame(403, $this->create(['name' => 'Nope'])['status']);
        $this->assertSame(403, $this->rest('PATCH', "venues/{$venue['id']}", $this->venue())['status']);
        $this->assertSame(403, $this->rest('DELETE', "venues/{$venue['id']}")['status']);
    }

    public function test_an_event_writer_without_a_venue_key_cannot_open_the_venue_list()
    {
        $this->actAs($this->createUser('editor', ['write_events']));

        $this->assertSame(403, $this->rest('GET', 'venues')['status']);
    }

    public function test_a_venue_writer_can_create_one()
    {
        $this->actAs($this->createUser('editor', ['write_venues']));

        $this->assertSame(200, $this->create()['status']);
    }
}
