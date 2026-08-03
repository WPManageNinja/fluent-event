<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Utils\Sanitizer;
use FluentEvent\Framework\Support\Arr;

class CalendarRequest extends BaseRequest
{
    const SORTABLE = ['id', 'title', 'slug', 'status', 'created_at', 'updated_at'];
    const META_KEYS = ['theme_color'];
    const SANITIZERS = [
        'title'       => Sanitizer::SANITIZE_TEXT_FIELD,
        'slug'        => Sanitizer::SANITIZE_TITLE,
        'description' => Sanitizer::WP_KSES_POST,
        'visibility'  => Sanitizer::SANITIZE_TEXT_FIELD,
        'status'      => Sanitizer::SANITIZE_TEXT_FIELD,
        'theme_color' => Sanitizer::SANITIZE_HEX_COLOR,
    ];
    const RULES = [
        'title'       => 'required|string|max:255',
        'slug'        => 'required|string|max:255|unique:fe_calendars,slug',
        'description' => 'nullable|string',
        'visibility'  => 'required|string|in:' . Calendar::VISIBILITIES,
        'status'      => 'required|string|in:' . Calendar::STATUSES,
    ];

    public static function createRules()
    {
        return self::RULES;
    }

    public static function updateRules($currentCalendarId)
    {
        return array_merge(self::RULES, [
            'slug' => 'required|string|max:255|unique:fe_calendars,slug,' . $currentCalendarId . ',id',
        ]);
    }

    public static function messages()
    {
        return [
            'title.required'      => __('Title is required.', 'fluent-event'),
            'title.string'        => __('Title must be text.', 'fluent-event'),
            'title.max'           => __('Title cannot exceed 255 characters.', 'fluent-event'),
            'slug.required'       => __('Slug is required.', 'fluent-event'),
            'slug.string'         => __('Slug must be text.', 'fluent-event'),
            'slug.max'            => __('Slug cannot exceed 255 characters.', 'fluent-event'),
            'slug.unique'         => __('This slug is already taken.', 'fluent-event'),
            'description.string'  => __('Description must be text.', 'fluent-event'),
            'visibility.required' => __('Visibility is required.', 'fluent-event'),
            'visibility.string'   => __('Visibility must be text.', 'fluent-event'),
            'visibility.in'       => __('Visibility must be public, private or unlisted.', 'fluent-event'),
            'status.required'     => __('Status is required.', 'fluent-event'),
            'status.string'       => __('Status must be text.', 'fluent-event'),
            'status.in'           => __('Status must be active, archived or draft.', 'fluent-event'),
        ];
    }

    public static function sanitize($data)
    {
        $sanitized = [];

        foreach (self::SANITIZERS as $field => $callback) {
            if (array_key_exists($field, $data)) {
                $sanitized[$field] = Sanitizer::sanitize($data[$field], $callback);
            }
        }

        return $sanitized;
    }

    public static function format($data, $existingMeta = [])
    {
        $data['meta'] = array_merge(
            (new Calendar)->defaultMeta(),
            (array) $existingMeta,
            array_filter(Arr::only($data, self::META_KEYS))
        );

        return Arr::except($data, self::META_KEYS);
    }
}
