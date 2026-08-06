<?php

namespace FluentEvent\App\Models;

/**
 * Event model.
 *
 * Table: `wp_fe_event_details` — database/Migrations/EventDetailsMigrator.php.
 *
 * An event is a CPT hybrid: identity (title, description, author) lives in the
 * `fe_event` post, everything typed lives here, joined 1:1 on `post_id`.
 */
class Event extends Model
{
    protected $table = 'fe_event_details';

    const STATUSES = 'public,private,draft,archived';
    const VENUE_TYPES = 'offline,online';
    const POST_STATUSES = [
        'public'   => 'publish',
        'private'  => 'private',
        'draft'    => 'draft',
        'archived' => 'draft',
    ];

    protected $fillable = [
        'post_id',
        'calendar_id',
        'parent_event_id',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'venue_type',
        'meta',
    ];

    protected $casts = [
        'meta'        => 'array',
        'post_id'     => 'int',
        'calendar_id' => 'int',
    ];

    public static function defaultMeta()
    {
        return [
            'cover_url'          => '',
            'online_meeting_url' => '',
            'venue'              => '',
            'address'            => '',
        ];
    }

    public static function postStatusFor($status)
    {
        return self::POST_STATUSES[$status] ?? 'draft';
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class, 'calendar_id', 'id');
    }
}
