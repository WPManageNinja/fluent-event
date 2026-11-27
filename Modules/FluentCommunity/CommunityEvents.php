<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Models\Feed as FcomFeed;
use FluentCommunity\App\Models\Space as FcomSpace;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Meta;
use FluentEvent\Framework\Support\Arr;

/**
 * What the community side needs about events: which post carries one and who may
 * see it. Event data stays in FluentEvent; a post only holds `meta.event_id`.
 */
class CommunityEvents
{
    const CONTENT_TYPE = 'event';
    const FEED_META_KEY = 'fcom_feed_id';

    public static function eventIdOf($feed)
    {
        return (int) Arr::get((array) $feed->meta, 'event_id');
    }

    public static function feedIdOf($event)
    {
        return (int) Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $event->id)
            ->where('meta_key', self::FEED_META_KEY)
            ->value('value');
    }

    // The post's event only when that event links back to the post: a reset or reinstall can reuse an old post's event id.
    public static function eventOf($feed)
    {
        $event = Event::query()->find(self::eventIdOf($feed));

        return $event && self::feedIdOf($event) === (int) $feed->id ? $event : null;
    }

    public static function parkEvent($event)
    {
        Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $event->id)
            ->where('meta_key', self::FEED_META_KEY)
            ->delete();

        $event->status = 'draft';
        $event->save();

        wp_update_post(['ID' => $event->post_id, 'post_status' => Event::postStatusFor('draft')]);
    }

    public static function isDiscussionSpace($space)
    {
        return $space && $space->type === 'community';
    }

    // A post outside any space is open to everyone who can see the portal, as fcom's own feed shows it.
    public static function canViewSpacelessPosts()
    {
        return FcomHelper::getCurrentUser() || FcomHelper::isPublicAccessible();
    }

    // Discussion spaces whose posts the current viewer can read.
    public static function visibleSpaceIds()
    {
        $user = FcomHelper::getCurrentUser();

        if (!$user) {
            if (!FcomHelper::isPublicAccessible()) {
                return [];
            }

            return FcomSpace::query()->where('privacy', 'public')->pluck('id')->toArray();
        }

        $spaceIds = array_filter((array) $user->getAccessSpaceIds());

        if (!$spaceIds) {
            return [];
        }

        return FcomSpace::query()->whereIn('id', $spaceIds)->pluck('id')->toArray();
    }

    // Events whose post the viewer can read: published, by an active author, in one of $spaceIds (or in none).
    // Matched in SQL, so only what was asked for is ever loaded.
    public static function whereVisible($query, array $spaceIds, $withSpaceless)
    {
        return $query->whereIn('id', function ($query) use ($spaceIds, $withSpaceless) {
            $query->select('fevent_meta.object_id')
                ->from('fevent_meta')
                ->join('fcom_posts', 'fcom_posts.id', '=', 'fevent_meta.value')
                // fcom hides posts whose author is blocked, pending or deactivated.
                ->join('fcom_xprofile', 'fcom_xprofile.user_id', '=', 'fcom_posts.user_id')
                ->where('fevent_meta.object_type', Meta::OBJECT_TYPE_EVENT)
                ->where('fevent_meta.meta_key', self::FEED_META_KEY)
                ->where('fcom_posts.content_type', self::CONTENT_TYPE)
                ->where('fcom_posts.status', 'published')
                ->where('fcom_xprofile.status', 'active')
                ->where(function ($query) use ($spaceIds, $withSpaceless) {
                    $query->whereIn('fcom_posts.space_id', $spaceIds);

                    if ($withSpaceless) {
                        $query->orWhereNull('fcom_posts.space_id');
                    }
                });
        });
    }

    // fcom's global search, `events` type: the events the portal's Events page would list whose title has every
    // word, upcoming soonest first, then past most recent first. $args: limit, page, space (slug), sort.
    public static function search($searchQuery, $user, array $args)
    {
        $spaceIds = self::visibleSpaceIds();
        $withSpaceless = self::canViewSpacelessPosts();

        if ($spaceSlug = Arr::get($args, 'space')) {
            $spaceIds = FcomSpace::query()->whereIn('id', $spaceIds)->where('slug', $spaceSlug)->pluck('id')->toArray();
            $withSpaceless = false;
        }

        $limit = max(1, (int) Arr::get($args, 'limit', 6));
        $page = max(1, (int) Arr::get($args, 'page', 1));

        $events = self::whereVisible(Event::query()->with(['post']), $spaceIds, $withSpaceless)
            ->byStatus(Event::LIVE_STATUSES)
            ->whereHas('post', function ($query) use ($searchQuery) {
                $searchQuery->whereAllTerms($query, ['post_title']);
            })
            ->byPeriod('')
            ->offset(($page - 1) * $limit)
            ->limit($limit + 1)
            ->get()
            ->all();

        $hasMore = count($events) > $limit;
        $events = array_slice($events, 0, $limit);

        $eventSpaceIds = Meta::query()
            ->join('fcom_posts', 'fcom_posts.id', '=', 'fevent_meta.value')
            ->where('fevent_meta.object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('fevent_meta.meta_key', self::FEED_META_KEY)
            ->whereIn('fevent_meta.object_id', array_map(function ($event) {
                return (int) $event->id;
            }, $events) ?: [0])
            ->pluck('fcom_posts.space_id', 'fevent_meta.object_id')
            ->toArray();

        $spaces = FcomSpace::query()->whereIn('id', array_filter($eventSpaceIds) ?: [0])->get()->keyBy('id');

        $items = array_map(function ($event) use ($eventSpaceIds, $spaces) {
            $space = $spaces->get((int) Arr::get($eventSpaceIds, $event->id));

            return [
                'id'            => (int) $event->id,
                'title'         => $event->title,
                'slug'          => $event->post->post_name ?? '',
                'start_at_site' => $event->start_at_site,
                'venue_type'    => $event->venue_type,
                'space'         => $space ? ['title' => $space->title, 'slug' => $space->slug] : null,
            ];
        }, $events);

        return ['items' => $items, 'has_more' => $hasMore];
    }

    public static function canViewFeed($feed)
    {
        if (!$feed || !in_array($feed->status, ['published', 'unlisted'], true)) {
            return false;
        }

        // fcom hides posts whose author is blocked, pending or deactivated.
        if (!$feed->xprofile || $feed->xprofile->status !== 'active') {
            return false;
        }

        if (!$feed->space_id) {
            return self::canViewSpacelessPosts();
        }

        $space = $feed->space;

        if (!self::isDiscussionSpace($space)) {
            return false;
        }

        $user = FcomHelper::getCurrentUser();

        if (!$user && !FcomHelper::isPublicAccessible()) {
            return false;
        }

        return (bool) $space->verifyUserPermisson($user ?: null, 'can_view_posts', false);
    }

    // The event and its post, when the current viewer may see both.
    public static function visibleEvent($id)
    {
        $event = Event::query()->with(['post'])->find((int) $id);

        if (!$event || $event->status === 'draft') {
            return [null, null];
        }

        $feed = FcomFeed::query()->find(self::feedIdOf($event));

        if (!$feed || self::eventIdOf($feed) !== (int) $event->id || !self::canViewFeed($feed)) {
            return [null, null];
        }

        return [$event, $feed];
    }
}
