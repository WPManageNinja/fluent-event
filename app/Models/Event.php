<?php

namespace FluentEvent\App\Models;

use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Framework\Foundation\Exceptions\HttpException;

/**
 * Event model.
 *
 * Table: `wp_fe_event_details` — database/Migrations/EventDetailsMigrator.php.
 */
class Event extends Model
{
    protected $table = 'fe_event_details';

    const STATUSES = 'public,private,draft,archived';
    const VENUE_TYPES = 'offline,online';
    const ENTRY_TYPES = 'rsvp';
    const DEFAULT_ENTRY_TYPE = 'rsvp';
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

    protected $hidden = ['post'];

    protected $appends = [
        'title',
        'description',
        'author',
        'start_at_local',
        'end_at_local',
    ];

    protected static function booted()
    {
        static::deleting(function ($event) {
            if (!wp_delete_post($event->post_id, true)) {
                throw new HttpException(
                    422,
                    __('The event could not be deleted. Please try again.', 'fluent-event'),
                    'event_post_delete_failed'
                );
            }

            $event->guests()->delete();
        });
    }

    public static function defaultMeta()
    {
        return [
            'cover_url'          => '',
            'online_meeting_url' => '',
            'venue'              => '',
            'address'            => '',
            'entry_type'         => self::DEFAULT_ENTRY_TYPE,
        ];
    }

    public static function postStatusFor($status)
    {
        return self::POST_STATUSES[$status] ?? 'draft';
    }

    public function guests()
    {
        return $this->hasMany(Guest::class, 'event_id', 'id');
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class, 'calendar_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'ID');
    }

    public function getTitleAttribute()
    {
        return $this->post->post_title ?? '';
    }

    public function getDescriptionAttribute()
    {
        return wp_kses_post($this->post->post_content ?? '');
    }

    public function getAuthorAttribute()
    {
        $author = $this->post->author ?? null;
        if (!$author) return null;

        return [
            'name'   => $author->display_name,
            'avatar' => get_avatar_url($author->ID),
        ];
    }

    public function getStartAtLocalAttribute()
    {
        return DateTimeHelper::convertFromUtc($this->start_at, $this->timezone);
    }

    public function getEndAtLocalAttribute()
    {
        return DateTimeHelper::convertFromUtc($this->end_at, $this->timezone);
    }

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $term = '%' . $GLOBALS['wpdb']->esc_like($search) . '%';

            $query->whereHas('post', function ($query) use ($term) {
                $query->where('post_title', 'LIKE', $term);
            });
        }

        return $query;
    }

    public function scopeByCalendar($query, $calendarId)
    {
        if ($calendarId) {
            $query->where('calendar_id', $calendarId);
        }

        return $query;
    }

    public function scopeByStatus($query, $status)
    {
        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function scopeInDateRange($query, $from, $to)
    {
        $isDate = function ($value) {
            return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
        };

        if ($isDate($from)) {
            $query->where('end_at', '>=', $from . ' 00:00:00');
        }

        if ($isDate($to)) {
            $query->where('start_at', '<=', $to . ' 23:59:59');
        }

        return $query;
    }

    public function scopeByPeriod($query, $period)
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        if ($period === 'upcoming') {
            return $query->where('end_at', '>=', $now)->orderBy('start_at', 'ASC');
        }

        if ($period === 'past') {
            return $query->where('end_at', '<', $now)->orderBy('start_at', 'DESC');
        }

        return $query->orderByRaw(
            '(end_at < ?) ASC, ABS(TIMESTAMPDIFF(SECOND, ?, start_at)) ASC',
            [$now, $now]
        );
    }
}
