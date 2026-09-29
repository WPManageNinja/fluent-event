<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Guest;

class GuestRequest extends BaseRequest
{
    const ORDER_BY = 'id';
    const ORDER_TYPE = 'DESC';
    const SORTABLE = ['id', 'name', 'email', 'status', 'created_at'];
    const COLUMNS = [
        'id',
        'event_id',
        'name',
        'email',
        'user_id',
        'status',
        'source',
        'checked_in_at',
        'created_at',
        'updated_at',
    ];
    const SANITIZERS = [
        'name'   => 'sanitize_text_field',
        'email'  => 'sanitize_email',
        'status' => 'sanitize_text_field',
        'user_id' => 'intval',
        'source'  => 'sanitize_text_field',
    ];
    const RULES = [
        'name'   => 'required|string|max:255',
        'email'  => 'required|email|max:192',
        'status' => 'nullable|string|in:' . Guest::STATUSES,
        'user_id' => 'nullable|integer|exists:users,ID',
        'source'  => 'nullable|string|max:64',
    ];

    public static function createRules($eventId)
    {
        return array_merge(self::RULES, [
            'email'   => 'required|email|max:192|unique:fe_guests,email,NULL,id,event_id,' . $eventId,
            'user_id' => 'nullable|integer|exists:users,ID|unique:fe_guests,user_id,NULL,id,event_id,' . $eventId,
        ]);
    }

    public static function updateRules($eventId, $currentGuestId)
    {
        return array_merge(self::RULES, [
            'email'   => 'required|email|max:192|unique:fe_guests,email,' . $currentGuestId . ',id,event_id,' . $eventId,
            'user_id' => 'nullable|integer|exists:users,ID|unique:fe_guests,user_id,' . $currentGuestId . ',id,event_id,' . $eventId,
        ]);
    }

    public static function messages()
    {
        return [
            'name.required'  => __('Name is required.', 'fluent-event'),
            'name.string'    => __('Name must be text.', 'fluent-event'),
            'name.max'       => __('Name cannot exceed 255 characters.', 'fluent-event'),
            'email.required' => __('Email is required.', 'fluent-event'),
            'email.email'    => __('Please provide a valid email address.', 'fluent-event'),
            'email.max'      => __('Email cannot exceed 192 characters.', 'fluent-event'),
            'email.unique'   => __('A guest with this email is already on this event.', 'fluent-event'),
            'user_id.integer' => __('The WordPress user must be a valid id.', 'fluent-event'),
            'user_id.exists' => __('The selected WordPress user no longer exists.', 'fluent-event'),
            'user_id.unique' => __('This WordPress user is already a guest on this event.', 'fluent-event'),
            'status.in'      => __('Status must be going, not going, checked in or cancelled.', 'fluent-event'),
            'source.string'  => __('Source must be text.', 'fluent-event'),
            'source.max'     => __('Source cannot exceed 64 characters.', 'fluent-event'),
        ];
    }
}
