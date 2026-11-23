<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Support\Arr;

class SettingsRequest extends BaseRequest
{
    const SANITIZERS = [
        'default_events_view'      => 'sanitize_text_field',
        'disable_event_view_switch' => 'sanitize_text_field',
        'events_page_id'           => 'absint',
        'event_slug'               => 'sanitize_title',
        'from_name'                => 'sanitize_text_field',
        'from_email'               => 'sanitize_email',
        'reply_to_name'            => 'sanitize_text_field',
        'reply_to_email'           => 'sanitize_email',
        'email_footer'             => 'wp_kses_post',
    ];

    // `sometimes`: the view settings and the public pages save from separate boxes, each sending only its own fields.
    const GENERAL_RULES = [
        'default_events_view'       => 'sometimes|required|string|in:cards,timeline,calendar',
        'disable_event_view_switch' => 'sometimes|required|string|in:yes,no',
        'events_page_id'            => 'nullable|integer',
    ];

    const EMAIL_RULES = [
        'from_name'      => 'required|string',
        'reply_to_name'  => 'nullable|string',
        'reply_to_email' => 'nullable|email',
        'email_footer'   => 'nullable|string',
    ];

    const EMAIL_NOTIFICATION_RULES = [
        'registration_confirmation'       => 'nullable|array',
        'registration_cancellation'       => 'nullable|array',
        'reminder_to_guest'               => 'required|array',
        'reminder_to_guest.enabled'       => 'required',
        'reminder_to_guest.times'         => 'required|array|max:' . Settings::MAX_REMINDERS,
        'reminder_to_guest.times.*.unit'  => 'required|string|in:minutes,hours,days',
    ];

    // An empty field gets `required` alone: the framework reports every failing rule, so it would also fail the format rule.
    public static function generalRules($data)
    {
        return self::GENERAL_RULES + [
            'event_slug' => Arr::get($data, 'event_slug') ? 'sometimes|alphadash|max:100' : 'sometimes|required',
        ];
    }

    public static function emailRules($data)
    {
        return self::EMAIL_RULES + [
            'from_email' => Arr::get($data, 'from_email') ? 'email' : 'required',
        ];
    }

    public static function emailNotificationRules()
    {
        return array_merge(self::EMAIL_NOTIFICATION_RULES, [
            // The time is checked here, one step at a time: as separate rules, an empty one failed three of them at once.
            'reminder_to_guest.times.*' => [function ($attribute, $reminder) {
                $value = Arr::get((array) $reminder, 'value');

                if ($value === null || $value === '') {
                    return __('The guest reminder time is required.', 'fluent-event');
                }

                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    return __('The reminder time must be a whole number.', 'fluent-event');
                }

                if ($value < 1) {
                    return __('The reminder time must be at least 1.', 'fluent-event');
                }

                $seconds = Settings::reminderInSeconds($reminder);

                if ($seconds && !Settings::isReminderTimeAllowed($seconds)) {
                    return __('The reminder must be between 5 minutes and 30 days before the event.', 'fluent-event');
                }
            }],
        ]);
    }

    // Rebuilt from the known keys, so nothing else sent inside these settings is stored.
    public static function emailNotifications($data)
    {
        $settings = [];

        foreach (['registration_confirmation', 'registration_cancellation'] as $key) {
            if (is_array(Arr::get($data, $key))) {
                $settings[$key] = ['enabled' => Arr::isTrue($data, $key . '.enabled')];
            }
        }

        $settings['reminder_to_guest'] = [
            'enabled' => Arr::isTrue($data, 'reminder_to_guest.enabled'),
            'times'   => array_map(function ($reminder) {
                return [
                    'value' => absint(Arr::get((array) $reminder, 'value')),
                    'unit'  => sanitize_key((string) Arr::get((array) $reminder, 'unit')),
                ];
            }, array_values((array) Arr::get($data, 'reminder_to_guest.times', []))),
        ];

        return $settings;
    }

    public static function messages()
    {
        return [
            'from_name.required'      => __('The from name is required.', 'fluent-event'),
            'from_email.required'     => __('The from email address is required.', 'fluent-event'),
            'from_email.email'        => __('The from email must be a valid email address.', 'fluent-event'),
            'reply_to_email.email'    => __('The reply-to email must be a valid email address.', 'fluent-event'),
            'reminder_to_guest.required'               => __('The guest reminder settings are required.', 'fluent-event'),
            'reminder_to_guest.enabled.required'       => __('Choose whether guest reminders are on or off.', 'fluent-event'),
            'reminder_to_guest.times.required'         => __('Add an guest email reminder.', 'fluent-event'),
            'reminder_to_guest.times.array'            => __('Guest email reminders must be a list.', 'fluent-event'),
            'reminder_to_guest.times.max'              => __('Max guest email reminder reached.', 'fluent-event'),
            'reminder_to_guest.times.*.unit.required'  => __('Choose minutes, hours or days for the reminder.', 'fluent-event'),
            'reminder_to_guest.times.*.unit.in'        => __('The reminder unit must be minutes, hours or days.', 'fluent-event'),

            'default_events_view.required' => __('Choose a default events view.', 'fluent-event'),
            'default_events_view.in'       => __('The default events view must be cards, timeline or calendar.', 'fluent-event'),
            'disable_event_view_switch.in' => __('The view switch option must be yes or no.', 'fluent-event'),
            'events_page_id.integer'       => __('Choose a published page.', 'fluent-event'),
            'event_slug.required'          => __('Enter an event slug.', 'fluent-event'),
            'event_slug.alphadash'         => __('The event slug can only use letters, numbers, hyphens and underscores.', 'fluent-event'),
            'event_slug.max'               => __('The event slug cannot exceed 100 characters.', 'fluent-event'),
            'key.required'                 => __('Choose which page to create.', 'fluent-event'),
            'key.in'                       => __('Choose which page to create.', 'fluent-event'),
            'title.required'               => __('Enter a page title.', 'fluent-event'),
            'title.max'                    => __('The page title cannot exceed 255 characters.', 'fluent-event'),
        ];
    }
}
