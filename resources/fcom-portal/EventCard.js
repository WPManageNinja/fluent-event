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
    components: { EventActions, EventMenu },
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
        isOnline() {
            return this.event.venue_type === 'online';
        },
        hasActions() {
            return !this.is_child && hasEventActions(this.event);
        },
        // fcom's admin app (moderation reports) renders the card too, without the portal's event route.
        hasEventPage() {
            return this.$router.hasRoute('fe_event_view');
        },
    },
    methods: {
        t,
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
            <template v-if="loading"></template>
            <p v-else-if="!event" class="fe-event-card__unavailable">{{ t('This event is not available.') }}</p>
            <template v-else>
                <img v-if="event.cover_url" class="fe-event-card__cover" :src="event.cover_url" alt="" />
                <div class="fe-event-card__body">
                    <div class="fe-date-chip" aria-hidden="true">
                        <span class="fe-date-chip__month">{{ month }}</span>
                        <span class="fe-date-chip__day">{{ day }}</span>
                    </div>
                    <div class="fe-event-card__info">
                        <div class="fe-event-card__head">
                            <h3 class="fe-event-card__title" :class="{ 'fe-event-card__title--truncated': !is_single }" :title="is_single ? null : event.title">
                                <router-link
                                    v-if="feed.slug && hasEventPage"
                                    class="fe-event-card__title-link"
                                    :to="{ name: 'fe_event_view', params: { feed_slug: feed.slug } }"
                                >{{ event.title }}</router-link>
                                <template v-else>{{ event.title }}</template>
                            </h3>
                            <event-menu v-if="!is_child" class="fe-event-card__menu" :event="event"></event-menu>
                        </div>
                        <p class="fe-event-card__meta">{{ startDate }}</p>
                        <p class="fe-event-card__meta fe-event-card__meta--venue">
                            <span v-if="isOnline" class="fe-event-card__venue-icon" aria-hidden="true">${VIDEO_CAMERA}</span>
                            <span v-else class="fe-event-card__venue-icon" aria-hidden="true">${MAP_LOCATION}</span>
                            {{ isOnline ? t('Online') : t('In person') }}
                        </p>
                    </div>
                </div>

                <div v-if="hasActions" class="fe-event-card__actions">
                    <span v-if="countLabel" class="fe-event-card__count">{{ countLabel }}</span>
                    <event-actions :event="event" @done="apply" @error="error = $event"></event-actions>
                </div>

                <p v-if="error" class="fe-event-card__error" role="alert">{{ error }}</p>
            </template>
        </div>
    `,
};
