<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Models\Activity as FcomActivity;
use FluentCommunity\App\Models\BaseSpace as FcomBaseSpace;
use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Services\FeedsHelper as FcomFeedsHelper;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\CalendarService;
use FluentEvent\App\Services\EventService;
use FluentEvent\Facade\DB;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Validator\ValidationException;
use FluentEvent\Framework\Validator\Validator;

/**
 * An event post is an ordinary fcom post with `content_type = 'event'` and
 * `meta.event_id`. fcom owns the post; these hooks keep the event in step.
 * fcom turns a thrown exception into a bare HTTP 500, so vetoes return WP_Error.
 */
class FeedHandler
{
    const MEDIA_META_KEYS = ['media_items', 'media_preview', 'audio_medias', 'survey_config', 'document_lists', 'document_ids'];

    protected $formattedEvents = [];

    // Event id => the post it belongs to, loaded with the events.
    protected $feedLinks = [];

    public function register()
    {
        add_filter('fluent_community/feed/message_required', [$this, 'captionRequired'], 10, 3);
        add_filter('fluent_community/feed/new_feed_data_type_event', [$this, 'validateNewPost'], 10, 2);
        add_action('fluent_community/feed/just_created_type_event', [$this, 'createEvent'], 10, 2);
        add_filter('fluent_community/feed/update_feed_data_type_event', [$this, 'validatePostUpdate'], 10, 3);
        add_filter('fluent_community/feed/update_data', [$this, 'keepEventLink'], 10, 2);
        add_action('fluent_community/feed/updated', [$this, 'followSpaceMove'], 10, 2);
        add_action('fluent_event/event/calendar_changed', [$this, 'followCalendarChange']);
        add_action('fluent_community/feed/before_deleted', [$this, 'parkEvent']);
        // Before CleanupHandler (priority 10) wipes the event's fevent_meta, which holds the post link.
        add_action('fluent_event/event/before_deleted', [$this, 'unlinkPost'], 5);
        add_filter('fluent_community/feed_general_config', [$this, 'preloadEvents'], 10, 2);
        add_filter('fluent_community/rendering_feed_model', [$this, 'attachEvent'], 10, 2);
        add_filter('fluent_event/events_api_response', [$this, 'addFeedUrls']);
        add_filter('fluent_event/event_api_response', [$this, 'addFeedUrl']);
    }

    // The event card is the post's content, so its caption is optional, when posting and when editing.
    public function captionRequired($required, $requestData, $existingFeed = null)
    {
        $contentType = $existingFeed ? $existingFeed->content_type : Arr::get($requestData, 'content_type');

        return $contentType === CommunityEvents::CONTENT_TYPE ? false : $required;
    }

    public function validateNewPost($data, $requestData)
    {
        if (is_wp_error($data)) {
            return $data;
        }

        if (!current_user_can('manage_options')) {
            return new \WP_Error('fluent_event_forbidden', __('You are not allowed to create events.', 'fluent-event'));
        }

        // No space is a post on the author's profile: a community-wide event.
        $spaceId = (int) Arr::get($data, 'space_id');

        if (!$spaceId && !FcomHelper::hasGlobalPost()) {
            return $this->globalPostsOffError();
        }

        if ($spaceId && !CommunityEvents::isDiscussionSpace(FcomBaseSpace::query()->find($spaceId))) {
            return new \WP_Error('fluent_event_space', __('Events can only be posted in discussion spaces.', 'fluent-event'));
        }

        if ($error = $this->validationError($this->eventInput($requestData))) {
            return $error;
        }

        $data['content_type'] = CommunityEvents::CONTENT_TYPE;
        $data['meta'] = Arr::except((array) Arr::get($data, 'meta', []), self::MEDIA_META_KEYS);

        return $data;
    }

    public function createEvent($feed, $requestData)
    {
        try {
            $input = $this->eventInput($requestData);
            $input['calendar_id'] = $this->calendarFor($feed->space_id)->id;

            $data = EventRequest::format($input);

            // The event and both links between it and the post land together: the post renders its card
            // from meta.event_id, so an event the post can't find is lost.
            $event = DB::transaction(function () use ($data, $feed) {
                $event = EventService::createEvent($data, $feed->user_id);

                Meta::query()->create([
                    'object_type' => Meta::OBJECT_TYPE_EVENT,
                    'object_id'   => $event->id,
                    'meta_key'    => CommunityEvents::FEED_META_KEY,
                    'value'       => (int) $feed->id,
                ]);

                $meta = (array) $feed->meta;
                $meta['event_id'] = (int) $event->id;
                $feed->meta = $meta;

                if (!$feed->save()) {
                    throw new \RuntimeException('The event post could not be linked to its event.');
                }

                return $event;
            });
        } catch (\Throwable $e) {
            // fcom saved the post before this hook. Without its event it would only render a dead card,
            // and a second try with the same text would be refused as a duplicate.
            $feed->delete();
            throw $e;
        }

        do_action('fluent_event/event/created', $event);
    }

    public function validatePostUpdate($data, $requestData, $existingFeed)
    {
        if (is_wp_error($data)) {
            return $data;
        }

        if (Arr::get($requestData, 'move_to_profile') && !FcomHelper::hasGlobalPost()) {
            return $this->globalPostsOffError();
        }

        if ($newSpaceId = (int) Arr::get($requestData, 'new_space_id')) {
            if (!CommunityEvents::isDiscussionSpace(FcomBaseSpace::query()->find($newSpaceId))) {
                return new \WP_Error('fluent_event_space', __('Events can only be moved to discussion spaces.', 'fluent-event'));
            }
        }

        return $data;
    }

    // Runs on every edit: fcom rebuilds the post meta, which would drop the event link.
    public function keepEventLink($data, $existingFeed)
    {
        $eventId = CommunityEvents::eventIdOf($existingFeed);

        if (!$eventId) {
            return $data;
        }

        $meta = Arr::has($data, 'meta') ? (array) $data['meta'] : (array) $existingFeed->meta;

        $data['content_type'] = CommunityEvents::CONTENT_TYPE;
        $data['meta'] = array_merge(Arr::except($meta, self::MEDIA_META_KEYS), ['event_id' => $eventId]);

        return $data;
    }

    public function followSpaceMove($feed, $dirty)
    {
        if (!array_key_exists('space_id', (array) $dirty)) {
            return;
        }

        $event = CommunityEvents::eventOf($feed);

        if (!$event) {
            return;
        }

        $calendarId = (int) $this->calendarFor($feed->space_id)->id;

        if ((int) $event->calendar_id === $calendarId) {
            return;
        }

        DB::transaction(function () use ($event, $calendarId) {
            $event->calendar_id = $calendarId;
            $event->save();

            Guest::query()->where('event_id', $event->id)->update(['calendar_id' => $calendarId]);
        });

        do_action('fluent_event/event/updated', $event);
    }

    public function followCalendarChange($event)
    {
        $feed = FcomFeed::query()->find(CommunityEvents::feedIdOf($event));

        if (!$feed || CommunityEvents::eventIdOf($feed) !== (int) $event->id) {
            return;
        }

        $calendarMeta = (array) Calendar::query()->find($event->calendar_id)->meta;
        $space = FcomBaseSpace::query()->find((int) Arr::get($calendarMeta, CalendarService::SPACE_META_KEY));
        $isGlobal = Arr::get($calendarMeta, CalendarService::GLOBAL_META_KEY) === true;

        // A site calendar has nowhere to hold the post, so the post lets go of the event.
        if (!$isGlobal && !CommunityEvents::isDiscussionSpace($space)) {
            DB::transaction(function () use ($event) {
                $this->unlinkPost($event);

                Meta::query()
                    ->where('object_type', Meta::OBJECT_TYPE_EVENT)
                    ->where('object_id', $event->id)
                    ->where('meta_key', CommunityEvents::FEED_META_KEY)
                    ->delete();
            });

            return;
        }

        // The Community calendar holds events posted outside any space.
        $spaceId = $isGlobal ? null : (int) $space->id;

        if ((int) $feed->space_id === (int) $spaceId) {
            return;
        }

        // A profile post is public, like fcom's own move to a profile.
        $isPublic = $spaceId ? (int) $space->isPublic() : 1;

        DB::transaction(function () use ($feed, $spaceId, $isPublic) {
            $feed->space_id = $spaceId;
            $feed->save();

            // feed_id holds a course id on course_completed rows, so only the post's own rows move.
            FcomActivity::query()
                ->where('feed_id', $feed->id)
                ->whereIn('action_name', ['feed_published', 'comment_added'])
                ->update(['space_id' => $spaceId, 'is_public' => $isPublic]);

            // Topics are space-scoped; the old space's topics do not carry over.
            $feed->terms()->where('taxonomy_name', 'post_topic')->detach();
        });

        do_action('fluent_community/feed/updated', $feed, ['space_id' => $spaceId]);

        if ($spaceId) {
            do_action('fluent_community/space_feed/updated', $feed);
        }
    }

    // Deleting a post is a moderation call, not an event one: the event keeps its guests as a draft.
    public function parkEvent($feed)
    {
        $event = CommunityEvents::eventOf($feed);

        if (!$event) {
            return;
        }

        DB::transaction(function () use ($event) {
            CommunityEvents::parkEvent($event);
        });

        do_action('fluent_event/event/updated', $event);
    }

    // An event deleted from the admin or with its calendar leaves the post as a plain one.
    public function unlinkPost($event)
    {
        $feed = FcomFeed::query()->find(CommunityEvents::feedIdOf($event));

        if (!$feed || CommunityEvents::eventIdOf($feed) !== (int) $event->id) {
            return;
        }

        $feed->content_type = 'text';
        $feed->meta = Arr::except((array) $feed->meta, ['event_id']);

        // The event card was the post's content and its caption is optional, so an empty one would leave a blank post.
        if (trim((string) $feed->message) === '') {
            $feed->message = $event->title ?: __('This event is no longer available.', 'fluent-event');
            $feed->message_rendered = wp_kses_post(FcomFeedsHelper::mdToHtml($feed->message));
        }

        $feed->save();
    }

    // The portal edit page links here: the post's text and space are edited on the post itself.
    public function addFeedUrl($data)
    {
        $this->addFeedUrls(['events' => [$data['event']]]);

        return $data;
    }

    public function addFeedUrls($data)
    {
        $eventIds = [];

        foreach ($data['events'] as $event) {
            $eventIds[] = $event->id;
        }

        if (!$eventIds) {
            return $data;
        }

        $feedIds = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->whereIn('object_id', $eventIds)
            ->where('meta_key', CommunityEvents::FEED_META_KEY)
            ->pluck('value', 'object_id')
            ->toArray();

        if (!$feedIds) {
            return $data;
        }

        $feeds = FcomFeed::query()->with(['space'])->whereIn('id', $feedIds)->get()->keyBy('id');

        foreach ($data['events'] as $event) {
            $feed = $feeds->get((int) Arr::get($feedIds, $event->id));

            if ($feed && CommunityEvents::eventIdOf($feed) === (int) $event->id) {
                $event->feed_url = esc_url_raw($feed->getPermalink());
                // The portal's "Clone Event" loads the post by it to copy its text, space and topics.
                $event->feed_slug = $feed->slug;
            }
        }

        return $data;
    }

    public function preloadEvents($config, $feeds)
    {
        if (!Arr::get($config, 'is_collection')) {
            return $config;
        }

        $eventIds = [];

        foreach ($feeds as $feed) {
            if ($feed && $feed->content_type === CommunityEvents::CONTENT_TYPE) {
                $eventIds[] = CommunityEvents::eventIdOf($feed);
            }
        }

        $this->preload($eventIds);

        return $config;
    }

    // In collection mode fcom ignores the return value, so the feed is changed in place.
    public function attachEvent($feed, $config)
    {
        if ($feed->content_type !== CommunityEvents::CONTENT_TYPE) {
            return $feed;
        }

        $eventId = CommunityEvents::eventIdOf($feed);

        if (Arr::get($config, 'is_collection')) {
            if (!array_key_exists($eventId, $this->formattedEvents)) {
                $this->preload([$eventId]);
            }

            $formatted = $this->formattedEvents[$eventId] ?? null;
            $linkedFeedId = $this->feedLinks[$eventId] ?? 0;
        } else {
            // A single post is the event page, the one view that shows the description.
            $event = Event::query()->with(['post'])->find($eventId);
            $formatted = EventService::formatEvents([$event], ['withDescription' => true])[$eventId] ?? null;
            $linkedFeedId = $event ? CommunityEvents::feedIdOf($event) : 0;
        }

        // Only the event that links back to this post: after a reset or reinstall reuses ids, an old
        // post would otherwise show a new, maybe private or draft, event's title and date.
        if ($linkedFeedId !== (int) $feed->id) {
            $formatted = null;
        }

        if ($formatted && !in_array($feed->status, ['published', 'unlisted'], true)) {
            $formatted['registration_open'] = false;
        }

        $feed->fluent_event = $formatted;
        // fcom's views show a post's featured image; an event post's is its event's cover.
        $feed->featured_image = $formatted ? ($formatted['cover_url'] ?: null) : null;

        return $feed;
    }

    protected function preload(array $eventIds)
    {
        $eventIds = array_diff(array_filter(array_map('intval', $eventIds)), array_keys($this->formattedEvents));

        if (!$eventIds) {
            return;
        }

        $events = Event::query()->with(['post'])->whereIn('id', $eventIds)->get()->all();
        $formatted = EventService::formatEvents($events);

        foreach ($eventIds as $eventId) {
            $this->formattedEvents[$eventId] = $formatted[$eventId] ?? null;
        }

        $links = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('meta_key', CommunityEvents::FEED_META_KEY)
            ->whereIn('object_id', $eventIds)
            ->get(['object_id', 'value']);

        foreach ($links as $link) {
            $this->feedLinks[(int) $link->object_id] = (int) $link->value;
        }
    }

    protected function eventInput($requestData)
    {
        $input = EventRequest::sanitize((array) Arr::get($requestData, 'event', []));
        $input['status'] = 'private';

        return $input;
    }

    protected function validationError(array $input)
    {
        $rules = Arr::except(EventRequest::rules($input), ['calendar_id']);
        $validator = Validator::make($input, $rules, EventRequest::messages());

        if ($validator->validate()->fails()) {
            return $this->toWpError($validator->errors());
        }

        try {
            EventRequest::validateDateWindow($input);
        } catch (ValidationException $e) {
            return $this->toWpError($e->errors());
        }

        return null;
    }

    protected function toWpError($errors)
    {
        $message = __('Please check the event details.', 'fluent-event');

        foreach ((array) $errors as $fieldErrors) {
            $first = is_array($fieldErrors) ? reset($fieldErrors) : $fieldErrors;

            if (is_string($first) && $first !== '') {
                $message = $first;
                break;
            }
        }

        return new \WP_Error('fluent_event_invalid', $message, $errors);
    }

    protected function globalPostsOffError()
    {
        return new \WP_Error('fluent_event_space', __('Community-wide events need global posts, which are turned off in FluentCommunity settings.', 'fluent-event'));
    }

    // The space's own calendar, or the Community calendar for a post outside any space.
    protected function calendarFor($spaceId)
    {
        if ($calendar = $spaceId ? CalendarService::findBySpace($spaceId) : CalendarService::findGlobal()) {
            return $calendar;
        }

        if ($spaceId) {
            $space = FcomBaseSpace::query()->find($spaceId);
            $calendar = CalendarService::createForSpace($spaceId, $space->title, $space->slug);
        } else {
            $calendar = CalendarService::createGlobal();
        }

        do_action('fluent_event/calendar/created', $calendar);

        return $calendar;
    }
}
