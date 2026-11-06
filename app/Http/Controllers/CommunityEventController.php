<?php

namespace FluentEvent\App\Http\Controllers;

use FluentCommunity\App\Models\Space as FcomSpace;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Http\Requests\GuestRequest;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\EventService;
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
                    // fcom hides posts whose author is blocked, pending or deactivated.
                    ->join('fcom_xprofile', 'fcom_xprofile.user_id', '=', 'fcom_posts.user_id')
                    ->where('fe_meta.object_type', Meta::OBJECT_TYPE_EVENT)
                    ->where('fe_meta.meta_key', CommunityEvents::FEED_META_KEY)
                    ->where('fcom_posts.content_type', CommunityEvents::CONTENT_TYPE)
                    ->where('fcom_posts.status', 'published')
                    ->where('fcom_xprofile.status', 'active')
                    ->whereIn('fcom_posts.space_id', $spaceIds);
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

        $spaceSlugs = Meta::query()
            ->join('fcom_posts', 'fcom_posts.id', '=', 'fe_meta.value')
            ->join('fcom_spaces', 'fcom_spaces.id', '=', 'fcom_posts.space_id')
            ->where('fe_meta.object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('fe_meta.meta_key', CommunityEvents::FEED_META_KEY)
            ->whereIn('fe_meta.object_id', array_keys($formatted))
            ->pluck('fcom_spaces.slug', 'fe_meta.object_id')
            ->toArray();

        $items = [];

        foreach ($events as $event) {
            $items[] = array_merge($formatted[$event->id], [
                'space_slug' => $spaceSlugs[$event->id],
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

        $space = $feed ? FcomSpace::query()->find($feed->space_id) : null;

        if (!$event || !$space || $space->slug !== $request->getSafe('space', 'sanitize_title')) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        return ['feed_slug' => $feed->slug];
    }

    // Members answer an RSVP either way; an open event only registers them (from its form), with no undo.
    public function register(Request $request, $id)
    {
        [$event, $feed] = CommunityEvents::visibleEvent($id);

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

        $registered = RegistrationService::registerMember($event, wp_get_current_user(), $status, $name, [
            'source'    => 'fluent_community',
            'source_id' => (int) $feed->id,
        ]);

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
            'event'   => EventService::formatEvents([$event])[(int) $event->id],
        ];
    }

    // A logged-out visitor registers for an open event by name and email; members use register().
    public function registerGuest(Request $request, $id)
    {
        [$event, $feed] = CommunityEvents::visibleEvent($id);

        if (!$event) {
            return $this->sendError(['message' => __('This event is not available.', 'fluent-event')], 404);
        }

        if (!wp_verify_nonce($request->getSafe('nonce', 'sanitize_text_field'), 'fe_guest_register_' . $event->id)) {
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
        $this->validate(
            $data,
            Arr::only(GuestRequest::createRules($event->id), ['name', 'email']),
            GuestRequest::messages()
        );

        $guest = RegistrationService::registerVisitor($event, $data, ['source' => 'fluent_community', 'source_id' => (int) $feed->id]);

        do_action('fluent_event/guest/created', $guest);

        RegistrationService::queueEmail($guest, null);

        $seats = EventService::seatCounts([$event->id]);

        return [
            'message' => __('You are registered.', 'fluent-event'),
            'status'  => $guest->status,
            'event'   => EventService::formatEvent($event, $seats[(int) $event->id] ?? 0, $guest->status, false, false),
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
