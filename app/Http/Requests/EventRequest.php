<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\Settings;
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
        'entry_type',
        'show_guest_count',
        'capacity',
        'waitlist',
        'registration_opens',
        'registration_closes',
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
        'cover_url'          => 'web_url',
        'online_meeting_url' => 'web_url',
        'venue'              => 'sanitize_text_field',
        'address'            => 'sanitize_textarea_field',
        'entry_type'         => 'sanitize_text_field',
        'show_guest_count'   => 'sanitize_text_field',
        'capacity'           => 'intval',
        'waitlist'           => 'sanitize_text_field',
        'registration_opens.value'  => 'absint',
        'registration_opens.unit'   => 'sanitize_key',
        'registration_closes.value' => 'absint',
        'registration_closes.unit'  => 'sanitize_key',
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
        'entry_type'         => 'required|string|in:' . Event::ENTRY_TYPES,
        'show_guest_count'   => 'nullable|string|in:yes,no',
        'capacity'           => 'nullable|integer|min:0',
        'waitlist'           => 'nullable|string|in:yes,no',
        'registration_opens'  => 'nullable|array',
        'registration_closes' => 'nullable|array',
    ];

    public static function rules($data)
    {
        $rules = self::RULES;

        if (Arr::get($data, 'venue_type') === 'online') {
            $rules['online_meeting_url'] = 'required|url';
        }

        foreach (Event::REGISTRATION_LIMITS as $key) {
            if (Arr::get($data, $key)) {
                $rules[$key . '.value'] = 'required|integer|min:0';
                $rules[$key . '.unit'] = 'required|in:' . implode(',', array_keys(Settings::REMINDER_UNITS));
            }
        }

        return $rules;
    }

    public static function validateDateWindow($data, $existingMeta = [])
    {
        $start = strtotime(Arr::get($data, 'start_at'));
        $end = strtotime(Arr::get($data, 'end_at'));

        if (!$start || !$end || $end <= $start) {
            throw new ValidationException('Unprocessable Entity!', 422, null, [
                'end_at' => [
                    'after' => esc_html__('End must be after the start.', 'fluent-event')
                ]
            ]);
        }

        // An omitted side keeps its saved offset (see meta()), so check the pair that will be stored; null clears.
        [$opens, $closes] = array_map(function ($key) use ($data, $existingMeta) {
            return array_key_exists($key, $data) ? $data[$key] : Arr::get((array) $existingMeta, $key);
        }, Event::REGISTRATION_LIMITS);

        // Both are offsets before the start, so opening first means the larger offset.

        if ($opens && $closes && Settings::reminderInSeconds($opens) <= Settings::reminderInSeconds($closes)) {
            throw new ValidationException('Unprocessable Entity!', 422, null, [
                'registration_closes' => [
                    'after' => esc_html__('Registration must close after it opens.', 'fluent-event')
                ]
            ]);
        }
    }

    public static function messages()
    {
        return [
            'calendar_id.required'        => __('Please choose a calendar for this event.', 'fluent-event'),
            'calendar_id.exists'          => __('The selected calendar no longer exists.', 'fluent-event'),
            'title.required'              => __('Event title is required.', 'fluent-event'),
            'title.string'                => __('Event title must be text.', 'fluent-event'),
            'title.max'                   => __('Event title cannot exceed 255 characters.', 'fluent-event'),
            'description.string'          => __('Event Description must be text.', 'fluent-event'),
            'status.required'             => __('Status is required.', 'fluent-event'),
            'status.in'                   => __('Status must be public, private or draft.', 'fluent-event'),
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
            'entry_type.required'         => __('Registration type is required.', 'fluent-event'),
            'entry_type.in'               => __('Registration must be RSVP or open.', 'fluent-event'),
            'show_guest_count.in'         => __('Show guest count must be yes or no.', 'fluent-event'),
            'capacity.integer'            => __('Capacity must be a whole number.', 'fluent-event'),
            'capacity.min'                => __('Capacity cannot be negative.', 'fluent-event'),
            'waitlist.in'                 => __('Waitlist must be yes or no.', 'fluent-event'),
            'registration_opens.value.integer' => __('Registration opens must be a whole number.', 'fluent-event'),
            'registration_opens.value.min' => __('Registration opens cannot be negative.', 'fluent-event'),
            'registration_opens.unit.in' => __('Registration opens must be in minutes, hours or days.', 'fluent-event'),
            'registration_closes.value.integer' => __('Registration closes must be a whole number.', 'fluent-event'),
            'registration_closes.value.min' => __('Registration closes cannot be negative.', 'fluent-event'),
            'registration_closes.unit.in' => __('Registration closes must be in minutes, hours or days.', 'fluent-event'),
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
            self::meta($data)
        );

        return $formatted;
    }

    protected static function meta($data)
    {
        $meta = Arr::only($data, self::META_KEYS);

        $unused = Arr::get($data, 'venue_type') === 'online'
            ? ['venue', 'address']
            : ['online_meeting_url'];

        foreach ($unused as $key) {
            $meta[$key] = '';
        }

        // Empty means no limit on that side; otherwise keep only the offset itself.
        foreach (Event::REGISTRATION_LIMITS as $key) {
            if (array_key_exists($key, $meta)) {
                $meta[$key] = Arr::get($meta, $key . '.unit') ? Arr::only($meta[$key], ['value', 'unit']) : null;
            }
        }

        return $meta;
    }
}
