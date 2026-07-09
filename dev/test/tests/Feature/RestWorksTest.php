<?php

namespace Dev\Test\Tests\Feature;

use Dev\Test\Inc\TestCase;

/**
 * Feature-level sample: exercises the plugin's own router through the full
 * REST stack — routing, policy resolution, response serialization.
 *
 * Intentionally free of factory/model dependencies so a minimal scaffold
 * (no Post, no Comment, no seed data) boots these tests green out of the box.
 */
class RestWorksTest extends TestCase
{
    public function test_rest_works_as_guest()
    {
        $this->router->get('test', function () {});

        $response = $this->get('test');

        $this->assertTrue($response->isOkay());
    }

    public function test_rest_works_as_admin()
    {
        $this->router->get('test', function () {})->withPolicy(
            fn() => current_user_can('administrator')
        );

        $response = $this->ActAsAdmin()->get('test');

        $this->assertTrue($response->isOkay());
    }
}
