<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Framework\Support\Arr;

/**
 * Self-registration, shared by the community portal and the public event page. The writes and the
 * emails live here; each caller checks visibility, validates and fires the guest hooks.
 */
class RegistrationService
{
    /**
     * Why an event refuses a registration, or '' when it takes one.
     *
     * @param string[] $entryTypes The entry types this caller accepts.
     */
    public static function refusal(Event $event, array $entryTypes)
    {
        if (!in_array(Arr::get((array) $event->meta, 'entry_type'), $entryTypes, true)) {
            return __('This event does not take registrations.', 'fluent-event');
        }

        if ($event->end_at < gmdate(DateTimeHelper::FORMAT)) {
            return __('This event has already ended.', 'fluent-event');
        }

        return '';
    }

    /**
     * A logged-in member's RSVP or open registration: their own row first, else one an admin added
     * under their email.
     *
     * @param array $source source and source_id for a new row.
     * @return array|null [Guest, previous status, created]; null when another account holds the email.
     */
    public static function registerMember(Event $event, \WP_User $user, $status, $name, array $source)
    {
        $guest = Guest::query()->where('event_id', $event->id)->where('user_id', $user->ID)->first()
            ?: Guest::query()->where('event_id', $event->id)->where('email', $user->user_email)->first();

        if ($guest && $guest->user_id && (int) $guest->user_id !== (int) $user->ID) {
            return null;
        }

        if (!$guest) {
            return [
                Guest::create(array_merge($source, [
                    'event_id'    => $event->id,
                    'calendar_id' => $event->calendar_id,
                    'group_id'    => wp_generate_password(32, false, false),
                    'is_buyer'    => 1,
                    'name'        => $name ?: $user->display_name,
                    'email'       => $user->user_email,
                    'user_id'     => $user->ID,
                    'status'      => $status,
                ])),
                null,
                true,
            ];
        }

        $previousStatus = $guest->status;

        // Going again keeps a checked-in guest checked in.
        if ($status === 'not_going' || !in_array($guest->status, Event::SEAT_STATUSES, true)) {
            $guest->status = $status;
        }

        $guest->user_id = $user->ID;
        $guest->save();

        return [$guest, $previousStatus, false];
    }

    /**
     * A logged-out visitor's registration by name and email, already validated. Always going.
     *
     * @param array $source source and source_id.
     */
    public static function registerVisitor(Event $event, array $data, array $source)
    {
        return Guest::create(array_merge($source, [
            'event_id'    => $event->id,
            'calendar_id' => $event->calendar_id,
            'group_id'    => wp_generate_password(32, false, false),
            'is_buyer'    => 1,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'status'      => 'going',
        ]));
    }

    // Confirmation on becoming going, cancellation on leaving it, each as Settings > Emails allows.
    public static function queueEmail(Guest $guest, $previousStatus)
    {
        $emails = Settings::get(Settings::EMAIL_NOTIFICATIONS);
        $wasGoing = $previousStatus === 'going';

        if ($guest->status === 'going' && !$wasGoing && !empty($emails['registration_confirmation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_confirmation', [(int) $guest->id], 'fluent-event');
        } elseif ($guest->status === 'not_going' && $wasGoing && !empty($emails['registration_cancellation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
        }
    }
}
