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

            <img v-if="event.cover_url" class="fe-event-view__cover" :src="event.cover_url" alt=""/>

            <div class="fe-event-view__grid">
                <div class="fe-event-view__main">
                    <div class="fe-event-view__intro">
                        <h1 class="fe-event-view__title">{{ event.title }}</h1>

                        <ul class="fe-event-view__meta">
                            <li class="fe-event-view__meta-item">
                                <el-icon aria-hidden="true"><Calendar/></el-icon>
                                <span>{{ siteDateLabel }}</span>
                            </li>
                            <li class="fe-event-view__meta-item">
                                <el-icon aria-hidden="true"><Clock/></el-icon>
                                <span>{{ siteTimeLabel }}</span>
                            </li>
                            <li class="fe-event-view__meta-item">
                                <el-icon aria-hidden="true">
                                    <VideoCamera v-if="isOnline"/>
                                    <MapLocation v-else/>
                                </el-icon>
                                <span>{{ isOnline ? $t('Online') : (event.venue || $t('In person')) }}</span>
                            </li>
                        </ul>

                        <p class="fe-event-view__origin">
                            <img v-if="feed.xprofile && feed.xprofile.avatar" class="fe-event-view__origin-avatar" :src="feed.xprofile.avatar" alt=""/>
                            <span v-if="feed.space">{{ $t('Posted by %s in', author) }} <strong class="fe-event-view__origin-space">{{ feed.space.title }}</strong></span>
                            <span v-else>{{ $t('Posted by %s on their profile', author) }}</span>
                            <span aria-hidden="true">·</span>
                            <a class="fe-event-view__origin-link" :href="router.resolve(postRoute).href" @click.prevent="router.push(postRoute)">{{ $t('View post') }}</a>
                        </p>
                    </div>

                    <div class="fe-event-view__body">
                        <section v-if="event.description" class="fe-event-view__card">
                            <h2 class="fe-event-view__section-title">{{ $t('About This Event') }}</h2>
                            <div class="fe-event-view__description" v-html="event.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->
                        </section>
                    </div>
                </div>

                <aside class="fe-event-view__card fe-event-view__side" :aria-label="$t('Registration')">
                    <div class="fe-event-view__when">
                        <span class="fe-date-chip" aria-hidden="true">
                            <span class="fe-date-chip__month">{{ month }}</span>
                            <span class="fe-date-chip__day">{{ day }}</span>
                        </span>

                        <div class="fe-event-view__when-text">
                            <span class="fe-event-view__when-date">{{ dateLabel }}</span>
                            <span>{{ eventTimeLabel }}</span>
                            <span v-if="localTimeLabel" class="fe-event-view__when-local">{{ localTimeLabel }}</span>
                        </div>
                    </div>

                    <span v-if="phase === 'live'" class="fe-live-badge">{{ $t('Happening now') }}</span>
                    <span v-else class="fe-event-view__status" :class="`fe-event-view__status--${phase}`">{{ statusLabel }}</span>

                    <div v-if="hasActions || countLabel" class="fe-event-view__rsvp">
                        <event-actions v-if="hasActions" :event="event" @done="applyResponse" @error="error = $event"></event-actions>
                        <span v-if="countLabel" class="fe-event-view__count">{{ countLabel }}</span>
                    </div>

                    <p v-if="error" class="fe-event-view__error" role="alert">{{ error }}</p>

                    <div v-if="!isOnline && (event.venue || event.address)" class="fe-event-view__place">
                        <el-icon class="fe-event-view__place-icon" aria-hidden="true"><MapLocation/></el-icon>
                        <div class="fe-event-view__place-text">
                            <span v-if="event.venue" class="fe-event-view__venue">{{ event.venue }}</span>
                            <span v-if="event.address" class="fe-event-view__address">{{ event.address }}</span>
                        </div>
                    </div>

                    <template v-else-if="isOnline && phase !== 'ended'">
                        <a v-if="meetingLink" class="el-button fcom_primary_button fe-event-view__join" :href="meetingLink" target="_blank" rel="noopener noreferrer">
                            <el-icon aria-hidden="true"><VideoCamera/></el-icon>
                            <span>{{ $t('Join online') }}</span>
                        </a>
                        <p v-else-if="event.has_meeting_url" class="fe-event-view__note">{{ $t('Please register for the event to see the meeting link.') }}</p>
                    </template>

                    <div v-if="canAddToCalendar" class="fe-event-view__calendar">
                        <span class="fe-event-view__calendar-label">{{ $t('Add to calendar') }}</span>
                        <div class="fe-event-view__calendar-links">
                            <a class="fe-event-view__calendar-link" :href="googleCalendarUrl" target="_blank" rel="noopener noreferrer">
                                <span class="fe-event-view__calendar-icon" aria-hidden="true" v-html="icons.GOOGLE"></span> <!-- coding-rules-scan:ignore — static SVG constant from icons.js -->
                                {{ $t('Google Calendar') }}
                            </a>
                            <button type="button" class="fe-event-view__calendar-link" @click="saveToCalendar">
                                <span class="fe-event-view__calendar-icon" aria-hidden="true" v-html="icons.APPLE"></span> <!-- coding-rules-scan:ignore — static SVG constant from icons.js -->
                                {{ $t('Apple / Outlook') }}
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </template>
    </div>
</template>

<script>
import { ArrowLeft, Calendar, Clock, MapLocation, VideoCamera } from '@element-plus/icons-vue';
import { communityApi, eventsApi, dateFormat, eventPhase, guestCountLabel, hasEventActions, isSeated, portalVars, timeFormat } from '../utils';
import { buildIcs, downloadIcs, icsStamp } from '@/utils/date';
import { APPLE, GOOGLE } from '../icons';
import EventActions from '../EventActions';
import EventMenu from '../EventMenu';

const dayjs = (value) => window.dayjs(value);
const utc = (value) => window.dayjs.utc(value);

// The event on its own page, inside its space or at the portal's top level when community-wide,
// read through fcom's post endpoint so it is visible exactly when its post is.
export default {
    name: 'EventViewPage',
    components: { ArrowLeft, Calendar, Clock, MapLocation, VideoCamera, EventActions, EventMenu },
    props: {
        // Empty for a community-wide event.
        spaceSlug: { type: String, default: '' },
        eventSlug: { type: String, required: true },
        router: { type: Object, required: true },
    },
    data() {
        return {
            loading: true,
            event: null,
            feed: null,
            error: '',
            icons: { APPLE, GOOGLE },
        };
    },
    computed: {
        isOnline() {
            return this.event.venue_type === 'online';
        },
        author() {
            return (this.feed.xprofile || {}).display_name || '';
        },
        postRoute() {
            return { name: 'single_feed', params: { feed_slug: this.feed.slug } };
        },
        month() {
            return dayjs(this.event.start_at_local).format('MMM');
        },
        day() {
            return dayjs(this.event.start_at_local).format('D');
        },
        dateLabel() {
            return this.dateRange(this.event.start_at_local, this.event.end_at_local);
        },
        eventTimeLabel() {
            return this.timeRange(this.event.start_at_local, this.event.end_at_local, this.event.timezone);
        },
        // The heading reads the site's clock, like every list; the side card leads with the event's own.
        siteDateLabel() {
            return this.dateRange(this.event.start_at_site, this.event.end_at_site);
        },
        siteTimeLabel() {
            return this.timeRange(this.event.start_at_site, this.event.end_at_site, portalVars().timezone);
        },
        // The site's clock, which the lists use; only when it reads differently from the event's.
        localTimeLabel() {
            const { start_at_site: start, end_at_site: end } = this.event;

            if (start === this.event.start_at_local && end === this.event.end_at_local) {
                return '';
            }

            const endFormat = dayjs(end).isSame(start, 'day') ? timeFormat() : `MMM D, ${timeFormat()}`;

            return `${dayjs(start).format(`MMM D, ${timeFormat()}`)} – ${dayjs(end).format(endFormat)} (${portalVars().timezone})`;
        },
        phase() {
            return eventPhase(this.event);
        },
        statusLabel() {
            if (this.phase === 'ended') {
                return this.$t('Ended');
            }

            /* translators: %s: relative time, e.g. "in 3 days" */
            return this.$t('Starts %s', utc(this.event.start_at).fromNow());
        },
        meetingLink() {
            return /^https?:\/\//i.test(this.event.online_meeting_url) ? this.event.online_meeting_url : '';
        },
        countLabel() {
            return guestCountLabel(this.event);
        },
        // The status above already says Ended, so an ended event gets no RSVP slot of its own here.
        hasActions() {
            return this.phase !== 'ended' && hasEventActions(this.event);
        },
        // Once someone is going, or for a no-entry event anyone can attend.
        canAddToCalendar() {
            return this.phase !== 'ended' && (this.event.entry_type === 'none' || isSeated(this.event));
        },
        place() {
            if (this.isOnline) {
                return this.meetingLink || window.location.href;
            }

            return [this.event.venue, this.event.address].filter(Boolean).join(', ');
        },
        // Plain text for calendar apps; block tags become line breaks so words don't run together.
        summary() {
            const html = (this.event.description || '')
                .replace(/<li[^>]*>/gi, '• ')
                .replace(/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/gi, '\n');
            const text = new DOMParser().parseFromString(html, 'text/html').body.textContent
                .replace(/\n{3,}/g, '\n\n')
                .trim();

            return `${text.slice(0, 600)}\n\n${window.location.href}`;
        },
        googleCalendarUrl() {
            const params = new URLSearchParams({
                action: 'TEMPLATE',
                text: this.event.title,
                dates: `${icsStamp(this.event.start_at)}/${icsStamp(this.event.end_at)}`,
                details: this.summary,
                location: this.place,
            });

            return `https://calendar.google.com/calendar/render?${params.toString()}`;
        },
    },
    mounted() {
        this.fetchEvent();
    },
    methods: {
        applyResponse(response) {
            this.event = { ...this.event, ...response.event };
        },
        dateRange(start, end) {
            const from = dayjs(start);

            if (from.isSame(dayjs(end), 'day')) {
                return from.format(dateFormat());
            }

            return `${from.format('MMM D')} – ${dayjs(end).format('MMM D, YYYY')}`;
        },
        timeRange(start, end, zone) {
            const format = dayjs(start).isSame(dayjs(end), 'day') ? timeFormat() : `MMM D, ${timeFormat()}`;
            const range = `${dayjs(start).format(format)} – ${dayjs(end).format(format)}`;

            return zone ? `${range} (${zone})` : range;
        },
        async fetchEvent() {
            try {
                // The URL carries the event's own slug; its post's slug is what fcom reads it by.
                const { feed_slug: feedSlug } = await eventsApi.get('community/events/by-slug', { space: this.spaceSlug, slug: this.eventSlug });
                const response = await communityApi.get(`feeds/${feedSlug}/by-slug`);

                this.feed = response.feed;
                this.event = response.feed.fluent_event || null;
            } catch (error) {
                this.event = null;
            } finally {
                this.loading = false;
            }
        },
        saveToCalendar() {
            const text = buildIcs({
                id: this.event.id,
                title: this.event.title,
                start_at: this.event.start_at,
                end_at: this.event.end_at,
                description: this.summary,
                location: this.place,
                url: window.location.href,
            });

            downloadIcs(text, `${this.eventSlug}.ics`);
        },
        goBack() {
            this.router.back();
        },
    },
};
</script>
