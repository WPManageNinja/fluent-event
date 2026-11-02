<?php

namespace FluentEvent\App\Models;

use FluentEvent\App\CPT\EventPost;

/**
 * Read side of `wp_posts`, where an event's title and body live.
 * Writes still go through wp_insert_post — never this model.
 */
class Post extends Model
{
    public $timestamps = false;

    protected $table = 'posts';

    protected $primaryKey = 'ID';

    protected static function booted()
    {
        static::addGlobalScope('post_type', function ($query) {
            $query->where('post_type', EventPost::POST_TYPE);
        });
    }

    /** @param string|string[] $status */
    public function scopeByStatus($query, $status)
    {
        if ($status) {
            $query->whereIn('post_status', (array) $status);
        }

        return $query;
    }
}
