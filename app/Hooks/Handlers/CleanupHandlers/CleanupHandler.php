<?php

namespace FluentEvent\App\Hooks\Handlers\CleanupHandlers;

use FluentEvent\App\Models\Meta;
use FluentEvent\Framework\Foundation\Exceptions\HttpException;

class CleanupHandler
{
    public function register()
    {
        add_action('fluent_event/calendar/before_deleted', [$this, 'cleanCalendar']);
        add_action('fluent_event/event/before_deleted', [$this, 'cleanEvent']);
    }

    public function cleanCalendar($calendar)
    {
        if (empty($calendar)) return;

        foreach ($calendar->events()->get() as $event) {
            do_action('fluent_event/event/before_deleted', $event);
            $event->delete();
            do_action('fluent_event/event/deleted', $event);
        }
    }

    public function cleanEvent($event)
    {
        if (empty($event)) return;

        // A post already deleted outside the plugin (XML-RPC, WP-CLI) leaves nothing to remove here.
        if (get_post($event->post_id) && !wp_delete_post($event->post_id, true)) {
            throw new HttpException(
                422,
                esc_html__('The event could not be deleted. Please try again.', 'fluent-event'),
                'event_post_delete_failed'
            );
        }

        $guests = $event->guests()->get();

        do_action('fluent_event/guest/before_bulk_deleted', $event, $guests);

        $event->guests()->delete();

        do_action('fluent_event/guest/bulk_deleted', $event, $guests);

        Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $event->id)
            ->delete();
    }
}
