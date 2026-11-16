import { t, eventStart, eventMonth, eventDay, guestCountLabel, hasEventActions, communityApi } from './utils';
import { MAP_LOCATION, VIDEO_CAMERA } from './icons';
import EventActions from './EventActions';
import EventMenu from './EventMenu';

/*
 * Rendered by FluentCommunity's own Vue for posts with content_type "event", so it
 * is a string template: fcom ships the runtime compiler but no way to share SFCs.
 */
export default {
    name: 'FluentEventCard',
    // Copies: Vue caches a compiled template on the component object, and our page apps render these
    // on our own Vue, whose compiled render silently drops v-model when fcom's Vue runs it.
    components: { EventActions: { ...EventActions }, EventMenu: { ...EventMenu } },
    props: {
        feed: { type: Object, required: true },
        is_single: { type: Boolean, default: false },
        is_child: { type: Boolean, default: false },
    },
    data() {
        return {
            event: this.feed.fluent_event || null,
            // fcom's moderation reports hand over the post without the event the feed views attach.
            loading: !('fluent_event' in this.feed),
            error: '',
        };
    },
    watch: {
        'feed.fluent_event'(value) {
            this.event = value || null;
        },
    },
    mounted() {
        if (this.loading) {
            this.loadEvent();
        }
    },
    computed: {
        startDate() {
            return eventStart(this.event);
        },
        month() {
            return eventMonth(this.event);
        },
        day() {
            return eventDay(this.event);
        },
        countLabel() {
            return guestCountLabel(this.event);
        },
        // Not isOnline: this card runs on fcom's Vue, whose global mixin has an isOnline(dateTime) method.
        isOnlineEvent() {
            return this.event.venue_type === 'online';
        },
        hasActions() {
            return !this.is_child && hasEventActions(this.event);
        },
        // A scheduled or pending post: the server takes answers once it is published.
        unpublishedNote() {
            if (!this.event.is_unpublished || this.event.is_past) {
                return '';
            }

            return this.event.entry_type === 'open'
                ? t('Members can register once this post is published.')
                : t('Members can RSVP once this post is published.');
        },
        eventRoute() {
            const space = this.feed.space && this.feed.space.slug;

            if (!this.event.slug) {
                return null;
            }

            if (space) {
                return { name: 'fe_event_view', params: { space, event_slug: this.event.slug } };
            }

            // A post outside any space is a community-wide event.
            return this.feed.space_id ? null : { name: 'fe_global_event_view', params: { event_slug: this.event.slug } };
        },
        // fcom's admin app (moderation reports) renders the card too, without the portal's event routes.
        hasEventPage() {
            return !!this.eventRoute && this.$router.hasRoute(this.eventRoute.name);
        },
        // There the title links to the portal page by URL, as fcom's own post title does.
        portalUrl() {
            return (window.fluentComAdmin || {}).portal_url || '';
        },
        portalEventUrl() {
            const { space, event_slug: slug } = this.eventRoute.params;

            return this.portalUrl + (space ? `space/${space}/` : '') + `event/${slug}`;
        },
        canOpenEvent() {
            return this.hasEventPage || (!!this.eventRoute && !!this.portalUrl);
        },
    },
    methods: {
        t,
        // Where the title link goes.
        openEvent() {
            if (this.hasEventPage) {
                this.$router.push(this.eventRoute);
            } else if (this.canOpenEvent) {
                window.location.href = this.portalEventUrl;
            }
        },
        apply(response) {
            this.event = response.event;
        },
        async loadEvent() {
            try {
                const response = await communityApi.get(`feeds/${this.feed.id}/by-id`);
                this.event = response.feed.fluent_event || null;
            } catch (error) {
                this.event = null;
            } finally {
                this.loading = false;
            }
        },
    },
    template: `
        <div class="fe-event-card" :class="{ 'fe-event-card--single': is_single, 'fe-event-card--child': is_child }">
            <div v-if="loading" class="fe-event-card__skeleton" role="status" :aria-label="t('Loading event')">
                <span class="fe-event-card__skeleton-cover"></span>
                <span class="fe-event-card__body">
                    <span class="fe-event-card__skeleton-chip"></span>
                    <span class="fe-event-card__skeleton-lines">
                        <span class="fe-event-card__skeleton-line fe-event-card__skeleton-line--title"></span>
                        <span class="fe-event-card__skeleton-line"></span>
                        <span class="fe-event-card__skeleton-line fe-event-card__skeleton-line--short"></span>
                    </span>
                </span>
                <span class="fe-event-card__actions">
                    <span class="fe-event-card__skeleton-line fe-event-card__skeleton-line--count"></span>
                    <span class="fe-event-card__skeleton-button"></span>
                </span>
            </div>
            <p v-else-if="!event" class="fe-event-card__unavailable">{{ t('This event is not available.') }}</p>
            <template v-else>
                <!-- A mouse shortcut to the title link, which stays the keyboard and screen-reader route. -->
                <img
                    v-if="event.cover_url"
                    class="fe-event-card__cover"
                    :class="{ 'fe-event-card__cover--link': canOpenEvent }"
                    :src="event.cover_url"
                    alt=""
                    @click="openEvent"
                />
                <div class="fe-event-card__body">
                    <div class="fe-date-chip" aria-hidden="true">
                        <span class="fe-date-chip__month">{{ month }}</span>
                        <span class="fe-date-chip__day">{{ day }}</span>
                    </div>
                    <div class="fe-event-card__info">
                        <div class="fe-event-card__head">
                            <h3 class="fe-event-card__title" :class="{ 'fe-event-card__title--truncated': !is_single }" :title="is_single ? null : event.title">
                                <router-link
                                    v-if="eventRoute && hasEventPage"
                                    class="fe-event-card__title-link"
                                    :to="eventRoute"
                                >{{ event.title }}</router-link>
                                <a v-else-if="eventRoute && portalUrl" class="fe-event-card__title-link" :href="portalEventUrl">{{ event.title }}</a>
                                <template v-else>{{ event.title }}</template>
                            </h3>
                            <event-menu v-if="!is_child" class="fe-event-card__menu" :event="event"></event-menu>
                        </div>
                        <p class="fe-event-card__meta">{{ startDate }}</p>
                        <p class="fe-event-card__meta fe-event-card__meta--venue">
                            <span v-if="isOnlineEvent" class="fe-event-card__venue-icon" aria-hidden="true">${VIDEO_CAMERA}</span>
                            <span v-else class="fe-event-card__venue-icon" aria-hidden="true">${MAP_LOCATION}</span>
                            {{ isOnlineEvent ? t('Online') : t('In Person') }}
                        </p>
                    </div>
                </div>

                <div v-if="hasActions" class="fe-event-card__actions">
                    <span v-if="countLabel" class="fe-event-card__count">{{ countLabel }}</span>
                    <event-actions :event="event" @done="apply" @error="error = $event"></event-actions>
                </div>

                <p v-if="hasActions && unpublishedNote" class="fe-event-card__note">{{ unpublishedNote }}</p>
                <p v-if="error" class="fe-event-card__error" role="alert">{{ error }}</p>
            </template>
        </div>
    `,
};
