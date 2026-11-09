<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Calendar;
use FluentEvent\Framework\Support\Arr;

/**
 * Calendars that belong to a FluentCommunity space, and the one Community calendar for events
 * posted outside any space. Each link is one key in `calendars.meta` — no column, no join table.
 */
class CalendarService
{
    const SPACE_META_KEY = 'fcom_space_id';
    const GLOBAL_META_KEY = 'fcom_global';

    public static function findGlobal()
    {
        // The LIKE narrows on the stored JSON; the decoded meta decides.
        $candidates = Calendar::query()
            ->where('meta', 'LIKE', '%"' . self::GLOBAL_META_KEY . '"%')
            ->get();

        foreach ($candidates as $calendar) {
            if (Arr::get((array) $calendar->meta, self::GLOBAL_META_KEY) === true) {
                return $calendar;
            }
        }

        return null;
    }

    public static function createGlobal()
    {
        return Calendar::create([
            'title'  => __('Community', 'fluent-event'),
            'slug'   => self::uniqueSlug('community'),
            'status' => 'private',
            'meta'   => array_merge(Calendar::defaultMeta(), [
                self::GLOBAL_META_KEY => true,
            ]),
        ]);
    }

    public static function findBySpace($spaceId)
    {
        $spaceId = (int) $spaceId;

        if ($spaceId < 1) {
            return null;
        }

        // The LIKE also matches ids that only start with this one, so confirm in PHP.
        $candidates = Calendar::query()
            ->where('meta', 'LIKE', '%"' . self::SPACE_META_KEY . '":' . $spaceId . '%')
            ->get();

        foreach ($candidates as $calendar) {
            if ((int) Arr::get((array) $calendar->meta, self::SPACE_META_KEY) === $spaceId) {
                return $calendar;
            }
        }

        return null;
    }

    public static function createForSpace($spaceId, $title, $slug)
    {
        return Calendar::create([
            'title'  => $title,
            'slug'   => self::uniqueSlug($slug ?: $title),
            'status' => 'private',
            'meta'   => array_merge(Calendar::defaultMeta(), [
                self::SPACE_META_KEY => (int) $spaceId,
            ]),
        ]);
    }

    public static function uniqueSlug($slug, $ignoreId = 0)
    {
        $base = sanitize_title($slug) ?: 'calendar';
        $slug = $base;
        $suffix = 2;

        while (Calendar::query()->where('slug', $slug)->where('id', '!=', (int) $ignoreId)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
