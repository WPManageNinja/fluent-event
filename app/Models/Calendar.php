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

    const STATUSES = 'public,private,draft';
    const DEFAULT_THEME_COLOR = '#0099ff';

    protected $fillable = [
        'title',
        'slug',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_by' => 'int',
    ];

    protected $searchable = ['title', 'slug'];

    protected static function booted()
    {
        static::creating(function ($calendar) {
            $calendar->created_by = get_current_user_id();
        });
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id', 'id');
    }

    public function guests()
    {
        return $this->hasMany(Guest::class, 'calendar_id', 'id');
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
            $fields = $this->searchable;
            $search = '%' . addcslashes($search, '%_\\') . '%';

            $query->where(function ($q) use ($fields, $search) {
                $q->where(array_shift($fields), 'LIKE', $search);

                foreach ($fields as $field) {
                    $q->orWhere($field, 'LIKE', $search);
                }
            });
        }

        return $query;
    }

    /** @param string|string[] $status */
    public function scopeByStatus($query, $status)
    {
        if ($status) {
            $query->whereIn('status', (array) $status);
        }

        return $query;
    }
}
