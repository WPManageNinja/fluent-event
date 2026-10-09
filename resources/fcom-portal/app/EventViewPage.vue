<template>
    <div class="fe-event-view">
        <el-skeleton v-if="loading" :rows="6" animated/>

        <el-empty v-else-if="!event" :description="$t('This event is not available.')"/>

        <template v-else>
            <div class="fe-event-view__head">
                <button type="button" class="fe-event-view__back" @click="goBack">
                    <el-icon aria-hidden="true"><ArrowLeft/></el-icon>
                    <span>{{ $t('Back') }}</span>
                </button>

                <event-menu :event="event"></event-menu>
            </div>

            <div class="fe-event-view__grid">
                <div>
                    <img v-if="event.cover_url" class="fe-event-view__cover" :src="event.cover_url" alt=""/>

                    <h1 class="fe-event-view__title">{{ event.title }}</h1>

                    <ul class="fe-event-view__meta">
                        <li class="fe-event-view__meta-item">
                            <el-icon aria-hidden="true"><Calendar/></el-icon>
                            <span>{{ dateLabel }}</span>
                        </li>
                        <li class="fe-event-view__meta-item">
                            <el-icon aria-hidden="true"><Clock/></el-icon>
                            <span>{{ timeLabel }}</span>
                        </li>
                        <li class="fe-event-view__meta-item">
                            <el-icon aria-hidden="true">
                                <VideoCamera v-if="isOnline"/>
                                <MapLocation v-else/>
                            </el-icon>
                            <span>{{ isOnline ? $t('Online') : $t('In person') }}</span>
                        </li>
                    </ul>

                    <section v-if="event.description" class="fe-event-view__card">
                        <h2 class="fe-event-view__section-title">{{ $t('About This Event') }}</h2>
                        <div class="fe-event-view__description" v-html="event.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->
                    </section>
                </div>

                <aside class="fe-event-view__card fe-event-view__side">
                    <h3 class="fe-event-view__panel-title">{{ $t('Details') }}</h3>

                    <dl class="fe-event-view__facts">
                        <div class="fe-event-view__fact">
                            <dt class="fe-event-view__fact-label">{{ isOnline ? $t('Online') : $t('Location') }}</dt>
                            <dd class="fe-event-view__fact-value">
                                <template v-if="isOnline">
                                    <a v-if="meetingLink" :href="meetingLink" target="_blank" rel="noopener noreferrer">{{ $t('Join the meeting') }}</a>
                                    <template v-else-if="event.has_meeting_url">{{ $t('Please register for the event to see the meeting link.') }}</template>
                                    <template v-else>{{ $t('Online event') }}</template>
                                </template>
                                <template v-else>
                                    <span class="fe-event-view__line">{{ event.venue || $t('In person') }}</span>
                                    <span v-if="event.address" class="fe-event-view__line">{{ event.address }}</span>
                                </template>
                            </dd>
                        </div>

                        <div v-if="countLabel" class="fe-event-view__fact">
                            <dt class="fe-event-view__fact-label">{{ $t('Going') }}</dt>
                            <dd class="fe-event-view__fact-value">{{ countLabel }}</dd>
                        </div>

                        <div v-if="host" class="fe-event-view__fact">
                            <dt class="fe-event-view__fact-label">{{ $t('Host') }}</dt>
                            <dd class="fe-event-view__fact-value">{{ host }}</dd>
                        </div>
                    </dl>

                    <event-actions v-if="hasActions" :event="event" @done="event = $event.event" @error="error = $event"></event-actions>

                    <p v-if="error" class="fe-event-view__error" role="alert">{{ error }}</p>
                </aside>
            </div>
        </template>
    </div>
</template>

<script>
import { ArrowLeft, Calendar, Clock, MapLocation, VideoCamera } from '@element-plus/icons-vue';
import { communityApi, guestCountLabel, hasEventActions, timeFormat } from '../utils';
import EventActions from '../EventActions';
import EventMenu from '../EventMenu';

const dayjs = (value) => window.dayjs(value);

// The event on its own page, read through fcom's post endpoint so it is visible exactly when its post is.
export default {
    name: 'EventViewPage',
    components: { ArrowLeft, Calendar, Clock, MapLocation, VideoCamera, EventActions, EventMenu },
    props: {
        feedSlug: { type: String, required: true },
        router: { type: Object, required: true },
    },
    data() {
        return {
            loading: true,
            event: null,
            feed: null,
            error: '',
        };
    },
    computed: {
        isOnline() {
            return this.event.venue_type === 'online';
        },
        isMultiDay() {
            return !dayjs(this.event.start_at_local).isSame(dayjs(this.event.end_at_local), 'day');
        },
        dateLabel() {
            const start = dayjs(this.event.start_at_local);

            if (!this.isMultiDay) {
                return start.format('dddd, MMMM D, YYYY');
            }

            return `${start.format('MMM D')} – ${dayjs(this.event.end_at_local).format('MMM D, YYYY')}`;
        },
        timeLabel() {
            const format = this.isMultiDay ? `MMM D, ${timeFormat()}` : timeFormat();
            const range = `${dayjs(this.event.start_at_local).format(format)} – ${dayjs(this.event.end_at_local).format(format)}`;

            return this.event.timezone ? `${range} (${this.event.timezone})` : range;
        },
        meetingLink() {
            return /^https?:\/\//i.test(this.event.online_meeting_url) ? this.event.online_meeting_url : '';
        },
        countLabel() {
            return guestCountLabel(this.event);
        },
        host() {
            return (this.feed.xprofile && this.feed.xprofile.display_name) || '';
        },
        hasActions() {
            return hasEventActions(this.event);
        },
    },
    mounted() {
        this.fetchEvent();
    },
    methods: {
        async fetchEvent() {
            try {
                const response = await communityApi.get(`feeds/${this.feedSlug}/by-slug`);

                this.feed = response.feed;
                this.event = response.feed.fluent_event || null;
            } catch (error) {
                this.event = null;
            } finally {
                this.loading = false;
            }
        },
        // Back to wherever the member came from; a direct visit falls back to the space's Events tab.
        goBack() {
            if (window.history.state && window.history.state.back) {
                this.router.back();
                return;
            }

            const space = this.feed.space;

            this.router.push(space ? { name: 'fe_space_events', params: { space: space.slug } } : { name: 'fe_events' });
        },
    },
};
</script>
