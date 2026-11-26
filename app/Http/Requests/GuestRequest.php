<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Models\Guest;
use FluentEvent\Framework\Support\Arr;

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
        'action'  => 'sanitize_text_field',
        'guest_ids.*' => 'absint',
    ];
    const RULES = [
        'name'   => 'required|string|max:255',
        'email'  => 'required|email|max:191',
        'status' => 'nullable|string|in:' . Guest::STATUSES,
        'user_id' => 'nullable|integer|exists:users,ID',
    ];

    public static function createRules($eventId)
    {
        return array_merge(self::RULES, [
            'email'   => 'required|email|max:191|unique:fevent_guests,email,NULL,id,event_id,' . $eventId,
            'user_id' => 'nullable|integer|exists:users,ID|unique:fevent_guests,user_id,NULL,id,event_id,' . $eventId,
        ]);
    }

    public static function updateRules($eventId, $currentGuestId)
    {
        // Updates are partial, so name and email are required only when sent.
        return array_merge(self::RULES, [
            'name'    => 'sometimes|required|string|max:255',
            'email'   => 'sometimes|required|email|max:191|unique:fevent_guests,email,' . $currentGuestId . ',id,event_id,' . $eventId,
            'user_id' => 'nullable|integer|exists:users,ID|unique:fevent_guests,user_id,' . $currentGuestId . ',id,event_id,' . $eventId,
        ]);
    }

    public static function bulkRules($data)
    {
        $rules = [
            'action'    => 'required|in:change_status,delete',
            'guest_ids' => 'required|array|max:' . self::PER_PAGE_MAX,
        ];

        if (Arr::get($data, 'action') === 'change_status') {
            $rules['status'] = 'required|in:' . Guest::STATUSES;
        }

        return $rules;
    }

    public static function messages()
    {
        return [
            'name.required'  => __('Name is required.', 'fluent-event'),
            'name.string'    => __('Name must be text.', 'fluent-event'),
            'name.max'       => __('Name cannot exceed 255 characters.', 'fluent-event'),
            'email.required' => __('Email is required.', 'fluent-event'),
            'email.email'    => __('Please provide a valid email address.', 'fluent-event'),
            'email.max'      => __('Email cannot exceed 191 characters.', 'fluent-event'),
            'email.unique'   => __('A guest with this email is already on this event.', 'fluent-event'),
            'user_id.integer' => __('The WordPress user must be a valid id.', 'fluent-event'),
            'user_id.exists' => __('The selected WordPress user no longer exists.', 'fluent-event'),
            'user_id.unique' => __('This WordPress user is already a guest on this event.', 'fluent-event'),
            'status.in'      => __('Status must be going, not going, checked in, waitlisted or cancelled.', 'fluent-event'),
            'action.required' => __('Choose a bulk action.', 'fluent-event'),
            'action.in'      => __('Choose a valid bulk action.', 'fluent-event'),
            'status.required' => __('Choose the status to move the guests to.', 'fluent-event'),
            'guest_ids.required' => __('Select at least one guest.', 'fluent-event'),
            'guest_ids.array' => __('The selected guests must be a list.', 'fluent-event'),
            'guest_ids.max'  => __('Too many guests selected at once.', 'fluent-event'),
        ];
    }
}
