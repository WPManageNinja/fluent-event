<?php

namespace FluentEvent\App\Http\Controllers;

use FluentCommunity\App\Models\Space as FcomSpace;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\Settings;
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

        if ($spaceSlug = $request->getSafe('space', 'sanitize_title')) {
            $spaceIds = FcomSpace::query()->whereIn('id', $spaceIds)->where('slug', $spaceSlug)->pluck('id')->toArray();
        }

        $events = Event::query()
            ->with(['post'])
            // Visibility is matched in SQL, so only the requested page is ever loaded.
            ->whereIn('id', function ($query) use ($spaceIds) {
                $query->select('fe_meta.object_id')
                    ->from('fe_meta')
                    ->join('fcom_posts', 'fcom_posts.id', '=', 'fe_meta.value')
                    ->where('fe_meta.object_type', Meta::OBJECT_TYPE_EVENT)
                    ->where('fe_meta.meta_key', CommunityEvents::FEED_META_KEY)
                    ->where('fcom_posts.content_type', CommunityEvents::CONTENT_TYPE)
                    ->where('fcom_posts.status', 'published')
                    ->whereIn('fcom_posts.space_id', $spaceIds);
            })
            ->byStatus(['public', 'private'])
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->inDateRange(
                $request->getSafe('from', 'sanitize_text_field'),
                $request->getSafe('to', 'sanitize_text_field')
            )
            ->byPeriod($request->getSafe('scope', 'sanitize_text_field', 'upcoming'))
            ->paginate(EventRequest::perPage($request));

        $formatted = CommunityEvents::formatEvents($events->items());

        $slugs = Meta::query()
            ->join('fcom_posts', 'fcom_posts.id', '=', 'fe_meta.value')
            ->where('fe_meta.object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('fe_meta.meta_key', CommunityEvents::FEED_META_KEY)
            ->whereIn('fe_meta.object_id', array_keys($formatted))
            ->pluck('fcom_posts.slug', 'fe_meta.object_id')
            ->toArray();

        $items = [];

        foreach ($events->items() as $event) {
            $items[] = array_merge($formatted[$event->id], [
                'post_slug' => $slugs[$event->id],
            ]);
        }

        return [
            'events'       => $items,
            'total'        => $events->total(),
            'current_page' => $events->currentPage(),
            'last_page'    => $events->lastPage(),
        ];
    }

    // RSVP only: an open event is view-only and takes no registration.
    public function register(Request $request, $id)
    {
        [$event, $feed] = CommunityEvents::visibleEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        if (Arr::get((array) $event->meta, 'entry_type') !== 'rsvp') {
            return $this->sendError(['message' => __('This event does not take registrations.', 'fluent-event')], 422);
        }

        if ($event->end_at < gmdate(DateTimeHelper::FORMAT)) {
            return $this->sendError(['message' => __('This event has already ended.', 'fluent-event')], 422);
        }

        $status = $request->getSafe('status', 'sanitize_text_field');

        if (!in_array($status, ['going', 'not_going'], true)) {
            return $this->sendError(['message' => __('Please choose going or not going.', 'fluent-event')], 422);
        }

        $user = wp_get_current_user();

        // The member's own row first, else one an admin added under their email.
        $guest = Guest::query()->where('event_id', $event->id)->where('user_id', $user->ID)->first()
            ?: Guest::query()->where('event_id', $event->id)->where('email', $user->user_email)->first();

        if ($guest && $guest->user_id && (int) $guest->user_id !== (int) $user->ID) {
            return $this->sendError(['message' => __('This email is already registered for this event.', 'fluent-event')], 422);
        }

        $previousStatus = $guest ? $guest->status : null;

        if (!$guest) {
            $guest = Guest::create([
                'event_id'    => $event->id,
                'calendar_id' => $event->calendar_id,
                'group_id'    => wp_generate_password(32, false, false),
                'is_buyer'    => 1,
                'name'        => $user->display_name,
                'email'       => $user->user_email,
                'user_id'     => $user->ID,
                'status'      => $status,
                'source'      => 'community',
                'source_id'   => (int) $feed->id,
            ]);

            do_action('fluent_event/guest/created', $guest);
        } else {
            // Going again keeps a checked-in guest checked in.
            if ($status === 'not_going' || !in_array($guest->status, Event::SEAT_STATUSES, true)) {
                $guest->status = $status;
            }

            $guest->user_id = $user->ID;
            $guest->save();

            do_action('fluent_event/guest/updated', $guest, $previousStatus);
        }

        $emails = Settings::get(Settings::EMAIL_NOTIFICATIONS);
        $wasGoing = $previousStatus === 'going';

        if ($guest->status === 'going' && !$wasGoing && !empty($emails['registration_confirmation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_confirmation', [(int) $guest->id], 'fluent-event');
        } elseif ($guest->status === 'not_going' && $wasGoing && !empty($emails['registration_cancellation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
        }

        return [
            'message' => $guest->status === 'not_going'
                ? __('Your RSVP has been updated.', 'fluent-event')
                : __('You are registered.', 'fluent-event'),
            'status'  => $guest->status,
            'event'   => CommunityEvents::formatEvents([$event])[(int) $event->id],
        ];
    }
}
