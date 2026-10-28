<?php

namespace FluentEvent\App\Http\Controllers;

use FluentCommunity\App\Models\Space as FcomSpace;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
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

        $from = $request->getSafe('from', 'sanitize_text_field');
        $to = $request->getSafe('to', 'sanitize_text_field');

        $query = Event::query()
            ->with(['post'])
            // Visibility is matched in SQL, so only what was asked for is ever loaded.
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

        $formatted = CommunityEvents::formatEvents($events);

        $slugs = Meta::query()
            ->join('fcom_posts', 'fcom_posts.id', '=', 'fe_meta.value')
            ->where('fe_meta.object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('fe_meta.meta_key', CommunityEvents::FEED_META_KEY)
            ->whereIn('fe_meta.object_id', array_keys($formatted))
            ->pluck('fcom_posts.slug', 'fe_meta.object_id')
            ->toArray();

        $items = [];

        foreach ($events as $event) {
            $items[] = array_merge($formatted[$event->id], [
                'post_slug' => $slugs[$event->id],
            ]);
        }

        return array_merge(['events' => $items], $pages);
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

        // A member answers only going / not going; waitlisted and cancelled are the host's to change.
        if ($guest && in_array($guest->status, ['waitlisted', 'cancelled'], true)) {
            return $this->sendError(['message' => __('Only the host can change this RSVP.', 'fluent-event')], 422);
        }

        $isNew = !$guest;
        $previousStatus = $guest ? $guest->status : null;
        $capacity = (int) Arr::get((array) $event->meta, 'capacity');
        $hasWaitlist = $capacity && Arr::get((array) $event->meta, 'waitlist') === 'yes';
        $takesSeat = $status === 'going' && !in_array($previousStatus, Event::SEAT_STATUSES, true);

        $guest = DB::transaction(function () use ($event, $feed, $user, $guest, $status, $capacity, $hasWaitlist, $takesSeat) {
            if ($capacity && $takesSeat) {
                // Holds every other RSVP to this event until commit, so two members cannot both take the last seat.
                Event::query()->where('id', $event->id)->lockForUpdate()->first();

                $taken = Guest::query()
                    ->where('event_id', $event->id)
                    ->whereIn('status', Event::SEAT_STATUSES)
                    ->count();

                if ($taken >= $capacity) {
                    if (!$hasWaitlist) {
                        return null;
                    }

                    $status = 'waitlisted';
                }
            }

            if (!$guest) {
                return Guest::create([
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
            }

            // Going again keeps a checked-in guest checked in.
            if ($status === 'not_going' || !in_array($guest->status, Event::SEAT_STATUSES, true)) {
                $guest->status = $status;
            }

            $guest->user_id = $user->ID;
            $guest->save();

            return $guest;
        });

        if (!$guest) {
            return $this->sendError(['message' => __('This event is full.', 'fluent-event')], 422);
        }

        if ($isNew) {
            do_action('fluent_event/guest/created', $guest);
        } else {
            do_action('fluent_event/guest/updated', $guest, $previousStatus);
        }

        $emails = Settings::get(Settings::EMAIL_NOTIFICATIONS);
        $wasGoing = $previousStatus === 'going';

        if ($guest->status === 'going' && !$wasGoing && !empty($emails['registration_confirmation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_confirmation', [(int) $guest->id], 'fluent-event');
        } elseif ($guest->status === 'not_going' && $wasGoing && !empty($emails['registration_cancellation']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_cancellation', [(int) $guest->id], 'fluent-event');
        } elseif ($guest->status === 'waitlisted' && $previousStatus !== 'waitlisted' && !empty($emails['registration_waitlist']['enabled'])) {
            as_enqueue_async_action('fluent_event/send_registration_waitlist', [(int) $guest->id], 'fluent-event');
        }

        $message = __('You are registered to this event.', 'fluent-event');

        if ($guest->status === 'not_going') {
            $message = __("You've marked this event as Not Going.", 'fluent-event');
        } elseif ($guest->status === 'waitlisted') {
            $message = __('You are on the waitlist.', 'fluent-event');
        }

        return [
            'message' => $message,
            'status'  => $guest->status,
            'event'   => CommunityEvents::formatEvents([$event])[(int) $event->id],
        ];
    }

    // The portal renders headless, so the WordPress editor its create-event page uses is handed over
    // as the markup WordPress would print, and loaded only when that page opens.
    public function getEditorAssets(Request $request)
    {
        wp_enqueue_editor();

        // wplink searches posts over admin-ajax, which only wp-admin defines.
        wp_add_inline_script('wplink', 'window.ajaxurl = window.ajaxurl || ' . wp_json_encode(admin_url('admin-ajax.php')) . ';', 'before');

        ob_start();
        wp_print_styles(['editor-buttons', 'buttons']);
        wp_print_scripts(['editor', 'quicktags', 'wplink', 'jquery-ui-autocomplete']);
        \_WP_Editors::print_default_editor_scripts();

        return ['html' => ob_get_clean()];
    }
}
