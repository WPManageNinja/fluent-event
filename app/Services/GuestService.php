<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Guest;
use FluentEvent\Framework\Support\Arr;

class GuestService
{
    public static function createGuest($event, $data)
    {
        $status = Arr::get($data, 'status') ?: Guest::DEFAULT_STATUS;

        $guest = Guest::create([
            'event_id'      => $event->id,
            'calendar_id'   => $event->calendar_id,
            'name'          => Arr::get($data, 'name'),
            'email'         => Arr::get($data, 'email'),
            'user_id'       => Arr::get($data, 'user_id') ?: null,
            'status'        => $status,
            'checked_in_at' => $status === 'checked_in' ? current_time('mysql') : null,
            'group_id'      => wp_generate_password(32, false, false),
            'is_buyer'      => 1,
            'source'        => Arr::get($data, 'source', 'community'),
        ]);

        return $guest;
    }

    public static function updateGuest($guest, $data)
    {
        $status = Arr::get($data, 'status') ?: Guest::DEFAULT_STATUS;

        $guest->fill([
            'name'          => Arr::get($data, 'name'),
            'email'         => Arr::get($data, 'email'),
            'user_id'       => Arr::get($data, 'user_id') ?: null,
            'status'        => $status,
            'checked_in_at' => $status === 'checked_in'
                ? ($guest->checked_in_at ?: current_time('mysql'))
                : null,
        ])->save();

        return $guest;
    }

    public static function deleteGuest($guest)
    {
        $guest->delete();
    }
}
