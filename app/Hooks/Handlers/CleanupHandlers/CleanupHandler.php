<?php

namespace FluentEvent\App\Hooks\Handlers\CleanupHandlers;

use FluentEvent\App\CPT\EventPost;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Foundation\Exceptions\HttpException;

class CleanupHandler
{
    public function register()
    {
        add_action('fluent_event/calendar/before_deleted', [$this, 'cleanCalendar']);
        add_action('fluent_event/event/before_deleted', [$this, 'cleanEvent']);
        add_action('deleted_post', [$this, 'cleanDeletedPost'], 10, 2);
        add_action('deleted_user', [$this, 'unlinkDeletedUser']);
    }

    // The registrations outlive the account as email guests, so a new account with that email claims
    // them instead of being refused as "already registered" by a user who no longer exists.
    public function unlinkDeletedUser($userId)
    {
        Guest::query()->where('user_id', (int) $userId)->update(['user_id' => null]);
    }

    // An event post deleted outside the plugin (WP-CLI, another plugin) takes the event down with it.
    public function cleanDeletedPost($postId, $post)
    {
        // The plugin's own delete removes the post from inside this action and cleans up after itself.
        if (!$post || $post->post_type !== EventPost::POST_TYPE || doing_action('fluent_event/event/before_deleted')) {
            return;
        }

        $event = Event::query()->where('post_id', $postId)->first();

        if (!$event) {
            return;
        }

        // WordPress clears this cache only after the action; cleanEvent must not still see the post.
        clean_post_cache($post);

        DB::transaction(function () use ($event) {
            do_action('fluent_event/event/before_deleted', $event);

            $event->delete();
        });

        do_action('fluent_event/event/deleted', $event);
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

        // Gone already when cleanDeletedPost runs this, or when the row was removed straight from the database.
        if (get_post($event->post_id) && !wp_delete_post($event->post_id, true)) {
            throw new HttpException(
                422,
                esc_html__('The event could not be deleted. Please try again.', 'fluent-event'),
                'event_post_delete_failed'
            );
        }

        do_action('fluent_event/guest/before_bulk_deleted', $event);

        $event->guests()->delete();

        do_action('fluent_event/guest/bulk_deleted', $event);

        Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $event->id)
            ->delete();
    }
}
