<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\Tests\IntegrationTestCase;

/**
 * The free plugin has no team permissions: every admin route is for site administrators
 * (manage_options) only. Per-module access is a Pro feature.
 */
class AdminAccessTest extends IntegrationTestCase
{
    public function adminRoutes()
    {
        return [
            'dashboard'      => ['GET', 'dashboard'],
            'reports'        => ['GET', 'reports'],
            'user search'    => ['GET', 'users'],
            'settings'       => ['GET', 'settings/general'],
            'save settings'  => ['POST', 'settings/email', ['from_name' => 'Events', 'from_email' => 'events@example.test']],
            'calendars'      => ['GET', 'calendars'],
            'calendar names' => ['GET', 'calendars/options'],
            'events'         => ['GET', 'events'],
            'new event'      => ['POST', 'events', ['title' => 'Nope']],
        ];
    }

    /**
     * @dataProvider adminRoutes
     */
    public function test_an_admin_route_is_refused_to_everyone_but_a_site_administrator($method, $route, $params = [])
    {
        $this->actAs(0);
        $this->assertContains($this->rest($method, $route, $params)['status'], [401, 403], 'logged out');

        foreach (['editor', 'author', 'subscriber'] as $role) {
            $this->actAs($this->createUser($role));
            $this->assertSame(403, $this->rest($method, $route, $params)['status'], $role);
        }
    }

    public function test_a_site_administrator_reaches_the_admin_routes()
    {
        $event = $this->createEvent();
        $this->actAs($this->createAdmin());

        foreach (['dashboard', 'reports', 'settings/general', 'calendars', 'events', "events/{$event->id}/guests"] as $route) {
            $this->assertSame(200, $this->rest('GET', $route)['status'], $route);
        }
    }

    public function test_a_non_admin_cannot_delete_an_event_or_its_guests()
    {
        $event = $this->createEvent();
        $guest = $this->createGuest($event);
        $this->actAs($this->createUser('editor'));

        $this->assertSame(403, $this->rest('DELETE', "events/{$event->id}/guests/{$guest->id}")['status']);
        $this->assertSame(403, $this->rest('DELETE', "events/{$event->id}")['status']);
        $this->assertNotNull(get_post($event->post_id));
    }
}
