<?php

namespace FluentEvent\App\Http\Controllers;

use FluentCommunity\App\Models\Space as FcomSpace;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\RateLimiter;
use FluentEvent\App\Services\RegistrationService;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Modules\FluentCommunity\CommunityEvents;

/**
 * Member-facing event endpoints for the FluentCommunity portal. Visibility
 * follows the event's post: whoever can read the post can see and join it.
 */
class CommunityEventController extends Controller
{
    public function getEvents(Request $request)
    {
        $spaceIds = CommunityEvents::visibleSpaceIds();
        $withSpaceless = CommunityEvents::canViewSpacelessPosts();

        if ($spaceSlug = $request->getSafe('space', 'sanitize_title')) {
            $spaceIds = FcomSpace::query()->whereIn('id', $spaceIds)->where('slug', $spaceSlug)->pluck('id')->toArray();
            $withSpaceless = false;
        }

        $from = $request->getSafe('from', 'sanitize_text_field');
        $to = $request->getSafe('to', 'sanitize_text_field');

        $query = Event::query()
            ->with(['post'])
            // Visibility is matched in SQL, so only what was asked for is ever loaded.
            ->whereIn('id', function ($query) use ($spaceIds, $withSpaceless) {
                $query->select('fevent_meta.object_id')
                    ->from('fevent_meta')
                    ->join('fcom_posts', 'fcom_posts.id', '=', 'fevent_meta.value')
                    // fcom hides posts whose author is blocked, pending or deactivated.
                    ->join('fcom_xprofile', 'fcom_xprofile.user_id', '=', 'fcom_posts.user_id')
                    ->where('fevent_meta.object_type', Meta::OBJECT_TYPE_EVENT)
                    ->where('fevent_meta.meta_key', CommunityEvents::FEED_META_KEY)
                    ->where('fcom_posts.content_type', CommunityEvents::CONTENT_TYPE)
                    ->where('fcom_posts.status', 'published')
                    ->where('fcom_xprofile.status', 'active')
                    ->where(function ($query) use ($spaceIds, $withSpaceless) {
                        $query->whereIn('fcom_posts.space_id', $spaceIds);

                        if ($withSpaceless) {
                            $query->orWhereNull('fcom_posts.space_id');
                        }
                    });
            })
            ->byStatus(Event::LIVE_STATUSES)
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->inDateRange($from, $to)
            ->byPeriod($request->getSafe('scope', 'sanitize_text_field', 'upcoming'));

        // The calendar takes its whole date window at once, as the admin grid does. Only a real window of at
        // most two months skips paging: the date filter ignores anything else, which would return everything.
        $fromTime = DateTimeHelper::isDate($from) ? strtotime($from) : false;
        $toTime = DateTimeHelper::isDate($to) ? strtotime($to) : false;
        $isWindow = $fromTime && $toTime && $toTime >= $fromTime && $toTime - $fromTime <= 62 * DAY_IN_SECONDS;

        if ($isWindow && !$request->getSafe('paginate', 'rest_sanitize_boolean', true)) {
            $events = $query->get()->all();
            $pages = ['total' => count($events), 'current_page' => 1, 'last_page' => 1];
        } else {
            $paginator = $query->paginate(EventRequest::perPage($request));
            $events = $paginator->items();
            $pages = [
                'total'        => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ];
        }

        $formatted = EventService::formatEvents($events);

        $spaceIds = Meta::query()
            ->join('fcom_posts', 'fcom_posts.id', '=', 'fevent_meta.value')
            ->where('fevent_meta.object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('fevent_meta.meta_key', CommunityEvents::FEED_META_KEY)
            ->whereIn('fevent_meta.object_id', array_keys($formatted))
            ->pluck('fcom_posts.space_id', 'fevent_meta.object_id')
            ->toArray();

        $spaces = FcomSpace::query()->whereIn('id', array_filter($spaceIds))->get()->keyBy('id');

        $items = [];

        foreach ($events as $event) {
            $space = $spaces->get((int) Arr::get($spaceIds, $event->id));

            // A community-wide event has no space, so no slug and no icon. The icon is fcom's sidebar one:
            // logo, else emoji, else SVG shape, sanitized the way fcom sanitizes it for its own menus.
            $items[] = array_merge($formatted[$event->id], [
                'space_slug' => $space ? $space->slug : '',
                'space'      => $space ? [
                    'title'     => $space->title,
                    'logo'      => esc_url_raw((string) $space->logo),
                    'emoji'     => (string) Arr::get($space->settings, 'emoji', ''),
                    'shape_svg' => CustomSanitizer::sanitizeSvg((string) Arr::get($space->settings, 'shape_svg', '')),
                ] : null,
            ]);
        }

        return array_merge(['events' => $items], $pages);
    }

    // The event page's URL names the space and the event; the page then reads the event's post, so fcom still decides who sees it.
    public function getEventBySlug(Request $request)
    {
        $slug = $request->getSafe('slug', 'sanitize_title');

        $event = $slug ? Event::query()->whereHas('post', function ($query) use ($slug) {
            $query->where('post_name', $slug);
        })->first() : null;

        list($event, $feed) = $event ? CommunityEvents::visibleEvent($event->id) : [null, null];

        // An event posted outside any space has no space in its URL.
        $space = $feed && $feed->space_id ? FcomSpace::query()->find($feed->space_id) : null;
        $spaceSlug = $space ? $space->slug : '';

        if (!$event || ($feed->space_id && !$space) || $spaceSlug !== $request->getSafe('space', 'sanitize_title', '')) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        return ['feed_slug' => $feed->slug];
    }

    // Members answer an RSVP either way; an open event only registers them (from its form), with no undo.
    public function register(Request $request, $id)
    {
        if (RateLimiter::isSpamming('rsvp', 10)) {
            return $this->tooManyRequests();
        }

        [$event, $feed] = CommunityEvents::visibleEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        if ($refusal = RegistrationService::refusal($event, ['rsvp', 'open'])) {
            return $this->sendError(['message' => $refusal], 422);
        }

        $entryType = Arr::get((array) $event->meta, 'entry_type');
        $status = $request->getSafe('status', 'sanitize_text_field');

        if (!in_array($status, ['going', 'not_going'], true)) {
            return $this->sendError(['message' => __('Please choose going or not going.', 'fluent-event')], 422);
        }

        // An open event's "not going" only gives up a registration the member holds; otherwise a not-going row
        // would appear from nothing. An RSVP's is an answer of its own.
        $myStatus = Guest::query()->where('event_id', $event->id)->where('user_id', get_current_user_id())->value('status');

        if ($status === 'not_going' && $entryType === 'open' && !in_array($myStatus, Event::SEAT_STATUSES, true)) {
            return $this->sendError(['message' => __('You are not registered for this event.', 'fluent-event')], 422);
        }

        // An open event's form lets the member change their name; their email is the account's.
        $name = $entryType === 'open' ? $request->getSafe('name', 'sanitize_text_field') : '';
        $this->validate(['name' => $name], ['name' => 'nullable|string|max:255'], GuestRequest::messages());

        $registered = RegistrationService::registerMember($event, wp_get_current_user(), $status, $name, [
            'source'    => 'fluent_community',
            'source_id' => (int) $feed->id,
        ]);

        if ($registered === false) {
            return $this->sendError(['message' => __('Your registration just changed. Please try again.', 'fluent-event')], 409);
        }

        // A repeat is warned in a dialog; a full event is an error.
        if (in_array($registered, ['already_registered', 'cancelled', 'checked_in'], true)) {
            return self::refused($registered);
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

        // The portal shows an RSVP's message as a toast; an open event's registration gets its own dialog.
        if ($guest->status === 'not_going') {
            $message = $entryType === 'rsvp' ? __("RSVP updated. You're not going.", 'fluent-event') : __("You're no longer going.", 'fluent-event');
        } elseif ($entryType === 'rsvp') {
            $message = __("RSVP updated. You're going!", 'fluent-event');
        } else {
            $message = __('You are registered.', 'fluent-event');
        }

        return self::answered($event, $guest->status, $message);
    }

    // A logged-out visitor registers for an open event by name and email; members use register().
    public function registerGuest(Request $request, $id)
    {
        // Shares 'register_guest' with the public page's form, so switching forms gives no new allowance.
        if (RateLimiter::isSpamming('register_guest', 5)) {
            return $this->tooManyRequests();
        }

        [$event, $feed] = CommunityEvents::visibleEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        if (!wp_verify_nonce($request->getSafe('nonce', 'sanitize_text_field'), 'fevent_guest_register_' . $event->id)) {
            return $this->sendError(['message' => __('This form has expired. Please reload the page and try again.', 'fluent-event')], 403);
        }

        if ($refusal = RegistrationService::refusal($event, ['open'])) {
            return $this->sendError(['message' => $refusal], 422);
        }

        // Bots fill the hidden field; they are told nothing and nothing is saved.
        if ($request->getSafe('website', 'sanitize_text_field')) {
            return ['message' => __('You are registered.', 'fluent-event')];
        }

        $data = GuestRequest::sanitize(Arr::only($request->all(), ['name', 'email']));

        // Validate first, so bad input fails alike for every email.
        $this->validate($data, Arr::only(GuestRequest::RULES, ['name', 'email']), GuestRequest::messages());

        $guest = RegistrationService::guestByEmail($event, $data['email']);

        // A repeated email is refused with nothing saved, unless its row said not going and has no account: that comes
        // back on.
        if ($guest && ($refusal = RegistrationService::visitorRefusal($guest))) {
            return self::refused($refusal);
        }

        if (!$guest) {
            $this->validate(
                $data,
                Arr::only(GuestRequest::createRules($event->id), ['name', 'email']),
                GuestRequest::messages()
            );
        }

        $registered = RegistrationService::registerVisitor($event, $data, ['source' => 'fluent_community', 'source_id' => (int) $feed->id], $guest);

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

        return self::answered($event, $guest->status, __('You are registered.', 'fluent-event'));
    }

    // A registration refused with nothing saved: no status, so the portal changes nothing and says why.
    private static function refused($refusal)
    {
        return [
            'message' => RegistrationService::refusalMessage($refusal),
            'status'  => null,
            'refusal' => $refusal,
        ];
    }

    // The guest's new status with the event's seats as they stand now, everyone's answers included, so the portal
    // redraws the count, the spots left and the full state together. The link shows once the guest holds a seat.
    private static function answered(Event $event, $status, $message)
    {
        $seats = EventService::seatCounts([$event->id]);
        $formatted = EventService::formatEvent($event, $seats[(int) $event->id] ?? 0, $status, false, false);

        return array_merge(
            ['message' => $message, 'status' => $status],
            Arr::only($formatted, ['online_meeting_url', 'guest_count', 'spots_left', 'is_full'])
        );
    }
}
