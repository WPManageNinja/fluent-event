<?php

namespace FluentEvent\App\Models;

use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;

/**
 * Event model.
 *
 * Table: `wp_fevent_event_details` — database/Migrations/EventDetailsMigrator.php.
 */
class Event extends Model
{
    protected $table = 'fevent_event_details';

    const STATUSES = 'public,unlisted,private,draft';
    const LIVE_STATUSES = ['public', 'unlisted', 'private'];
    const VENUE_TYPES = 'offline,online';
    const ENTRY_TYPES = 'rsvp,open,none';
    const SEAT_STATUSES = ['going', 'checked_in'];
    // A guest who holds a seat or waits for one: they can't register again, and can leave.
    const PLACE_STATUSES = ['going', 'checked_in', 'waitlisted'];
    const REGISTRATION_LIMITS = ['registration_opens', 'registration_closes'];
    const POST_STATUSES = [
        'public'   => 'publish',
        'unlisted' => 'publish',
        'private'  => 'private',
        'draft'    => 'draft',
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
        'start_at_local',
        'end_at_local',
        'start_at_site',
        'end_at_site',
    ];

    public static function defaultMeta()
    {
        return [
            'cover_url'          => '',
            'online_meeting_url' => '',
            'venue'              => '',
            'address'            => '',
            'entry_type'         => '',
            'show_guest_count'   => 'yes',
            'capacity'           => 0, // most guests going at once; 0 = unlimited
            // Whether guests see the spots left on a capped event; the going count has its own switch.
            'show_spots_left'    => 'no',
            // yes: once a capped event is full, new sign-ups join its waitlist instead of being turned away.
            'waitlist'           => 'no',
            // How the event page shows the time until the start: relative ("Starts in 3 days") or a live countdown.
            'start_display'      => 'relative',
            'registration_opens'  => null, // {value, unit} before the start; null = as soon as published
            'registration_closes' => null, // {value, unit} before the start; null = until the event ends
            // The description's markdown, as written in the editor; the post holds its rendered HTML.
            'description_md'     => '',
        ];
    }

    // Capped at 255, where the venue rule stood before the filter.
    public static function venueMaxLength()
    {
        return min(255, max(1, (int) apply_filters('fluent_event/event_venue_max_length', 100)));
    }

    public static function postStatusFor($status)
    {
        return self::POST_STATUSES[$status] ?? 'draft';
    }

    // [opens, closes] as UTC timestamps, each null where the host set no limit.
    // Counted back on the event's own clock, as the form previews it, so "7 days before" survives a DST change.
    public static function registrationWindow($event)
    {
        $meta = (array) $event->meta;
        $startsAt = strtotime(DateTimeHelper::convertFromUtc($event->start_at, $event->timezone) . ' UTC');

        return array_map(function ($key) use ($meta, $startsAt, $event) {
            if (empty($meta[$key])) {
                return null;
            }

            $local = gmdate(DateTimeHelper::FORMAT, $startsAt - Settings::reminderInSeconds($meta[$key]));
            $at = strtotime(DateTimeHelper::convertToUtc($local, $event->timezone) . ' UTC');

            // A time the clocks skip (2:30 on a spring-forward night) would read as an hour later and could open
            // after closing; it counts as the moment the clocks jump instead.
            $skipped = strtotime(DateTimeHelper::convertFromUtc(gmdate(DateTimeHelper::FORMAT, $at), $event->timezone) . ' UTC') - strtotime($local . ' UTC');

            if ($skipped > 0) {
                $timezone = new \DateTimeZone(DateTimeHelper::getValidatedTimeZone($event->timezone));
                $jumps = $timezone->getTransitions($at - $skipped, $at);
                $at = end($jumps)['ts'];
            }

            return $at;
        }, self::REGISTRATION_LIMITS);
    }

    // not_open, open, closed or ended, on the server's clock.
    public static function registrationState($event)
    {
        [$opensAt, $closesAt] = self::registrationWindow($event);
        $now = time();

        if ($event->end_at < gmdate(DateTimeHelper::FORMAT)) {
            return 'ended';
        }

        if ($opensAt && $now < $opensAt) {
            return 'not_open';
        }

        return $closesAt && $now >= $closesAt ? 'closed' : 'open';
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
        // Plain text with "<" and ">" restored — every sink must escape it, never print it as HTML.
        return strtr($this->post->post_title ?? '', ['&amp;' => '&', '&lt;' => '<', '&gt;' => '>', '&quot;' => '"', '&#039;' => "'"]);
    }

    public function getDescriptionAttribute()
    {
        return wp_kses_post($this->post->post_content ?? '');
    }

    public function getStartAtLocalAttribute()
    {
        return DateTimeHelper::convertFromUtc($this->start_at, $this->timezone);
    }

    public function getEndAtLocalAttribute()
    {
        return DateTimeHelper::convertFromUtc($this->end_at, $this->timezone);
    }

    // *_local is the event's own clock, for its page and its guests; lists and the date filter read the site's.
    public function getStartAtSiteAttribute()
    {
        return DateTimeHelper::convertFromUtc($this->start_at, wp_timezone_string());
    }

    public function getEndAtSiteAttribute()
    {
        return DateTimeHelper::convertFromUtc($this->end_at, wp_timezone_string());
    }

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $search = '%' . addcslashes($search, '%_\\') . '%';

            $query->whereHas('post', function ($query) use ($search) {
                $query->where('post_title', 'LIKE', $search);
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

    /** @param string|string[] $status */
    public function scopeByStatus($query, $status)
    {
        if ($status) {
            $query->whereIn('status', (array) $status);
        }

        return $query;
    }

    // Days are the site's days, the clock every list shows (start_at_site).
    public function scopeInDateRange($query, $from, $to)
    {
        if (DateTimeHelper::isDate($from)) {
            $query->where('end_at', '>=', DateTimeHelper::convertToUtc($from . ' 00:00:00', wp_timezone_string()));
        }

        if (DateTimeHelper::isDate($to)) {
            $query->where('start_at', '<=', DateTimeHelper::convertToUtc($to . ' 23:59:59', wp_timezone_string()));
        }

        return $query;
    }

    // Each order ends on id: MySQL orders rows with equal keys differently per LIMIT, so pages would repeat or skip events.
    public function scopeByPeriod($query, $period)
    {
        $now = gmdate(DateTimeHelper::FORMAT);

        if ($period === 'upcoming') {
            return $query->where('end_at', '>=', $now)->orderBy('start_at', 'ASC')->orderBy('id', 'ASC');
        }

        if ($period === 'past') {
            return $query->where('end_at', '<', $now)->orderBy('start_at', 'DESC')->orderBy('id', 'DESC');
        }

        return $query->orderByRaw(
            '(end_at < ?) ASC, ABS(TIMESTAMPDIFF(SECOND, ?, start_at)) ASC',
            [$now, $now]
        )->orderBy('id', 'ASC');
    }
}
