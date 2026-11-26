<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
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
        'capacity',
        'show_spots_left',
        'waitlist',
        'start_display',
        'registration_opens',
        'registration_closes',
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
        'capacity'           => 'intval',
        'show_spots_left'    => 'sanitize_text_field',
        'waitlist'           => 'sanitize_text_field',
        'start_display'      => 'sanitize_text_field',
        // Not absint: it would turn -7 into 7 and "abc" into 0 before integer|min:0 could refuse them.
        'registration_opens.type'   => 'sanitize_key',
        'registration_opens.value'  => 'wholeNumber',
        'registration_opens.unit'   => 'sanitize_key',
        'registration_opens.at'     => 'sanitize_text_field',
        'registration_closes.type'  => 'sanitize_key',
        'registration_closes.value' => 'wholeNumber',
        'registration_closes.unit'  => 'sanitize_key',
        'registration_closes.at'    => 'sanitize_text_field',
    ];
    const RULES = [
        'calendar_id'        => 'required|exists:fevent_calendars,id',
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
        'capacity'           => 'nullable|integer|min:0',
        'show_spots_left'    => 'nullable|string|in:yes,no',
        'waitlist'           => 'nullable|string|in:yes,no',
        'start_display'      => 'nullable|string|in:relative,countdown',
        'registration_opens'  => 'nullable|array',
        'registration_closes' => 'nullable|array',
    ];

    public static function rules($data)
    {
        $rules = self::RULES;
        $rules['venue'] = 'nullable|string|max:' . Event::venueMaxLength();

        if (Arr::get($data, 'venue_type') === 'online') {
            // One rule at a time: the framework reports every failing rule, so an empty link would get both errors.
            $rules['online_meeting_url'] = Arr::get($data, 'online_meeting_url') ? 'url' : 'required';
        }

        foreach (Event::REGISTRATION_LIMITS as $key => $types) {
            $limit = Arr::get($data, $key);

            if (!$limit || !is_array($limit)) {
                continue;
            }

            $rules[$key . '.type'] = 'nullable|in:' . implode(',', $types);
            $type = Event::registrationLimit($key, $limit)['type'];

            if (in_array($type, ['start', 'end'], true)) {
                $rules[$key . '.value'] = 'required|integer|min:0';
                $rules[$key . '.unit'] = 'required|in:' . implode(',', array_keys(Settings::REMINDER_UNITS));
            } elseif ($type === 'fixed') {
                $rules[$key . '.at'] = 'required';
            }
        }

        return $rules;
    }

    public static function validateDateWindow($data, $existingMeta = [])
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

        $fixedMessages = [
            'registration_opens'  => esc_html__('Registration opens needs a valid date and time.', 'fluent-event'),
            'registration_closes' => esc_html__('Registration closes needs a valid date and time.', 'fluent-event'),
        ];

        foreach ($fixedMessages as $key => $message) {
            $limit = Arr::get($data, $key);

            if (is_array($limit) && Event::registrationLimit($key, $limit)['type'] === 'fixed' && !self::isDateTime(Arr::get($limit, 'at'))) {
                $errors[$key] = ['date' => $message];
            }
        }

        if (!$errors) {
            // Checked on the moments that will be stored, whatever mix of types: an omitted side keeps its saved limit.
            $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));
            $endAt = DateTimeHelper::convertToUtc($data['end_at'], $timezone);
            [$opens, $closes] = array_map(function ($key) use ($data, $existingMeta, $timezone) {
                return array_key_exists($key, $data) ? self::limit($key, $data[$key], $timezone) : Arr::get((array) $existingMeta, $key);
            }, array_keys(Event::REGISTRATION_LIMITS));

            $window = EventService::calculateRegistrationWindow(DateTimeHelper::convertToUtc($data['start_at'], $timezone), $endAt, $timezone, $opens, $closes);

            // An opening "immediately" isn't compared: an event whose registration already closed must stay editable.
            if ($window['opens_at'] && $window['closes_at'] <= $window['opens_at']) {
                $errors['registration_closes'] = ['after' => esc_html__('Registration must close after it opens.', 'fluent-event')];
            } elseif ($window['closes_at'] > $endAt) {
                $errors['registration_closes'] = ['end' => esc_html__('Registration cannot close after the event ends.', 'fluent-event')];
            }
        }

        if ($errors) {
            throw new ValidationException('Unprocessable Entity!', 422, null, $errors); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messages escaped with esc_html__() above; returned as REST JSON
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
            'capacity.integer'            => __('Capacity must be a whole number.', 'fluent-event'),
            'capacity.min'                => __('Capacity cannot be negative.', 'fluent-event'),
            'show_spots_left.in'          => __('Show spots left must be yes or no.', 'fluent-event'),
            'waitlist.in'                 => __('Waitlist must be yes or no.', 'fluent-event'),
            'start_display.in'            => __('Before the start must show the time left or a countdown.', 'fluent-event'),
            'registration_opens.type.in'         => __('Registration opens must be immediately, before the start or on a fixed date.', 'fluent-event'),
            'registration_opens.at.required'     => __('Registration opens needs a date and time.', 'fluent-event'),
            'registration_closes.type.in'        => __('Registration closes must be when the event ends, before the start or end, or on a fixed date.', 'fluent-event'),
            'registration_closes.at.required'    => __('Registration closes needs a date and time.', 'fluent-event'),
            'registration_opens.value.required'  => __('Registration opens needs a number.', 'fluent-event'),
            'registration_opens.value.integer'   => __('Registration opens must be a whole number.', 'fluent-event'),
            'registration_opens.value.min'       => __('Registration opens cannot be negative.', 'fluent-event'),
            'registration_opens.unit.required'   => __('Registration opens needs minutes, hours or days.', 'fluent-event'),
            'registration_opens.unit.in'         => __('Registration opens must be in minutes, hours or days.', 'fluent-event'),
            'registration_closes.value.required' => __('Registration closes needs a number.', 'fluent-event'),
            'registration_closes.value.integer'  => __('Registration closes must be a whole number.', 'fluent-event'),
            'registration_closes.value.min'      => __('Registration closes cannot be negative.', 'fluent-event'),
            'registration_closes.unit.required'  => __('Registration closes needs minutes, hours or days.', 'fluent-event'),
            'registration_closes.unit.in'        => __('Registration closes must be in minutes, hours or days.', 'fluent-event'),
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

        $timezone = DateTimeHelper::getValidatedTimeZone(Arr::get($data, 'timezone'));

        foreach (array_keys(Event::REGISTRATION_LIMITS) as $key) {
            if (array_key_exists($key, $meta)) {
                $meta[$key] = self::limit($key, $meta[$key], $timezone);
            }
        }

        return $meta;
    }

    // A limit as sent: a fixed date comes on the event's clock and is kept in UTC, like every stored moment.
    protected static function limit($key, $limit, $timezone)
    {
        $limit = Event::registrationLimit($key, $limit);
        $limit['value'] = $limit['value'] === null ? null : (int) $limit['value'];
        $limit['at'] = $limit['type'] === 'fixed' ? EventService::wallClockToUtc($limit['at'], $timezone) : null;

        return $limit;
    }

    protected static function isDateTime($value)
    {
        $parts = date_parse((string) $value);

        return $value && strtotime($value) !== false && !$parts['error_count'] && checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year']);
    }
}
