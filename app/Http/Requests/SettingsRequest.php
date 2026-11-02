<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Services\Settings;

class SettingsRequest extends BaseRequest
{
    const SANITIZERS = [
        'user_id'                  => 'absint',
        'permissions.*'            => 'sanitize_text_field',
        'default_events_view'      => 'sanitize_text_field',
        'disable_event_view_switch' => 'sanitize_text_field',
        'portal_slug'              => 'sanitize_title',
        'from_name'                => 'sanitize_text_field',
        'from_email'               => 'sanitize_email',
        'reply_to_name'            => 'sanitize_text_field',
        'reply_to_email'           => 'sanitize_email',
        'email_footer'             => 'wp_kses_post',
        'registration_confirmation.enabled' => 'rest_sanitize_boolean',
        'registration_cancellation.enabled' => 'rest_sanitize_boolean',
        'reminder_to_guest.enabled'       => 'rest_sanitize_boolean',
        'reminder_to_guest.times.*.value' => 'absint',
        'reminder_to_guest.times.*.unit'  => 'sanitize_key',
    ];

    const TEAM_RULES = [
        'user_id'     => 'required|integer|exists:users,ID',
        'permissions' => 'required|array',
    ];

    const GENERAL_RULES = [
        'default_events_view'       => 'required|string|in:cards,timeline,calendar',
        'disable_event_view_switch' => 'required|string|in:yes,no',
        'portal_slug'               => 'required|string|max:100|regex:/^[a-z0-9_-]+$/',
    ];

    const EMAIL_RULES = [
        'from_name'      => 'required|string',
        'from_email'     => 'required|email',
        'reply_to_name'  => 'nullable|string',
        'reply_to_email' => 'nullable|email',
        'email_footer'   => 'nullable|string',
    ];

    const EMAIL_NOTIFICATION_RULES = [
        'registration_confirmation'       => 'nullable|array',
        'registration_cancellation'       => 'nullable|array',
        'reminder_to_guest'               => 'required|array',
        'reminder_to_guest.enabled'       => 'required',
        'reminder_to_guest.times'         => 'required|array|max:5',
        'reminder_to_guest.times.*.value' => 'required|integer|min:1',
        'reminder_to_guest.times.*.unit'  => 'required|string|in:minutes,hours,days',
    ];

    public static function teamRules()
    {
        return self::TEAM_RULES;
    }

    public static function generalRules()
    {
        return self::GENERAL_RULES;
    }

    public static function emailRules()
    {
        return self::EMAIL_RULES;
    }

    public static function emailNotificationRules()
    {
        return array_merge(self::EMAIL_NOTIFICATION_RULES, [
            'reminder_to_guest.times.*' => [function ($attribute, $reminder) {
                $seconds = Settings::reminderInSeconds($reminder);

                if ($seconds && ($seconds < 5 * MINUTE_IN_SECONDS || $seconds > Settings::MAX_REMINDER_TIME)) {
                    return __('The reminder must be between 5 minutes and 30 days before the event.', 'fluent-event');
                }
            }],
        ]);
    }

    public static function messages()
    {
        return [
            'user_id.required'        => __('Pick a user first.', 'fluent-event'),
            'user_id.integer'         => __('User must be a valid id.', 'fluent-event'),
            'user_id.exists'          => __('The selected user does not exist.', 'fluent-event'),
            'permissions.required'    => __('Select at least one permission.', 'fluent-event'),
            'permissions.array'       => __('Permissions must be a list.', 'fluent-event'),
            'from_name.required'      => __('The from name is required.', 'fluent-event'),
            'from_email.required'     => __('The from email address is required.', 'fluent-event'),
            'from_email.email'        => __('The from email must be a valid email address.', 'fluent-event'),
            'reply_to_email.email'    => __('The reply-to email must be a valid email address.', 'fluent-event'),
            'reminder_to_guest.required'               => __('The guest reminder settings are required.', 'fluent-event'),
            'reminder_to_guest.enabled.required'       => __('Choose whether guest reminders are on or off.', 'fluent-event'),
            'reminder_to_guest.times.required'         => __('Add an guest email reminder.', 'fluent-event'),
            'reminder_to_guest.times.array'            => __('Guest email reminders must be a list.', 'fluent-event'),
            'reminder_to_guest.times.max'              => __('Max guest email reminder reached.', 'fluent-event'),
            'reminder_to_guest.times.*.value.required' => __('The guest reminder time is required.', 'fluent-event'),
            'reminder_to_guest.times.*.value.integer'  => __('The reminder time must be a whole number.', 'fluent-event'),
            'reminder_to_guest.times.*.value.min'      => __('The reminder time must be at least 1.', 'fluent-event'),
            'reminder_to_guest.times.*.unit.required'  => __('Choose minutes, hours or days for the reminder.', 'fluent-event'),
            'reminder_to_guest.times.*.unit.in'        => __('The reminder unit must be minutes, hours or days.', 'fluent-event'),

            'default_events_view.required' => __('Choose a default events view.', 'fluent-event'),
            'default_events_view.in'       => __('The default events view must be cards, timeline or calendar.', 'fluent-event'),
            'disable_event_view_switch.in' => __('The view switch option must be yes or no.', 'fluent-event'),
            'portal_slug.required'         => __('The portal slug is required.', 'fluent-event'),
            'portal_slug.max'              => __('The portal slug cannot exceed 100 characters.', 'fluent-event'),
            'portal_slug.regex'            => __('The portal slug can only use letters, numbers, hyphens and underscores.', 'fluent-event'),
        ];
    }
}
