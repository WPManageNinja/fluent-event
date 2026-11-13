<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Utils\Utility;
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
        'description_md',
    ];
    const SANITIZERS = [
        'calendar_id'        => 'intval',
        'title'              => 'sanitize_text_field',
        'description_md'     => 'markdown',
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
    ];
    const RULES = [
        'calendar_id'        => 'required|exists:fe_calendars,id',
        'title'              => 'required|string|max:255',
        'description_md'     => 'nullable|string',
        'status'             => 'required|string|in:' . Event::STATUSES,
        // No `date` rule: the framework ignores a custom message for it, so validateDateWindow() checks the dates.
        'start_at'           => 'required',
        'end_at'             => 'required',
        'timezone'           => 'nullable|string|max:64',
        'venue_type'         => 'required|string|in:' . Event::VENUE_TYPES,
        'cover_url'          => 'nullable|url',
        'online_meeting_url' => 'nullable|url',
        'venue'              => 'nullable|string',
        'address'            => 'nullable|string|max:500',
        'entry_type'         => 'required|string|in:' . Event::ENTRY_TYPES,
        'show_guest_count'   => 'nullable|string|in:yes,no',
    ];

    public static function rules($data)
    {
        $rules = self::RULES;
        $rules['venue'] = 'nullable|string|max:' . Event::venueMaxLength();

        if (Arr::get($data, 'venue_type') === 'online') {
            // One rule at a time: the framework reports every failing rule, so an empty link would get both errors.
            $rules['online_meeting_url'] = Arr::get($data, 'online_meeting_url') ? 'url' : 'required';
        }

        return $rules;
    }

    public static function validateDateWindow($data)
    {
        $errors = [];
        $messages = [
            'start_at' => esc_html__('Start must be a valid date and time.', 'fluent-event'),
            'end_at'   => esc_html__('End must be a valid date and time.', 'fluent-event'),
        ];

        foreach ($messages as $field => $message) {
            $value = (string) Arr::get($data, $field);
            $parts = date_parse($value);

            if (strtotime($value) === false || !checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])) {
                $errors[$field] = ['date' => $message];
            }
        }

        if (!$errors && strtotime($data['end_at']) <= strtotime($data['start_at'])) {
            $errors['end_at'] = ['after' => esc_html__('End must be after the start.', 'fluent-event')];
        }

        if ($errors) {
            throw new ValidationException('Unprocessable Entity!', 422, null, $errors);
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
            'description_md.string'       => __('Event Description must be text.', 'fluent-event'),
            'status.required'             => __('Status is required.', 'fluent-event'),
            'status.in'                   => __('Status must be public, unlisted, private or draft.', 'fluent-event'),
            'start_at.required'           => __('Start date and time are required.', 'fluent-event'),
            'end_at.required'             => __('End date and time are required.', 'fluent-event'),
            'timezone.max'                => __('Timezone cannot exceed 64 characters.', 'fluent-event'),
            'venue_type.required'         => __('Location type is required.', 'fluent-event'),
            'venue_type.in'               => __('Location type must be offline or online.', 'fluent-event'),
            'cover_url.url'               => __('Cover image must be a valid URL.', 'fluent-event'),
            'online_meeting_url.required' => __('An online event needs a meeting link.', 'fluent-event'),
            'online_meeting_url.url'      => __('Meeting link must be a valid http or https URL.', 'fluent-event'),
            /* translators: %d: the longest venue name allowed, in characters */
            'venue.max'                   => sprintf(__('Venue name cannot exceed %d characters.', 'fluent-event'), Event::venueMaxLength()),
            'address.max'                 => __('Address cannot exceed 500 characters.', 'fluent-event'),
            'entry_type.required'         => __('Registration type is required.', 'fluent-event'),
            'entry_type.in'               => __('Registration must be RSVP, open or none.', 'fluent-event'),
            'show_guest_count.in'         => __('Show guest count must be yes or no.', 'fluent-event'),
        ];
    }

    public static function format($data, $existingMeta = [])
    {
        $formatted = Arr::only(
            $data, array_merge(self::COLUMNS, ['title'])
        );

        // The markdown is the source; the post keeps the HTML every page, email and calendar file reads.
        if (array_key_exists('description_md', $data)) {
            $formatted['description'] = Utility::mdToHtml($data['description_md']);
        }

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

        return $meta;
    }
}
