<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Models\Activity as FcomActivity;
use FluentCommunity\App\Models\BaseSpace as FcomBaseSpace;
use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\CalendarService;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\PermissionManager;
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

    public function register()
    {
        add_filter('fluent_community/feed/new_feed_data_type_event', [$this, 'validateNewPost'], 10, 2);
        add_action('fluent_community/feed/just_created_type_event', [$this, 'createEvent'], 10, 2);
        add_filter('fluent_community/feed/update_feed_data_type_event', [$this, 'validatePostUpdate'], 10, 3);
        add_filter('fluent_community/feed/update_data', [$this, 'keepEventLink'], 10, 2);
        add_action('fluent_community/feed/updated', [$this, 'followSpaceMove'], 10, 2);
        add_action('fluent_event/event/calendar_changed', [$this, 'followCalendarChange']);
        add_action('fluent_community/feed/before_deleted', [$this, 'deleteEvent']);
        // Before CleanupHandler (priority 10) wipes the event's fe_meta, which holds the post link.
        add_action('fluent_event/event/before_deleted', [$this, 'unlinkPost'], 5);
        add_filter('fluent_community/feed_general_config', [$this, 'preloadEvents'], 10, 2);
        add_filter('fluent_community/rendering_feed_model', [$this, 'attachEvent'], 10, 2);
    }

    public function validateNewPost($data, $requestData)
    {
        if (is_wp_error($data)) {
            return $data;
        }

        if (!PermissionManager::userCan('write_events', get_current_user_id())) {
            return new \WP_Error('fluent_event_forbidden', __('You are not allowed to create events.', 'fluent-event'));
        }

        $space = FcomBaseSpace::query()->find((int) Arr::get($data, 'space_id'));

        if (!CommunityEvents::isDiscussionSpace($space)) {
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
        $input = $this->eventInput($requestData);
        $input['calendar_id'] = $this->spaceCalendar($feed->space_id)->id;

        $input['cover_url'] = $this->claimMedia(Arr::get($input, 'cover_url'), $feed, 'event_cover');

        // Images added to the description are fcom uploads too, and need the same claim.
        $input['description'] = preg_replace_callback('/(<img\b[^>]*\bsrc=)(["\'])(.*?)\2/i', function ($match) use ($feed) {
            $url = $this->claimMedia(html_entity_decode($match[3]), $feed, 'event_description');

            return $match[1] . $match[2] . esc_attr($url) . $match[2];
        }, (string) Arr::get($input, 'description', ''));

        $data = EventRequest::format($input);

        try {
            $event = EventService::createEvent($data, $feed->user_id);
        } catch (\Throwable $e) {
            // An event post without its event would only render a dead card.
            $feed->delete();
            throw $e;
        }

        $meta = (array) $feed->meta;
        $meta['event_id'] = (int) $event->id;
        $feed->meta = $meta;
        $feed->save();

        Meta::query()->create([
            'object_type' => Meta::OBJECT_TYPE_EVENT,
            'object_id'   => $event->id,
            'meta_key'    => CommunityEvents::FEED_META_KEY,
            'value'       => (int) $feed->id,
        ]);

        do_action('fluent_event/event/created', $event);
    }

    public function validatePostUpdate($data, $requestData, $existingFeed)
    {
        if (is_wp_error($data)) {
            return $data;
        }

        if (Arr::get($requestData, 'move_to_profile')) {
            return new \WP_Error('fluent_event_space', __('An event post must stay in a discussion space.', 'fluent-event'));
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
        if (!array_key_exists('space_id', (array) $dirty) || !$feed->space_id) {
            return;
        }

        $event = Event::query()->find(CommunityEvents::eventIdOf($feed));

        if (!$event) {
            return;
        }

        $calendarId = (int) $this->spaceCalendar($feed->space_id)->id;

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

        $calendar = Calendar::query()->find($event->calendar_id);
        $space = FcomBaseSpace::query()->find((int) Arr::get((array) $calendar->meta, CalendarService::SPACE_META_KEY));

        // A calendar with no space has nowhere to hold the post, so the post lets go of the event.
        if (!CommunityEvents::isDiscussionSpace($space)) {
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

        if ((int) $feed->space_id === (int) $space->id) {
            return;
        }

        DB::transaction(function () use ($feed, $space) {
            $feed->space_id = $space->id;
            $feed->save();

            FcomActivity::query()->where('feed_id', $feed->id)->update(['space_id' => $space->id]);

            // Topics are space-scoped; the old space's topics do not carry over.
            $feed->terms()->where('taxonomy_name', 'post_topic')->detach();
        });
    }

    public function deleteEvent($feed)
    {
        $event = Event::query()->find(CommunityEvents::eventIdOf($feed));

        if (!$event) {
            return;
        }

        DB::transaction(function () use ($event) {
            do_action('fluent_event/event/before_deleted', $event);

            $event->delete();
        });

        do_action('fluent_event/event/deleted', $event);
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
        $feed->save();
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
        } else {
            // A single post is the event page, the one view that shows the description.
            $event = Event::query()->with(['post'])->find($eventId);
            $formatted = CommunityEvents::formatEvents([$event], ['withDescription' => true])[$eventId] ?? null;
        }

        if ($formatted && !in_array($feed->status, ['published', 'unlisted'], true)) {
            $formatted['registration_open'] = false;
        }

        $feed->fluent_event = $formatted;

        return $feed;
    }

    protected function preload(array $eventIds)
    {
        $eventIds = array_diff(array_filter(array_map('intval', $eventIds)), array_keys($this->formattedEvents));

        if (!$eventIds) {
            return;
        }

        $events = Event::query()->with(['post'])->whereIn('id', $eventIds)->get()->all();
        $formatted = CommunityEvents::formatEvents($events);

        foreach ($eventIds as $eventId) {
            $this->formattedEvents[$eventId] = $formatted[$eventId] ?? null;
        }
    }

    // fcom's cron deletes an unclaimed upload after two hours; tying it to the post also
    // retires it with the post. An active upload already belongs to something else.
    protected function claimMedia($url, $feed, $source)
    {
        $media = FcomHelper::getMediaFromUrl($url);

        if (!$media) {
            return $url;
        }

        if (!$media->is_active) {
            $media->update([
                'is_active'     => true,
                'user_id'       => $feed->user_id,
                'feed_id'       => $feed->id,
                'object_source' => $source,
            ]);
        }

        return $media->public_url;
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

    protected function spaceCalendar($spaceId)
    {
        if ($calendar = CalendarService::findBySpace($spaceId)) {
            return $calendar;
        }

        $space = FcomBaseSpace::query()->find($spaceId);
        $calendar = CalendarService::createForSpace($spaceId, $space->title, $space->slug);

        do_action('fluent_event/calendar/created', $calendar);

        return $calendar;
    }
}
