<?php

namespace FluentEvent\Tests\Unit\CPT;

use FluentEvent\App\CPT\EventPost;
use FluentEvent\Tests\TestCase;

class EventPostReservedSlugTest extends TestCase
{
    public function test_create_and_edit_are_taken_for_events_only()
    {
        $this->assertTrue(EventPost::isReservedSlug(false, 'create', 'fevent_event'));
        $this->assertTrue(EventPost::isReservedSlug(false, 'edit', 'fevent_event'));
        $this->assertFalse(EventPost::isReservedSlug(false, 'create-day', 'fevent_event'));
        $this->assertFalse(EventPost::isReservedSlug(false, 'create', 'post'));
        $this->assertTrue(EventPost::isReservedSlug(true, 'meetup', 'fevent_event'));
    }
}
