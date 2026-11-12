<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\BlockEditorHandler;
use FluentEvent\App\Hooks\Handlers\FrontEndHandler;
use FluentEvent\App\Models\Event;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The two blocks render through the shortcodes, with each attribute held to the values the shortcode takes.
 */
class BlockRenderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        $this->createPublicPages();

        if (!\WP_Block_Type_Registry::get_instance()->is_registered('fluent-event/events')) {
            (new BlockEditorHandler())->registerBlocks();
        }
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    public function test_the_events_block_renders_the_list_from_its_attributes_inside_its_aligned_wrapper()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Block Club']);
        $this->createCalendar(['title' => 'Zebra Other Block Club']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra block night']);

        $html = do_blocks('<!-- wp:fluent-event/events ' . wp_json_encode([
            'align'          => 'wide',
            'calendar'       => $calendar->slug,
            'view'           => 'cards',
            'show_calendars' => 'no',
        ]) . ' /-->');

        $this->assertStringContainsString('alignwide wp-block-fluent-event-events', $html);
        $this->assertStringContainsString('Zebra block night', $html);
        $this->assertStringContainsString('fe-event-grid', $html);
        $this->assertStringNotContainsString('data-fe-chips', $html);
    }

    // Anyone who edits a post saves any attribute; one outside the shortcode's values falls back to its default.
    public function test_an_attribute_outside_its_values_is_dropped()
    {
        $html = (new BlockEditorHandler())->renderEvents([
            'view'           => '"><script>alert(1)</script>',
            'filter'         => ['past'],
            'view_switch'    => 'maybe',
            'show_calendars' => 'sometimes',
            'calendar'       => '<b>Bad</b> slug',
        ]);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('fe-switch__option--icon', $html, 'An unknown view_switch keeps the setting');
        $this->assertMatchesRegularExpression('/fe-switch__option--active"\s*href="[^"]*filter=upcoming/', $html);
    }

    // An unlisted event opens only by its link, so the editor's picker lists it to admins alone.
    public function test_the_event_picker_lists_unlisted_events_to_admins_only()
    {
        $calendar = $this->createCalendar();
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra listed night']);
        $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra unlisted night', 'status' => 'unlisted']);

        $handler = new class extends BlockEditorHandler {
            public function titles()
            {
                return array_column($this->editorData()['events'], 'title');
            }
        };

        $this->actAs($this->createUser('contributor'));
        $this->assertContains('Zebra listed night', $handler->titles());
        $this->assertNotContains('Zebra unlisted night', $handler->titles());

        $this->actAs($this->createAdmin());
        $this->assertContains('Zebra unlisted night', $handler->titles());
    }

    public function test_the_event_block_renders_the_event_it_names()
    {
        $event = $this->createEvent(['title' => 'Zebra pinned night']);

        $html = do_blocks('<!-- wp:fluent-event/event {"event":"' . $event->post->post_name . '"} /-->');

        $this->assertStringContainsString('wp-block-fluent-event-event', $html);
        $this->assertStringContainsString('Zebra pinned night', $html);
        $this->assertStringContainsString('data-fe-card', $html);
    }

    // The single-event template's pieces: each draws its part of the open event, and nothing where none is open.
    public function test_the_event_page_blocks_render_the_open_events_pieces()
    {
        $calendar = $this->createCalendar(['title' => 'Zebra Parts Club']);
        $event = $this->createEvent(['calendar_id' => $calendar->id, 'title' => 'Zebra parts night']);
        $blocks = '<!-- wp:fluent-event/event-title /--><!-- wp:fluent-event/event-calendar /--><!-- wp:fluent-event/event-registration /-->';

        $this->assertSame('', trim(do_blocks($blocks)), 'No event is open.');

        $page = new class extends FrontEndHandler {
            public function open($event)
            {
                static::$pageEvent = $event;
            }
        };

        $page->open(Event::query()->with(['post', 'calendar'])->find($event->id));

        try {
            $html = do_blocks($blocks);
        } finally {
            $page->open(null);
        }

        $this->assertStringContainsString('wp-block-fluent-event-event-title', $html);
        $this->assertStringContainsString('Zebra parts night', $html);
        $this->assertStringContainsString('Zebra Parts Club', $html);
        $this->assertStringContainsString('data-fe-card', $html);
    }

    // WordPress dequeues what a block enqueued when it renders empty, as the cover part does for an event with no cover.
    public function test_the_event_page_keeps_its_styles_when_its_first_part_renders_empty()
    {
        $event = $this->createEvent(['title' => 'Zebra coverless night']);
        wp_dequeue_style(FrontEndHandler::ASSET_HANDLE);
        wp_dequeue_script(FrontEndHandler::ASSET_HANDLE);

        $page = new class extends FrontEndHandler {
            public function open($event)
            {
                static::$pageEvent = $event;
            }
        };

        $page->open(Event::query()->with(['post', 'calendar'])->find($event->id));

        try {
            $html = do_blocks('<!-- wp:fluent-event/event-cover /--><!-- wp:fluent-event/event-title /-->');
        } finally {
            $page->open(null);
        }

        $this->assertStringNotContainsString('wp-block-fluent-event-event-cover', $html, 'No cover, so the cover part is empty.');
        $this->assertTrue(wp_style_is(FrontEndHandler::ASSET_HANDLE, 'enqueued'));
        $this->assertTrue(wp_script_is(FrontEndHandler::ASSET_HANDLE, 'enqueued'));
    }
}
