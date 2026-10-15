<?php

namespace FluentEvent\App\CPT;

/**
 * The `fe_event` custom post type.
 *
 * An event's identity — title, description, author — lives in `wp_posts`;
 * everything typed lives in `wp_fe_event_details`, joined 1:1 on `post_id`.
 *
 * The post type is deliberately NOT public. An event has no permalink, no
 * archive, no REST endpoint and no admin list, and appears in neither the
 * Posts nor the Pages screen. The plugin's own SPA is the only UI, and when a
 * front-end arrives it will be a page plus a query parameter we control rather
 * than a WordPress rewrite rule.
 *
 * `post_status` stays `publish`. Visibility is a property of the type, not the
 * row, which leaves `event_details.status` free to mean what it says.
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
        register_post_type(static::POST_TYPE, [
            'labels'              => $this->getLabels(),

            // Not reachable from the front-end by any route.
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,

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

'capability_type'     => 'post',
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
