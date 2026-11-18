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
     * @return array|null|false|string [Guest, previous status, created]; null when another account holds the email;
     *                                 'cancelled' when an admin cancelled the row; false when the row changed under this
     *                                 request to something other than what it asked for.
     */
    public static function registerMember(Event $event, \WP_User $user, $status, $name, array $source)
    {
        $guest = Guest::query()->where('event_id', $event->id)->where('user_id', $user->ID)->first()
            ?: Guest::query()->where('event_id', $event->id)->where('email', $user->user_email)->first();

        if ($guest && $guest->user_id && (int) $guest->user_id !== (int) $user->ID) {
            return null;
        }

        // Only an admin cancels, and the guest can't undo it on their own.
        if ($guest && $guest->status === 'cancelled') {
            return 'cancelled';
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

        // Claiming a visitor's row drops the name they typed, which emails would otherwise greet the member with.
        $claim = ['user_id' => $user->ID];
        if (!$guest->user_id && $guest->source !== 'manual') {
            $claim['name'] = $name ?: $user->display_name;
        }

        // Going again keeps a checked-in guest checked in.
        $newStatus = $status === 'not_going' || !in_array($previousStatus, Event::SEAT_STATUSES, true)
            ? $status
            : $previousStatus;

        // Claimed against the status read above: of two requests making the same change (two tabs), only one
        // reports it, so only one sends the email; the other gets the row back unchanged.
        if ($newStatus !== $previousStatus) {
            $claimed = Guest::query()->where('id', $guest->id)->where('status', $previousStatus)
                ->update(array_merge($claim, ['status' => $newStatus]));

            $guest = Guest::query()->find($guest->id);

            if (!$claimed && $guest->status !== $newStatus) {
                return false;
            }

            return [$guest, $claimed ? $previousStatus : $guest->status, false];
        }

        $guest->fill($claim);
        $guest->save();

        return [$guest, $previousStatus, false];
    }

    // The row this email already holds on the event, if any.
    public static function guestByEmail(Event $event, $email)
    {
        return $email === '' ? null : Guest::query()->where('event_id', $event->id)->where('email', $email)->first();
    }

    // Why a guest can't register again on their own: their row already holds a place, or an admin cancelled it.
    // '' for a row they marked not going, which registering takes back to going.
    public static function repeatRefusal(Guest $guest)
    {
        if ($guest->status === 'not_going') {
            return '';
        }

        return $guest->status === 'cancelled' ? 'cancelled' : 'already_registered';
    }

    // What a refused repeat registration is told.
    public static function refusalMessage($refusal)
    {
        return $refusal === 'cancelled'
            ? __('Your registration was cancelled by the host.', 'fluent-event')
            : __('This email is already registered for this event.', 'fluent-event');
    }

    /**
     * A logged-out visitor's registration by name and email, already validated. Always going: the email's
     * not-going row comes back on, keeping its name; otherwise a new row.
     *
     * @param array      $source source and source_id.
     * @param Guest|null $guest  The email's not-going row.
     * @return array|false [Guest, its status before, or null when created]; false when the row changed under this
     *                     request and holds no seat.
     */
    public static function registerVisitor(Event $event, array $data, array $source, $guest = null)
    {
        if ($guest) {
            // Claimed against "not going", so two requests coming back together send one confirmation.
            $claimed = Guest::query()->where('id', $guest->id)->where('status', 'not_going')->update(['status' => 'going']);
            $guest = Guest::query()->find($guest->id);

            // An admin cancelled or deleted the row meanwhile.
            if (!$claimed && (!$guest || !in_array($guest->status, Event::SEAT_STATUSES, true))) {
                return false;
            }

            return [$guest, $claimed ? 'not_going' : $guest->status];
        }

        return [Guest::create(array_merge($source, [
            'event_id'    => $event->id,
            'calendar_id' => $event->calendar_id,
            'group_id'    => wp_generate_password(32, false, false),
            'is_buyer'    => 1,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'status'      => 'going',
        ])), null];
    }

    // Confirmation on becoming going, cancellation on leaving it, each as Settings > Emails allows.
    public static function queueEmail(Guest $guest, $previousStatus)
    {
        $emails = Settings::get(Settings::EMAIL_NOTIFICATIONS);
        // A checked-in guest holds their seat too, so leaving from there is a cancellation as well.
        $wasGoing = in_array($previousStatus, Event::SEAT_STATUSES, true);

        if ($guest->status === 'going' && !$wasGoing && !empty($emails['registration_confirmation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_confirmation', [(int) $guest->id], 'fluent-event');
        } elseif (in_array($guest->status, ['not_going', 'cancelled'], true) && $wasGoing && !empty($emails['registration_cancellation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
        }
    }
}
