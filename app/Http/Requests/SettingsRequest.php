<?php

namespace FluentEvent\App\Http\Requests;

class SettingsRequest extends BaseRequest
{
    const SANITIZERS = [
        'user_id'       => 'absint',
        'permissions.*' => 'sanitize_text_field',
    ];

    const TEAM_RULES = [
        'user_id'     => 'required|integer|exists:users,ID',
        'permissions' => 'required|array',
    ];

    public static function teamRules()
    {
        return self::TEAM_RULES;
    }

    public static function teamMessages()
    {
        return [
            'user_id.required'     => __('Pick a user first.', 'fluent-event'),
            'user_id.integer'      => __('User must be a valid id.', 'fluent-event'),
            'user_id.exists'       => __('The selected user does not exist.', 'fluent-event'),
            'permissions.required' => __('Select at least one permission.', 'fluent-event'),
            'permissions.array'    => __('Permissions must be a list.', 'fluent-event'),
        ];
    }
}
