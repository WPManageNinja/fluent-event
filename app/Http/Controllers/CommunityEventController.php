<?php

namespace FluentEvent\App\Http\Controllers;

use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Models\Space as FcomSpace;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\Facade\DB;
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

        if ($spaceSlug = $request->getSafe('space', 'sanitize_title')) {
            $spaceIds = FcomSpace::query()->whereIn('id', $spaceIds)->where('slug', $spaceSlug)->pluck('id')->toArray();
        }

        // Event id => the published post it lives in.
        $posts = [];

        $feeds = FcomFeed::query()
            ->select(['id', 'slug', 'space_id', 'meta'])
            ->with(['space' => function ($query) {
                $query->select(['id', 'title']);
            }])
            ->where('content_type', CommunityEvents::CONTENT_TYPE)
            ->where('status', 'published')
            ->whereIn('space_id', $spaceIds)
            ->get();

        foreach ($feeds as $feed) {
            $posts[CommunityEvents::eventIdOf($feed)] = $feed;
        }

        $events = Event::query()
            ->with(['post'])
            ->whereIn('id', array_keys($posts))
            ->byStatus(['public', 'private'])
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->inDateRange(
                $request->getSafe('from', 'sanitize_text_field'),
                $request->getSafe('to', 'sanitize_text_field')
            )
            ->byPeriod($request->getSafe('scope', 'sanitize_text_field', 'upcoming'))
            ->paginate(EventRequest::perPage($request));

        $formatted = CommunityEvents::formatEvents($events->items());
        $items = [];

        foreach ($events->items() as $event) {
            $post = $posts[$event->id];

            $items[] = array_merge($formatted[$event->id], [
                'post_slug'   => $post->slug,
                'space_title' => $post->space ? $post->space->title : '',
            ]);
        }

        return [
            'events'       => $items,
            'total'        => $events->total(),
            'current_page' => $events->currentPage(),
            'last_page'    => $events->lastPage(),
        ];
    }

    public function register(Request $request, $id)
    {
        [$event, $feed] = CommunityEvents::visibleEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        $meta = array_merge(Event::defaultMeta(), (array) $event->meta);
        $entryType = $meta['entry_type'];
        $userId = get_current_user_id();

        if ($entryType === 'none') {
            return $this->sendError(['message' => __('This event does not take registrations.', 'fluent-event')], 422);
        }

        if ($event->end_at < gmdate(DateTimeHelper::FORMAT)) {
            return $this->sendError(['message' => __('This event has already ended.', 'fluent-event')], 422);
        }

        if ($entryType === 'rsvp' && !$userId) {
            return $this->sendError(['message' => __('Please log in to RSVP.', 'fluent-event')], 403);
        }

        $status = $entryType === 'rsvp' ? $request->getSafe('status', 'sanitize_text_field') : 'going';

        if (!in_array($status, ['going', 'not_going'], true)) {
            return $this->sendError(['message' => __('Please choose going or not going.', 'fluent-event')], 422);
        }

        if ($entryType === 'rsvp') {
            $user = get_user_by('ID', $userId);
            $name = $user->display_name;
            $email = $user->user_email;
        } else {
            // Open registration always takes the name and email given; a member may register another address.
            $data = [
                'name'  => $request->getSafe('name', 'sanitize_text_field'),
                'email' => $request->getSafe('email', 'sanitize_email'),
            ];

            $this->validate($data, [
                'name'  => 'required|string|max:255',
                'email' => 'required|email|max:192',
            ], [
                'name.required'  => __('Please enter your name.', 'fluent-event'),
                'name.max'       => __('Name cannot exceed 255 characters.', 'fluent-event'),
                'email.required' => __('Please enter your email address.', 'fluent-event'),
                'email.email'    => __('Please enter a valid email address.', 'fluent-event'),
                'email.max'      => __('Email cannot exceed 192 characters.', 'fluent-event'),
            ]);

            $name = $data['name'];
            $email = $data['email'];
        }

        $result = DB::transaction(function () use ($event, $feed, $meta, $userId, $name, $email, $status) {
            // Locking the event row queues sign-ups for it, so two people can't take the last seat.
            Event::query()->where('id', $event->id)->lockForUpdate()->first();

            $guest = $userId
                ? Guest::query()->where('event_id', $event->id)->where('user_id', $userId)->first()
                : null;

            if (!$guest) {
                $guest = Guest::query()->where('event_id', $event->id)->where('email', $email)->first();

                // Claiming a row found by email needs proof the address is the member's own.
                if ($guest && $userId && strcasecmp($email, (string) wp_get_current_user()->user_email) !== 0) {
                    return ['conflict' => true];
                }
            }

            if ($guest && $guest->user_id && (int) $guest->user_id !== $userId) {
                return ['conflict' => true];
            }

            // A member moving their row to an address another row holds would break the event + email unique key.
            if ($guest && $guest->email !== $email && Guest::query()->where('event_id', $event->id)->where('email', $email)->exists()) {
                return ['conflict' => true];
            }

            $wasSeated = $guest && in_array($guest->status, Event::SEAT_STATUSES, true);
            $takesSeat = $status === 'going' && !$wasSeated;

            if ($takesSeat && (int) $meta['max_guests'] >= 0) {
                $seats = Guest::query()
                    ->where('event_id', $event->id)
                    ->whereIn('status', Event::SEAT_STATUSES)
                    ->count();

                if ($seats >= (int) $meta['max_guests']) {
                    return ['full' => true];
                }
            }

            if (!$guest) {
                $guest = Guest::create([
                    'event_id'    => $event->id,
                    'calendar_id' => $event->calendar_id,
                    'group_id'    => wp_generate_password(32, false, false),
                    'is_buyer'    => 1,
                    'name'        => $name,
                    'email'       => $email,
                    'user_id'     => $userId ?: null,
                    'status'      => $status,
                    'source'      => 'community',
                    'source_id'   => (int) $feed->id,
                ]);

                return ['guest' => $guest, 'created' => true];
            }

            // An anonymous repeat of a live registration changes nothing; the reply
            // stays the same so it can't be used to test which emails signed up.
            if (!$userId && $wasSeated) {
                return ['guest' => $guest, 'created' => false, 'unchanged' => true];
            }

            $previousStatus = $guest->status;

            $guest->fill([
                'status'  => ($takesSeat || $status !== 'going') ? $status : $guest->status,
                'name'    => $name,
                'email'   => $email,
                'user_id' => $userId ?: $guest->user_id,
            ])->save();

            return ['guest' => $guest, 'created' => false, 'previous_status' => $previousStatus];
        });

        if (!empty($result['conflict'])) {
            return $this->sendError(['message' => __('This email is already registered for this event.', 'fluent-event')], 422);
        }

        if (!empty($result['full'])) {
            return $this->sendError(['message' => __('Sorry, this event is full.', 'fluent-event')], 422);
        }

        $guest = $result['guest'];
        $previousStatus = $result['previous_status'] ?? null;

        if ($result['created']) {
            do_action('fluent_event/guest/created', $guest);
        } elseif (empty($result['unchanged'])) {
            do_action('fluent_event/guest/updated', $guest, $previousStatus);
        }

        if (empty($result['unchanged'])) {
            $emails = Settings::get(Settings::EMAIL_NOTIFICATIONS);
            $wasGoing = $previousStatus === 'going';

            if ($guest->status === 'going' && !$wasGoing && !empty($emails['registration_confirmation']['enabled'])) {
                as_enqueue_async_action('fluent_event/send_registration_confirmation', [(int) $guest->id], 'fluent-event');
            } elseif ($guest->status === 'not_going' && $wasGoing && !empty($emails['registration_cancellation']['enabled'])) {
                as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
            }
        }

        $response = [
            'message' => $guest->status === 'not_going'
                ? __('Your RSVP has been updated.', 'fluent-event')
                : __('You are registered.', 'fluent-event'),
            'status'  => $guest->status,
            'event'   => CommunityEvents::formatEvents([$event])[(int) $event->id],
        ];

        // A logged-out guest can't be recognised on the next visit, so the link is handed over now.
        if (!$userId && $guest->status !== 'not_going' && $event->venue_type === 'online') {
            $response['meeting_url'] = $meta['online_meeting_url'];
        }

        return $response;
    }

    public function cancel(Request $request, $id)
    {
        [$event] = CommunityEvents::visibleEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        $guest = Guest::query()
            ->where('event_id', $event->id)
            ->where('user_id', get_current_user_id())
            ->first();

        if (!$guest) {
            return $this->sendError(['message' => __('You are not registered for this event.', 'fluent-event')], 404);
        }

        $entryType = Arr::get(array_merge(Event::defaultMeta(), (array) $event->meta), 'entry_type');
        $previousStatus = $guest->status;

        $guest->status = $entryType === 'rsvp' ? 'not_going' : 'cancelled';
        $guest->save();

        do_action('fluent_event/guest/updated', $guest, $previousStatus);

        if (
            $previousStatus === 'going'
            && !empty(Settings::get(Settings::EMAIL_NOTIFICATIONS)['registration_cancellation']['enabled'])
        ) {
            as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
        }

        return [
            'message' => __('Your registration has been cancelled.', 'fluent-event'),
            'event'   => CommunityEvents::formatEvents([$event])[(int) $event->id],
        ];
    }
}
