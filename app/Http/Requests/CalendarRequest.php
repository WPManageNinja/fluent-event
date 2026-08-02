<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Calendar;
use FluentEvent\Framework\Support\Arr;

class CalendarRequest
{
    const SORTABLE = ['id', 'title', 'slug', 'status', 'created_at', 'updated_at'];

    public static function sorting($request)
    {
        $orderBy = $request->getSafe('order_by', 'sanitize_text_field', 'id');
        $orderType = strtoupper($request->getSafe('order_type', 'sanitize_text_field', 'DESC'));

        return [
            in_array($orderBy, self::SORTABLE, true) ? $orderBy : 'id',
            $orderType === 'ASC' ? 'ASC' : 'DESC',
        ];
    }

    public static function rules($ignoreId = null)
    {
        $unique = 'unique:fe_calendars,slug';

        if ($ignoreId) {
            $unique .= ',' . $ignoreId . ',id';
        }

        return [
            'title'       => 'required|string|max:255',
            'slug'        => 'required|string|max:255|' . $unique,
            'description' => 'nullable|string',
            'visibility'  => 'required|string|in:' . implode(',', Calendar::VISIBILITIES),
            'status'      => 'required|string|in:' . implode(',', Calendar::STATUSES),
        ];
    }

    public static function messages()
    {
        return [
            'slug.unique' => __('This slug is already taken.', 'fluent-event')
        ];
    }

    public static function sanitize($data, $existingMeta = [])
    {
        return [
            'title'       => sanitize_text_field(Arr::get($data, 'title', '')),
            'slug'        => sanitize_title(Arr::get($data, 'slug') ?: Arr::get($data, 'title')),
            'description' => wp_kses_post(Arr::get($data, 'description', '')),
            'visibility'  => sanitize_text_field(Arr::get($data, 'visibility', 'public')),
            'status'      => sanitize_text_field(Arr::get($data, 'status', 'active')),
            'meta'        => static::meta($data, $existingMeta),
        ];
    }

    public static function meta($data, $existingMeta = [])
    {
        $themeColor = sanitize_hex_color(Arr::get($data, 'theme_color', ''));

        return array_merge(
            (new Calendar)->defaultMeta(),
            (array) $existingMeta,
            array_filter(['theme_color' => $themeColor])
        );
    }
}
