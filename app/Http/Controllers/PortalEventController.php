<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\RateLimiter;
use FluentEvent\App\Services\RegistrationService;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Framework\Support\Arr;

/**
 * Registration from the public event page. An event takes it while that page shows it; the answer
 * carries the side card, re-rendered for the new state, which the page swaps in.
 */
class PortalEventController extends Controller
{
    // A logged-in member's own answer, as on the portal: Going or Not going on an RSVP, Not going on an open event
    // they hold a seat on. An open event's registration comes through the form, in registerGuest().
    public function register(Request $request, $id)
    {
        // Shares 'rsvp' with the portal's member endpoint, so switching pages gives no new allowance.
        if (RateLimiter::isSpamming('rsvp', 10)) {
            return $this->tooManyRequests();
        }

        if (!$this->cameFromThePage($request, $id)) {
            return $this->expired();
        }

        $event = $this->pageEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        if ($refusal = RegistrationService::refusal($event, ['rsvp', 'open'])) {
            return $this->sendError(['message' => $refusal], 422);
        }

        $isOpen = Arr::get((array) $event->meta, 'entry_type') === 'open';
        $status = $request->getSafe('status', 'sanitize_text_field');

        if (!in_array($status, $isOpen ? ['not_going'] : ['going', 'not_going'], true)) {
            return $this->sendError(['message' => __('Please choose going or not going.', 'fluent-event')], 422);
        }

        // An open event's Not going only gives up a seat the member holds; otherwise a not-going row would appear
        // from nothing.
        if ($isOpen && !in_array(RegistrationService::memberStatus($event), Event::SEAT_STATUSES, true)) {
            return $this->sendError(['message' => __('You are not registered for this event.', 'fluent-event')], 422);
        }

        return $this->answerAsMember($event, $status, '');
    }

    // Anyone by name and email, on an RSVP or open event. Always going; a visitor's is final. A logged-in member
    // keeping their account's email registers by account, so it shows as theirs; another email registers a visitor.
    public function registerGuest(Request $request, $id)
    {
        if (RateLimiter::isSpamming('register_guest', 5)) {
            return $this->tooManyRequests();
        }

        if (!$this->cameFromThePage($request, $id)) {
            return $this->expired();
        }

        $event = $this->pageEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        if ($refusal = RegistrationService::refusal($event, ['rsvp', 'open'])) {
            return $this->sendError(['message' => $refusal], 422);
        }

        // Bots fill the hidden field; they are told nothing and nothing is saved.
        if ($request->getSafe('website', 'sanitize_text_field')) {
            return ['message' => __('You are registered.', 'fluent-event')];
        }

        $data = GuestRequest::sanitize(Arr::only($request->all(), ['name', 'email']));

        // Validate first, so bad input fails alike for every email.
        $this->validate($data, Arr::only(GuestRequest::RULES, ['name', 'email']), GuestRequest::messages());

        $user = wp_get_current_user();

        if ($user->exists() && strtolower($data['email']) === strtolower($user->user_email)) {
            return $this->answerAsMember($event, 'going', $data['name']);
        }

        $guest = RegistrationService::guestByEmail($event, $data['email']);

        // A repeated email is warned with nothing saved, unless its row said not going and has no account: that comes
        // back on.
        if ($guest && ($refusal = RegistrationService::visitorRefusal($guest))) {
            return $this->registered($event, null, $refusal);
        }

        if (!$guest) {
            $this->validate(
                $data,
                Arr::only(GuestRequest::createRules($event->id), ['name', 'email']),
                GuestRequest::messages()
            );
        }

        $registered = RegistrationService::registerVisitor($event, $data, ['source' => 'wordpress_pages'], $guest);

        if (is_string($registered)) {
            return $this->sendError(['message' => $registered], 422);
        }

        if (!$registered) {
            return $this->sendError(['message' => __('Your registration just changed. Please try again.', 'fluent-event')], 409);
        }

        [$guest, $previousStatus] = $registered;

        if ($previousStatus === null) {
            do_action('fluent_event/guest/created', $guest);
        } elseif ($previousStatus !== $guest->status) {
            do_action('fluent_event/guest/updated', $guest, $previousStatus);
        }

        RegistrationService::queueEmail($guest, $previousStatus);

        return $this->registered($event, $guest->status);
    }

    private function answerAsMember(Event $event, $status, $name)
    {
        $registered = RegistrationService::registerMember($event, wp_get_current_user(), $status, $name, ['source' => 'wordpress_pages']);

        if ($registered === false) {
            return $this->sendError(['message' => __('Your registration just changed. Please try again.', 'fluent-event')], 409);
        }

        // A repeat is warned in the card's dialog; a full event is an error.
        if (in_array($registered, ['already_registered', 'cancelled'], true)) {
            return $this->registered($event, null, $registered);
        }

        if (is_string($registered)) {
            return $this->sendError(['message' => $registered], 422);
        }

        if (!$registered) {
            return $this->sendError(['message' => __('This email is already registered for this event.', 'fluent-event')], 422);
        }

        [$guest, $previousStatus, $created] = $registered;

        if ($created) {
            do_action('fluent_event/guest/created', $guest);
        } elseif ($previousStatus !== $guest->status) {
            do_action('fluent_event/guest/updated', $guest, $previousStatus);
        }

        RegistrationService::queueEmail($guest, $previousStatus);

        return $this->registered($event, $guest->status);
    }

    /**
     * @param string|null $status  The guest's status, or null when the registration was refused.
     * @param string      $refusal Why it was refused: 'already_registered' or 'cancelled'.
     */
    private function registered(Event $event, $status, $refusal = '')
    {
        $seats = EventService::seatCounts([$event->id]);
        // A member's card shows their own row, read back by account. A visitor has none: a registration's card is
        // drawn as going, a refusal's as before.
        $myStatus = is_user_logged_in() ? RegistrationService::memberStatus($event) : $status;
        $formatted = EventService::formatEvent($event, $seats[(int) $event->id] ?? 0, $myStatus, false, false);

        if ($refusal) {
            $message = RegistrationService::refusalMessage($refusal);
        } elseif ($status === 'not_going') {
            $message = Arr::get((array) $event->meta, 'entry_type') === 'open'
                ? __("You're no longer going.", 'fluent-event')
                : __("RSVP updated. You're not going.", 'fluent-event');
        } else {
            $message = __('You are registered.', 'fluent-event');
        }

        // Going opens the confirmation over the new card; Not going just redraws it.
        $answer = $refusal ?: ($status === 'not_going' ? '' : 'registered');

        return [
            'message' => $message,
            'status'  => $status,
            'html'    => EventPageService::sideCard($event, $formatted, EventPageService::url($event), $answer),
        ];
    }

    // The event's own nonce, which only its page hands out, checked before the event is looked up:
    // otherwise walking ids would find unlisted events, which open only by their link.
    private function cameFromThePage(Request $request, $id)
    {
        return (bool) wp_verify_nonce($request->getSafe('nonce', 'sanitize_text_field'), 'fe_portal_register_' . (int) $id);
    }

    private function expired()
    {
        return $this->sendError(['message' => __('This form has expired. Please reload the page and try again.', 'fluent-event')], 403);
    }

    // What the event page opens: a public or unlisted event in a public calendar.
    private function pageEvent($id)
    {
        return Event::query()
            ->with(['post', 'calendar'])
            ->byStatus(['public', 'unlisted'])
            ->whereHas('calendar', function ($query) {
                $query->byStatus('public');
            })
            ->find((int) $id);
    }
}
