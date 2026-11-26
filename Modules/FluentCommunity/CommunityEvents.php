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
