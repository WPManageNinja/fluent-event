<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Foundation\Exceptions\ForbiddenHttpException;
use FluentEvent\Framework\Validator\ValidationException;

class GuestService
{
    const RSVP_STATUSES = ['going', 'not_going'];

    /** Attendance buckets: checked_in is an arrival, cancelled is a withdrawal. */
    const COUNTS_AS = [
        'going'      => 'going',
        'checked_in' => 'going',
        'not_going'  => 'not_going',
        'cancelled'  => 'not_going',
    ];

    public static function updateGuest(int $eventId, int $userId, array $data): array
    {
        $event = Event::query()->find($eventId);

        if (!$event) {
            throw new ValidationException('Unprocessable Entity!', 422, null, ['event_id' => ['exists' => __('This event no longer exists.', 'fluent-event')]]);
        }

        if ($event->status !== 'public') {
            throw new ValidationException('Unprocessable Entity!', 422, null, ['event_id' => ['status' => __('This event is not open for RSVP yet.', 'fluent-event')]]);
        }
        
        $entryType = Arr::get((array) $event->meta, 'entry_type', Event::DEFAULT_ENTRY_TYPE);

        if ($entryType !== 'rsvp') {
            throw new ValidationException('Unprocessable Entity!', 422, null, ['entry_type' => ['unsupported' => __('This event is not valid.', 'fluent-event')]]);
        } else {
            if (!$userId) {
                throw new ForbiddenHttpException(
                    __('You must be logged in to enter.', 'fluent-event'),
                    'fluent_event_permission_denied'
                );
            }

            $status = Arr::get($data, 'status', '');

            if (!in_array($status, self::RSVP_STATUSES, true)) {
                throw new ValidationException('Unprocessable Entity!', 422, null, ['status' => ['in' => __('RSVP must be going or not going.', 'fluent-event')]]);
            }

            $user = get_user_by('ID', $userId);

            if (!$user) {
                throw new ValidationException('Unprocessable Entity!', 422, null, ['user_id' => ['exists' => __('The selected user no longer exists.', 'fluent-event')]]);
            }

            $name = sanitize_text_field(Arr::get($data, 'name', '')) ?: $user->display_name;
            $email = sanitize_email(Arr::get($data, 'email', '')) ?: $user->user_email;
            $sourceId = (int) Arr::get($data, 'source_id', 0);

            $guest = Guest::query()
                ->where('event_id', $event->id)
                ->where('user_id', $userId)
                ->first();

            if ($guest) {
                $guest->fill([
                    'status'        => $status,
                    'name'          => $name,
                    'email'         => $email,
                    'checked_in_at' => $status === 'going' ? $guest->checked_in_at : null,
                ])->save();

                do_action('fluent_event/guest/updated', $guest);

                return static::format($guest);
            }

            $guest = Guest::create([
                'event_id'    => $event->id,
                'calendar_id' => $event->calendar_id,
                'name'        => $name,
                'email'       => $email,
                'user_id'     => $userId,
                'status'      => $status,
                'group_id'    => wp_generate_password(32, false, false),
                'is_buyer'    => 1,
                'source'      => 'community',
                'source_id'   => $sourceId ?: null,
            ]);

            do_action('fluent_event/guest/created', $guest);

            return static::format($guest);
        }
    }

    public static function format($guest): array
    {
        return [
            'id'       => (int) $guest->id,
            'event_id' => (int) $guest->event_id,
            'user_id'  => (int) $guest->user_id,
            'name'     => $guest->name,
            'email'    => $guest->email,
            'status'   => $guest->status,
            'avatar'   => $guest->avatar,
        ];
    }
}
