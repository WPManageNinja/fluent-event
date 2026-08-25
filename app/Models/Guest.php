<?php

namespace FluentEvent\App\Models;

/**
 * Guest model.
 *
 * Table: `wp_fe_guests` — database/Migrations/GuestsMigrator.php.
 */
class Guest extends Model
{
    protected $table = 'fe_guests';

    const STATUSES = 'going,checked_in,cancelled';
    const DEFAULT_STATUS = 'going';

    protected $fillable = [
        'event_id',
        'calendar_id',
        'group_id',
        'is_buyer',
        'name',
        'email',
        'user_id',
        'status',
        'checked_in_at',
        'source',
        'meta',
    ];

    protected $casts = [
        'meta'        => 'array',
        'event_id'    => 'int',
        'calendar_id' => 'int',
        'user_id'     => 'int',
        'is_buyer'    => 'int',
    ];

    protected $appends = ['avatar'];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function getAvatarAttribute()
    {
        return get_avatar_url($this->user_id ?: $this->email) ?: '';
    }

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $term = '%' . $GLOBALS['wpdb']->esc_like($search) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('email', 'LIKE', $term);
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
}
