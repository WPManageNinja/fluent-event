<?php

namespace FluentEvent\App\Models;

/**
 * Calendar model.
 *
 * Table: `wp_fe_calendars` — see database/Migrations/CalendarsMigrator.php.
 */
class Calendar extends Model
{
    protected $table = 'fe_calendars';

    const VISIBILITIES = 'public,private,unlisted';
    const STATUSES = 'active,archived,draft';
    const DEFAULT_THEME_COLOR = '#0099ff';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'visibility',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_by' => 'int',
    ];

    protected static function booted()
    {
        static::creating(function ($calendar) {
            $calendar->created_by = get_current_user_id();
        });

        static::deleting(function ($calendar) {
            foreach ($calendar->events()->get() as $event) {
                $event->delete();
            }
        });
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id', 'id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id', 'id');
    }

    public static function defaultMeta()
    {
        return [
            'theme_color' => self::DEFAULT_THEME_COLOR,
        ];
    }

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $term = '%' . $GLOBALS['wpdb']->esc_like($search) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('slug', 'LIKE', $term);
            });
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

    public function scopeByVisibility($query, $visibility)
    {
        if ($visibility) {
            $query->where('visibility', $visibility);
        }

        return $query;
    }
}
