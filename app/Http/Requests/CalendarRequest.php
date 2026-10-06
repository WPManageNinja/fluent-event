<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Calendar;
use FluentEvent\Framework\Support\Arr;

class CalendarRequest extends BaseRequest
{
    const SORTABLE = ['id', 'title', 'slug', 'status', 'created_at', 'updated_at'];
    const ORDER_TYPE = 'DESC';
    const META_KEYS = ['theme_color'];
    const SANITIZERS = [
        'title'       => 'sanitize_text_field',
        'slug'        => 'sanitize_title',
        'description' => 'wp_kses_post',
        'status'      => 'sanitize_text_field',
        'theme_color' => 'sanitize_hex_color',
    ];
    const RULES = [
        'title'       => 'required|string|max:255',
        'slug'        => 'required|string|max:255|unique:fe_calendars,slug',
        'description' => 'nullable|string',
        'status'      => 'required|string|in:' . Calendar::STATUSES,
        'theme_color' => 'nullable|regex:/^#[A-Fa-f0-9]{3}([A-Fa-f0-9]{3})?$/',
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
            'status.required'     => __('Status is required.', 'fluent-event'),
            'status.string'       => __('Status must be text.', 'fluent-event'),
            'status.in'           => __('Status must be public, private or draft.', 'fluent-event'),
            'theme_color.regex'   => __('Theme color must be a hex value like #0099ff.', 'fluent-event'),
        ];
    }

    public static function sanitize($data)
    {
        $data = parent::sanitize($data);

        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title(Arr::get($data, 'title', ''));
        }

        return $data;
    }

    public static function format($data, $existingMeta = [])
    {
        $data['meta'] = array_merge(
            Calendar::defaultMeta(),
            (array) $existingMeta,
            array_filter(Arr::only($data, self::META_KEYS))
        );

        return Arr::except($data, self::META_KEYS);
    }
}
