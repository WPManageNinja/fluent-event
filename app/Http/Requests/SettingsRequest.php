<?php

namespace FluentEvent\App\Http\Requests;

class SettingsRequest extends BaseRequest
{
    const SANITIZERS = [
        'user_id'                  => 'absint',
        'permissions.*'            => 'sanitize_text_field',
        'default_events_view'      => 'sanitize_text_field',
        'disable_event_view_switch' => 'sanitize_text_field',
        'from_name'                => 'sanitize_text_field',
        'from_email'               => 'sanitize_email',
        'reply_to_name'            => 'sanitize_text_field',
        'reply_to_email'           => 'sanitize_email',
        'email_footer'             => 'wp_kses_post',
        'reminder_hours'           => 'absint',
    ];

    const TEAM_RULES = [
        'user_id'     => 'required|integer|exists:users,ID',
        'permissions' => 'required|array',
    ];

    const GENERAL_RULES = [
        'default_events_view'       => 'required|string|in:cards,timeline,calendar',
        'disable_event_view_switch' => 'required|string|in:yes,no',
    ];

    const EMAIL_RULES = [
        'from_name'      => 'required|string',
        'from_email'     => 'required|email',
        'reply_to_name'  => 'nullable|string',
        'reply_to_email' => 'nullable|email',
        'email_footer'   => 'nullable|string',
        'reminder_hours' => 'required|integer|min:1|max:720',
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
            'reminder_hours.required' => __('The reminder time is required.', 'fluent-event'),
            'reminder_hours.integer'  => __('The reminder time must be a number of hours.', 'fluent-event'),
            'reminder_hours.min'      => __('The reminder time must be at least 1 hour.', 'fluent-event'),
            'reminder_hours.max'      => __('The reminder time cannot be more than 720 hours (30 days).', 'fluent-event'),

            'default_events_view.required' => __('Choose a default events view.', 'fluent-event'),
            'default_events_view.in'       => __('The default events view must be cards, timeline or calendar.', 'fluent-event'),
            'disable_event_view_switch.in' => __('The view switch option must be yes or no.', 'fluent-event'),
        ];
    }
}
