<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\RegistrationService;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Modules\FluentCommunity\CommunityEvents;

/**
 * Registration from the public event page. An event takes it while that page shows it; each answer
 * carries the side card, re-rendered for the new state, which the page swaps in.
 */
class PortalEventController extends Controller
{
    // A logged-in member: RSVP going / not going, or an open event's registration.
    public function register(Request $request, $id)
    {
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

        $entryType = Arr::get((array) $event->meta, 'entry_type');
        $status = $request->getSafe('status', 'sanitize_text_field');

        if (!in_array($status, $entryType === 'open' ? ['going'] : ['going', 'not_going'], true)) {
            return $this->sendError(['message' => __('Please choose going or not going.', 'fluent-event')], 422);
        }

        // An open event's form lets the member change their name; their email is the account's.
        $name = $entryType === 'open' ? $request->getSafe('name', 'sanitize_text_field') : '';
        $this->validate(['name' => $name], ['name' => 'nullable|string|max:255'], GuestRequest::messages());

        $registered = RegistrationService::registerMember($event, wp_get_current_user(), $status, $name, ['source' => 'website']);

        if (!$registered) {
            return $this->sendError(['message' => __('This email is already registered for this event.', 'fluent-event')], 422);
        }

        [$guest, $previousStatus, $created] = $registered;

        if ($created) {
            do_action('fluent_event/guest/created', $guest);
        } else {
            do_action('fluent_event/guest/updated', $guest, $previousStatus);
        }

        RegistrationService::queueEmail($guest, $previousStatus);

        return [
            'message' => $guest->status === 'not_going'
                ? __('Your RSVP has been updated.', 'fluent-event')
                : __('You are registered.', 'fluent-event'),
            'status'  => $guest->status,
            'html'    => EventPageService::sideCard($event, CommunityEvents::formatEvents([$event])[(int) $event->id], EventPageService::url($event)),
        ];
    }

    // A logged-out visitor, by name and email, on an RSVP or open event. Always going, with no undo.
    public function registerGuest(Request $request, $id)
    {
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
        $this->validate(
            $data,
            Arr::only(GuestRequest::createRules($event->id), ['name', 'email']),
            GuestRequest::messages()
        );

        $guest = RegistrationService::registerVisitor($event, $data, ['source' => 'website']);

        do_action('fluent_event/guest/created', $guest);

        RegistrationService::queueEmail($guest, null);

        $seats = CommunityEvents::seatCounts([$event->id]);
        // A visitor has no account to read their row back by, so the card is drawn as going.
        $formatted = CommunityEvents::formatEvent($event, $seats[(int) $event->id] ?? 0, $guest->status, false, false);

        return [
            'message' => __('You are registered.', 'fluent-event'),
            'status'  => $guest->status,
            'html'    => EventPageService::sideCard($event, $formatted, EventPageService::url($event)),
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

    // What the event page opens: a public or unlisted event in a public or unlisted calendar.
    private function pageEvent($id)
    {
        return Event::query()
            ->with(['post', 'calendar'])
            ->byStatus(['public', 'unlisted'])
            ->whereHas('calendar', function ($query) {
                $query->byStatus(['public', 'unlisted']);
            })
            ->find((int) $id);
    }
}
