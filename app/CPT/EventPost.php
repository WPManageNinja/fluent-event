<?php

namespace FluentEvent\App\CPT;

use FluentEvent\App\Services\Settings;

/**
 * The `fe_event` custom post type.
 *
 * An event's identity — title, description, author — lives in `wp_posts`;
 * everything typed lives in `wp_fe_event_details`, joined 1:1 on `post_id`.
 *
 * Public, as FluentCart's products are: WordPress gives every event its own
 * address, /<event_slug>/<post slug>/, and lists them at /<event_slug>/.
 * FrontEndHandler keeps out what our statuses hide. There is still no REST
 * endpoint and no admin list: the plugin's SPA is the only UI.
 *
 * `post_status` follows `event_details.status` (Event::POST_STATUSES), so a
 * private or draft event is never a published post.
 */
class EventPost
{
    /**
     * Post type key.
     */
    const POST_TYPE = 'fe_event';

    /**
     * Register the post type. Called from CPTHandler on `init`.
     *
     * @return void
     */
    public function registerPostType()
    {
        $slug = Settings::get()['event_slug'];

        register_post_type(static::POST_TYPE, [
            'labels'              => $this->getLabels(),

            // Our own events list has its search; WordPress's would show unlisted events.
            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'has_archive'         => $slug,
            'rewrite'             => ['slug' => $slug, 'with_front' => false, 'feeds' => false, 'pages' => false],

            // No WordPress-supplied UI — the SPA owns event management.
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,

            // Not exposed through WordPress' own REST API; ours is namespaced.
            'show_in_rest'        => false,

            // No thumbnail: the cover is a URL in event_details.meta, not an
            // attachment. No custom-fields: nothing edits postmeta by hand.
            'supports'            => ['title', 'editor', 'author'],

            // Events belong to the site, not their author: deleting a WP user must not delete them.
            'delete_with_user'    => false,

            // Admins only, as the plugin's API is: an editor could otherwise delete an event over XML-RPC.
            'capability_type'     => 'post',
            'capabilities'        => array_fill_keys([
                'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts',
                'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts',
                'publish_posts', 'read_private_posts', 'create_posts',
            ], 'manage_options'),
            'map_meta_cap'        => true,
            'hierarchical'        => false,
        ]);
    }

    /**
     * Labels. Still worth defining even with no admin UI — they surface in
     * error messages, REST schema and anything that reflects on the type.
     *
     * @return array<string, string>
     */
    protected function getLabels()
    {
        return [
            'name'          => _x('Events', 'post type general name', 'fluent-event'),
            'singular_name' => _x('Event', 'post type singular name', 'fluent-event'),
            'add_new_item'  => __('Add New Event', 'fluent-event'),
            'edit_item'     => __('Edit Event', 'fluent-event'),
            'view_item'     => __('View Event', 'fluent-event'),
            'search_items'  => __('Search Events', 'fluent-event'),
            'not_found'     => __('No events found', 'fluent-event'),
        ];
    }
}
