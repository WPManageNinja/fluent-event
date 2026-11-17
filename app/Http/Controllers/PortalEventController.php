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
    // Anyone, logged in or not, as a visitor: by name and email, on an RSVP or open event. Always going, with no undo.
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

        // A repeated email is warned, and nothing is saved.
        if (RegistrationService::hasGuestEmail($event, $data['email'])) {
            return $this->registered($event, null);
        }

        $this->validate(
            $data,
            Arr::only(GuestRequest::createRules($event->id), ['name', 'email']),
            GuestRequest::messages()
        );

        $guest = RegistrationService::registerVisitor($event, $data, ['source' => 'wordpress_pages']);

        do_action('fluent_event/guest/created', $guest);

        RegistrationService::queueEmail($guest, null);

        return $this->registered($event, $guest->status);
    }

    /**
     * @param string|null $status The new guest's status, or null when the email was already registered.
     */
    private function registered(Event $event, $status)
    {
        $seats = EventService::seatCounts([$event->id]);
        // A visitor has no account to read their row back by: a new guest's card is drawn as going, a repeat's as before.
        $formatted = EventService::formatEvent($event, $seats[(int) $event->id] ?? 0, $status, false, false);

        return [
            'message' => $status ? __('You are registered.', 'fluent-event') : __('This email is already registered for this event.', 'fluent-event'),
            'status'  => $status,
            'html'    => EventPageService::sideCard($event, $formatted, EventPageService::url($event), $status ? 'registered' : 'already'),
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
