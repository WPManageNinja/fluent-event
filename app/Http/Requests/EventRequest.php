<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Event;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Validator\ValidationException;

class EventRequest extends BaseRequest
{
    const SORTABLE = ['id', 'start_at', 'end_at', 'created_at', 'updated_at'];
    const COLUMNS = [
        'calendar_id',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'venue_type',
    ];
    const META_KEYS = [
        'cover_url',
        'online_meeting_url',
        'venue',
        'address',
    ];
    const SANITIZERS = [
        'calendar_id'        => 'intval',
        'title'              => 'sanitize_text_field',
        'description'        => 'wp_kses_post',
        'status'             => 'sanitize_text_field',
        'start_at'           => 'sanitize_text_field',
        'end_at'             => 'sanitize_text_field',
        'timezone'           => 'sanitize_text_field',
        'venue_type'         => 'sanitize_text_field',
        'cover_url'          => 'esc_url_raw',
        'online_meeting_url' => 'web_url',
        'venue'              => 'sanitize_text_field',
        'address'            => 'sanitize_textarea_field',
    ];
    const RULES = [
        'calendar_id'        => 'required|exists:fe_calendars,id',
        'title'              => 'required|string|max:255',
        'description'        => 'nullable|string',
        'status'             => 'required|string|in:' . Event::STATUSES,
        'start_at'           => 'required|date',
        'end_at'             => 'required|date',
        'timezone'           => 'nullable|string|max:64',
        'venue_type'         => 'required|string|in:' . Event::VENUE_TYPES,
        'cover_url'          => 'nullable|url',
        'online_meeting_url' => 'nullable|url',
        'venue'              => 'nullable|string|max:255',
        'address'            => 'nullable|string|max:500',
    ];

    public static function rules($data)
    {
        $rules = self::RULES;

        if (Arr::get($data, 'venue_type') === 'online') {
            $rules['online_meeting_url'] = 'required|url';
        }

        return $rules;
    }

    public static function validateDateWindow($data)
    {
        $start = strtotime(Arr::get($data, 'start_at'));
        $end = strtotime(Arr::get($data, 'end_at'));

        if ($start && $end && $end > $start) {
            return;
        }

        throw new ValidationException('Unprocessable Entity!', 422, null, [
            'end_at' => [
                'after' => __('End must be after the start.', 'fluent-event')
            ]
        ]);
    }

    public static function messages()
    {
        return [
            'calendar_id.required'        => __('Please choose a calendar for this event.', 'fluent-event'),
            'calendar_id.exists'          => __('The selected calendar no longer exists.', 'fluent-event'),
            'title.required'              => __('Title is required.', 'fluent-event'),
            'title.string'                => __('Title must be text.', 'fluent-event'),
            'title.max'                   => __('Title cannot exceed 255 characters.', 'fluent-event'),
            'description.string'          => __('Description must be text.', 'fluent-event'),
            'status.required'             => __('Status is required.', 'fluent-event'),
            'status.in'                   => __('Status must be public, private, draft or archived.', 'fluent-event'),
            'start_at.required'           => __('Start date and time are required.', 'fluent-event'),
            'start_at.date'               => __('Start must be a valid date and time.', 'fluent-event'),
            'end_at.required'             => __('End date and time are required.', 'fluent-event'),
            'end_at.date'                 => __('End must be a valid date and time.', 'fluent-event'),
            'timezone.max'                => __('Timezone cannot exceed 64 characters.', 'fluent-event'),
            'venue_type.required'         => __('Location type is required.', 'fluent-event'),
            'venue_type.in'               => __('Location type must be offline or online.', 'fluent-event'),
            'cover_url.url'               => __('Cover image must be a valid URL.', 'fluent-event'),
            'online_meeting_url.required' => __('An online event needs a meeting link.', 'fluent-event'),
            'online_meeting_url.url'      => __('Meeting link must be a valid http or https URL.', 'fluent-event'),
            'venue.max'                   => __('Venue name cannot exceed 255 characters.', 'fluent-event'),
            'address.max'                 => __('Address cannot exceed 500 characters.', 'fluent-event'),
        ];
    }

    public static function format($data, $existingMeta = [])
    {
        $formatted = Arr::only(
            $data, array_merge(self::COLUMNS, ['title', 'description'])
        );

        $formatted['meta'] = array_merge(
            Event::defaultMeta(),
            (array) $existingMeta,
            self::locationMeta($data)
        );

        return $formatted;
    }

    protected static function locationMeta($data)
    {
        $meta = Arr::only($data, self::META_KEYS);

        $unused = Arr::get($data, 'venue_type') === 'online'
            ? ['venue', 'address']
            : ['online_meeting_url'];

        foreach ($unused as $key) {
            $meta[$key] = '';
        }

        return $meta;
    }
}
