import { t, eventDateRange, eventMonth, eventDay, guestCountLabel, hasEventActions } from './utils';
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
            error: '',
            descriptionExpanded: false,
            descriptionOverflows: false,
        };
    },
    watch: {
        'feed.fluent_event'(value) {
            this.event = value || null;
        },
        'event.description'() {
            this.descriptionExpanded = false;
            this.$nextTick(this.observeDescription);
        },
    },
    mounted() {
        this.observeDescription();
    },
    beforeUnmount() {
        if (this.descriptionObserver) {
            this.descriptionObserver.disconnect();
        }
    },
    computed: {
        dateRange() {
            return eventDateRange(this.event);
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
        place() {
            if (this.event.venue_type === 'online') {
                return t('Online event');
            }
            return [this.event.venue, this.event.address].filter(Boolean).join(', ');
        },
        meetingLink() {
            const url = this.event.online_meeting_url;
            return /^https?:\/\//i.test(url) ? url : '';
        },
        hasActions() {
            return !this.is_child && hasEventActions(this.event);
        },
    },
    methods: {
        t,
        // Re-measures when the text grows after layout, e.g. once an image in it loads.
        observeDescription() {
            if (this.descriptionObserver) {
                this.descriptionObserver.disconnect();
            }

            const body = this.$refs.descriptionBody;

            if (body && typeof ResizeObserver !== 'undefined') {
                this.descriptionObserver = new ResizeObserver(() => this.measureDescription());
                this.descriptionObserver.observe(body);
            } else {
                this.measureDescription();
            }
        },
        measureDescription() {
            const box = this.$refs.descriptionBox;

            if (box && !this.descriptionExpanded) {
                this.descriptionOverflows = box.scrollHeight > box.clientHeight + 1;
            }
        },
        apply(response) {
            this.event = response.event;
        },
    },
    template: `
        <div class="fe-event-card" :class="{ 'fe-event-card--single': is_single, 'fe-event-card--child': is_child }">
            <p v-if="!event" class="fe-event-card__unavailable">{{ t('This event is not available.') }}</p>
            <template v-else>
                <img v-if="event.cover_url" class="fe-event-card__cover" :src="event.cover_url" alt="" />
                <div class="fe-event-card__body">
                    <div class="fe-event-card__date" aria-hidden="true">
                        <span class="fe-event-card__month">{{ month }}</span>
                        <span class="fe-event-card__day">{{ day }}</span>
                    </div>
                    <div class="fe-event-card__info">
                        <div class="fe-event-card__head">
                            <h3 class="fe-event-card__title" :class="{ 'fe-event-card__title--truncated': !is_single }" :title="is_single ? null : event.title">
                                <router-link
                                    v-if="!is_single && feed.slug"
                                    class="fe-event-card__title-link"
                                    :to="{ name: 'single_feed', params: { feed_slug: feed.slug } }"
                                >{{ event.title }}</router-link>
                                <template v-else>{{ event.title }}</template>
                            </h3>
                            <event-menu v-if="!is_child" class="fe-event-card__menu" :event="event"></event-menu>
                        </div>
                        <p class="fe-event-card__meta">{{ dateRange }} <span v-if="event.timezone">({{ event.timezone }})</span></p>
                        <p v-if="place" class="fe-event-card__meta">
                            <a v-if="meetingLink" :href="meetingLink" target="_blank" rel="noopener noreferrer" :title="t('Join the meeting')">{{ place }}</a>
                            <template v-else>{{ place }}</template>
                        </p>
                        <p v-if="event.has_meeting_url && !meetingLink" class="fe-event-card__meta fe-event-card__meta--muted">
                            {{ t('Please register for the event to see the meeting link.') }}
                        </p>
                        <p v-if="countLabel" class="fe-event-card__meta fe-event-card__meta--muted">{{ countLabel }}</p>
                    </div>
                </div>

                <!-- Description is wp_kses_post-filtered by FluentEvent before it reaches the payload. -->
                <div v-if="is_single && event.description" class="fe-event-card__description">
                    <div
                        ref="descriptionBox"
                        class="fe-event-card__description-box"
                        :class="{ 'fe-event-card__description-box--clamped': !descriptionExpanded }"
                    >
                        <div ref="descriptionBody" v-html="event.description"></div>
                    </div>
                    <button
                        v-if="descriptionOverflows"
                        type="button"
                        class="fe-event-card__more"
                        :aria-expanded="descriptionExpanded ? 'true' : 'false'"
                        @click="descriptionExpanded = !descriptionExpanded"
                    >{{ descriptionExpanded ? t('See less') : t('See more') }}</button>
                </div>

                <div v-if="hasActions" class="fe-event-card__actions">
                    <event-actions :event="event" @done="apply" @error="error = $event"></event-actions>
                </div>

                <p v-if="error" class="fe-event-card__error" role="alert">{{ error }}</p>
            </template>
        </div>
    `,
};
