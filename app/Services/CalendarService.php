<?php

namespace FluentEvent\App\Services;

use FluentEvent\Facade\DB;
use FluentEvent\App\Models\Calendar;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Foundation\Exceptions\ForbiddenHttpException;
use FluentEvent\Framework\Validator\ValidationException;
use FluentEvent\Framework\Validator\Validator;
use FluentEvent\App\Http\Requests\CalendarRequest;

/**
 * Calendars, as an integration sees them. The space link is one key in
 * `calendars.meta` — no column, no join table.
 */
class CalendarService
{
    const SPACE_META_KEY = 'fcom_space_id';

    const WRITABLE = ['title', 'description', 'theme_color'];

    public static function createForSpace(array $data, int $actorId): array
    {
        if (!PermissionManager::userCan('write_calendars', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to create calendars.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        $spaceId = (int) Arr::get($data, 'space_id', 0);
        $slug = sanitize_title((string) Arr::get($data, 'slug', ''));

        // One space, one calendar — the same answer `link()` gives. Callers
        // resolve the existing one with `findBySpace()`.
        if ($spaceId && static::matchSpace($spaceId)) {
            throw new ValidationException('Unprocessable Entity!', 422, null, [
                'space_id' => ['linked' => __('This space already has a calendar.', 'fluent-event')],
            ]);
        }

        $data = array_merge(
            array_fill_keys(self::WRITABLE, ''),
            CalendarRequest::sanitize(Arr::only($data, self::WRITABLE))
        );

        $data['theme_color'] = $data['theme_color'] ?: Calendar::DEFAULT_THEME_COLOR;
        $data['space_id'] = $spaceId;

        $rules = Arr::only(CalendarRequest::createRules(), self::WRITABLE);
        $rules['space_id'] = 'required|integer|min:1';

        if ($slug) {
            $data['slug'] = $slug;
            $rules['slug'] = CalendarRequest::createRules()['slug'];
        }

        $messages = CalendarRequest::messages();
        $messages['space_id.required'] = __('A space is required.', 'fluent-event');
        $messages['space_id.min'] = __('A space is required.', 'fluent-event');

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->validate()->fails()) {
            throw new ValidationException('Unprocessable Entity!', 422, null, $validator->errors());
        }

        $data['slug'] = $slug ?: static::uniqueSlug($data['title']);
        $data['status'] = 'active';

        // Same meta shaping as the portal's own create, plus our link key.
        $data = CalendarRequest::format(Arr::except($data, ['space_id']));
        $data['meta'][self::SPACE_META_KEY] = $spaceId;

        $calendar = Calendar::create($data);

        do_action('fluent_event/calendar/created', $calendar);

        return static::format($calendar);
    }

    public static function link(int $calendarId, int $spaceId, int $actorId): array
    {
        if (!PermissionManager::userCan('write_calendars', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to link calendars.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        if ($spaceId < 1) {
            throw new ValidationException('Unprocessable Entity!', 422, null, ['space_id' => ['required' => __('A space is required.', 'fluent-event')]]);
        }

        // No DB constraint behind a JSON key, so read and write share a transaction.
        $calendar = DB::transaction(function () use ($calendarId, $spaceId) {
            $calendar = Calendar::query()->find($calendarId);

            if (!$calendar) {
                throw new ValidationException('Unprocessable Entity!', 422, null, ['calendar_id' => ['exists' => __('The selected calendar no longer exists.', 'fluent-event')]]);
            }

            $linkedSpaceId = (int) Arr::get((array) $calendar->meta, self::SPACE_META_KEY);

            if ($linkedSpaceId && $linkedSpaceId !== $spaceId) {
                throw new ValidationException('Unprocessable Entity!', 422, null, ['calendar_id' => ['linked' => __('This calendar is already linked to another space.', 'fluent-event')]]);
            }

            if ($taken = static::matchSpace($spaceId)) {
                if ((int) $taken->id !== (int) $calendar->id) {
                    throw new ValidationException('Unprocessable Entity!', 422, null, ['space_id' => ['linked' => __('This space already has a calendar linked to it.', 'fluent-event')]]);
                }
            }

            $calendar->meta = array_merge((array) $calendar->meta, [self::SPACE_META_KEY => $spaceId]);
            $calendar->save();

            return $calendar;
        });

        do_action('fluent_event/calendar/linked', $calendar, $spaceId);

        return static::format($calendar);
    }

    /**
     * Detaching a calendar from a space that still exists. A deleted space is
     * handled by the `space/deleted` listener instead, with no actor.
     */
    public static function unlink(int $calendarId, int $actorId): bool
    {
        if (!PermissionManager::userCan('write_calendars', $actorId)) {
            throw new ForbiddenHttpException(__('You are not allowed to unlink calendars.', 'fluent-event'), 'fluent_event_permission_denied');
        }

        $calendar = Calendar::query()->find($calendarId);

        if (!$calendar) {
            return false;
        }

        $meta = (array) $calendar->meta;

        if (!isset($meta[self::SPACE_META_KEY])) {
            return false;
        }

        $spaceId = (int) $meta[self::SPACE_META_KEY];

        // Removed, not nulled — `linkable()` tests for the key's absence.
        unset($meta[self::SPACE_META_KEY]);

        $calendar->meta = $meta;
        $calendar->save();

        do_action('fluent_event/calendar/unlinked', $calendar, $spaceId);

        return true;
    }

    public static function linkable(array $args = [], int $actorId = 0): array
    {
        if (!PermissionManager::userCan(['read_calendars', 'write_calendars'], $actorId)) {
            return ['calendars' => [], 'total' => 0];
        }

        $args = array_merge(['search' => '', 'status' => 'active'], $args);

        $calendars = Calendar::query()
            ->searchBy(sanitize_text_field($args['search']))
            ->byStatus(sanitize_text_field($args['status']))
            ->where('meta', 'NOT LIKE', '%"' . self::SPACE_META_KEY . '"%')
            ->withCount('events')
            ->orderBy('title', 'ASC')
            ->get();

        return [
            'calendars' => array_map([CalendarService::class, 'format'], $calendars->all()),
            'total'     => count($calendars),
        ];
    }

    public static function belongsToSpace(int $calendarId, int $spaceId): bool
    {
        if ($calendarId < 1 || $spaceId < 1) {
            return false;
        }

        $calendar = Calendar::query()->find($calendarId);

        return $calendar && (int) Arr::get((array) $calendar->meta, self::SPACE_META_KEY) === $spaceId;
    }

    public static function findBySpace(int $spaceId): ?array
    {
        $calendar = static::matchSpace($spaceId);

        return $calendar ? static::format($calendar) : null;
    }

    public static function matchSpace($spaceId)
    {
        $spaceId = (int) $spaceId;

        if ($spaceId < 1) {
            return null;
        }

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

    public static function format($calendar): array
    {
        $meta = (array) $calendar->meta;

        return [
            'id'             => (int) $calendar->id,
            'title'          => $calendar->title,
            'slug'           => $calendar->slug,
            'description'    => wp_kses_post((string) $calendar->description),
            'status'         => $calendar->status,
            'theme_color'    => Arr::get($meta, 'theme_color', Calendar::DEFAULT_THEME_COLOR),
            'events_count'   => (int) ($calendar->events_count ?? 0),
            'fcom_space_id'  => (int) Arr::get($meta, self::SPACE_META_KEY, 0) ?: null,
        ];
    }

    protected static function uniqueSlug($title)
    {
        $base = sanitize_title($title) ?: 'calendar';
        $slug = $base;
        $suffix = 2;

        while (Calendar::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
