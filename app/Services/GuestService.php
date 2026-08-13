<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Guest;
use FluentEvent\Framework\Support\Arr;

class GuestService
{
    public static function createGuest($event, $data)
    {
        $email = Arr::get($data, 'email');
        $status = Arr::get($data, 'status') ?: Guest::DEFAULT_STATUS;
        $user = get_user_by('email', $email);

        $guest = Guest::create([
            'event_id'      => $event->id,
            'calendar_id'   => $event->calendar_id,
            'name'          => Arr::get($data, 'name'),
            'email'         => $email,
            'user_id'       => $user ? $user->ID : null,
            'status'        => $status,
            'checked_in_at' => $status === 'checked_in' ? current_time('mysql') : null,
            'group_id'      => wp_generate_password(32, false, false),
            'is_buyer'      => 1,
            'source'        => Arr::get($data, 'source', 'community'),
        ]);

        do_action('fluent_event/guest/created', $guest);

        return $guest;
    }

    public static function updateGuest($guest, $data)
    {
        $status = Arr::get($data, 'status') ?: Guest::DEFAULT_STATUS;
        $email = Arr::get($data, 'email');
        $user = get_user_by('email', $email);

        $guest->fill([
            'name'          => Arr::get($data, 'name'),
            'email'         => $email,
            'user_id'       => $user ? $user->ID : null,
            'status'        => $status,
            'checked_in_at' => $status === 'checked_in'
                ? ($guest->checked_in_at ?: current_time('mysql'))
                : null,
        ])->save();

        do_action('fluent_event/guest/updated', $guest);

        return $guest;
    }

    public static function deleteGuest($guest)
    {
        do_action('fluent_event/guest/before_deleted', $guest);

        $guest->delete();

        do_action('fluent_event/guest/deleted', $guest);
    }
}
