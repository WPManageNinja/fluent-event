<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\CPT\EventPost;
use FluentEvent\App\Models\Event;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Foundation\Exceptions\HttpException;

/**
 * The `wp_posts` half of the event CPT hybrid.
 *
 * Identity (title, body, status, author) lives in WordPress; the typed columns
 * live in `wp_fe_event_details`. Keeping the post writes here means a caller
 * only deals with one API, and the WP_Error-to-422 translation happens once.
 */
class PostService
{
    public static function createEventPost($data, $authorId = null)
    {
        $postId = wp_insert_post([
            'post_type'    => EventPost::POST_TYPE,
            'post_title'   => Arr::get($data, 'title'),
            'post_content' => Arr::get($data, 'description', ''),
            'post_status'  => Event::postStatusFor(Arr::get($data, 'status')),
            'post_author'  => $authorId ?: get_current_user_id(),
        ], true);

        if (is_wp_error($postId)) {
            throw new HttpException(
                422,
                __('The event could not be saved. Please try again.', 'fluent-event'),
                'event_post_insert_failed'
            );
        }

        return $postId;
    }

    public static function updateEventPost($postId, $data)
    {
        $result = wp_update_post([
            'ID'           => $postId,
            'post_title'   => Arr::get($data, 'title'),
            'post_content' => Arr::get($data, 'description', ''),
            'post_status'  => Event::postStatusFor(Arr::get($data, 'status')),
        ], true);

        if (is_wp_error($result)) {
            throw new HttpException(
                422,
                __('The event could not be saved. Please try again.', 'fluent-event'),
                'event_post_update_failed'
            );
        }
    }
}
