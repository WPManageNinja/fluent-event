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
                <div class="fe-event-view__intro">
                    <h1 class="fe-event-view__title">{{ event.title }}</h1>

                    <ul class="fe-event-view__meta">
                        <li class="fe-event-view__meta-item">
                            <el-icon aria-hidden="true"><Calendar/></el-icon>
                            <span>{{ dateLabel }}</span>
                        </li>
                        <li class="fe-event-view__meta-item">
                            <el-icon aria-hidden="true"><Clock/></el-icon>
                            <span>{{ eventTimeLabel }}</span>
                        </li>
                        <li class="fe-event-view__meta-item">
                            <el-icon aria-hidden="true">
                                <VideoCamera v-if="isOnline"/>
                                <MapLocation v-else/>
                            </el-icon>
                            <span>{{ isOnline ? $t('Online') : (event.venue || $t('In person')) }}</span>
                        </li>
                    </ul>
                </div>

                <aside class="fe-event-view__card fe-event-view__side" :aria-label="$t('Registration')">
                    <div class="fe-event-view__when">
                        <span class="fe-event-view__date-chip" aria-hidden="true">
                            <span class="fe-event-view__date-month">{{ month }}</span>
                            <span class="fe-event-view__date-day">{{ day }}</span>
                        </span>

                        <div class="fe-event-view__when-text">
                            <span class="fe-event-view__when-date">{{ dateLabel }}</span>
                            <span>{{ eventTimeLabel }}</span>
                            <span v-if="localTimeLabel" class="fe-event-view__when-local">{{ localTimeLabel }}</span>
                        </div>
                    </div>

                    <span class="fe-event-view__status" :class="`fe-event-view__status--${phase}`">{{ statusLabel }}</span>

                    <div v-if="hasActions || countLabel" class="fe-event-view__rsvp">
                        <event-actions v-if="hasActions" :event="event" @done="event = $event.event" @error="error = $event"></event-actions>
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
                            <button type="button" class="fe-event-view__calendar-link" @click="downloadIcs">
                                <span class="fe-event-view__calendar-icon" aria-hidden="true" v-html="icons.APPLE"></span> <!-- coding-rules-scan:ignore — static SVG constant from icons.js -->
                                {{ $t('Apple / Outlook') }}
                            </button>
                        </div>
                    </div>
                </aside>

                <div class="fe-event-view__body">
                    <section v-if="event.description" class="fe-event-view__card">
                        <h2 class="fe-event-view__section-title">{{ $t('About This Event') }}</h2>
                        <div class="fe-event-view__description" v-html="event.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->
                    </section>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { ArrowLeft, Calendar, Clock, MapLocation, VideoCamera } from '@element-plus/icons-vue';
import { communityApi, eventDay, eventMonth, guestCountLabel, hasEventActions, isSeated, timeFormat, timezoneOffset } from '../utils';
import { APPLE, GOOGLE } from '../icons';
import EventActions from '../EventActions';
import EventMenu from '../EventMenu';

const dayjs = (value) => window.dayjs(value);
const utc = (value) => window.dayjs.utc(value);

// Calendar files need commas, semicolons and newlines escaped.
const icsText = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/([,;])/g, '\\$1').replace(/\r?\n/g, '\\n');

const encoder = new TextEncoder();

// Lines are capped at 75 bytes, not characters; fold between whole characters so accents, CJK and emoji survive.
const icsFold = (line) => {
    const lines = [];
    let current = '';
    let bytes = 0;

    for (const char of line) {
        const size = encoder.encode(char).length;

        // A continuation line starts with a space, which counts toward its 75.
        if (bytes + size > (lines.length ? 74 : 75)) {
            lines.push(current);
            current = '';
            bytes = 0;
        }

        current += char;
        bytes += size;
    }

    lines.push(current);

    return lines.join('\r\n ');
};
const icsStamp = (value) => utc(value).format('YYYYMMDD[T]HHmmss[Z]');

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
            now: Date.now(),
            ticker: null,
            icons: { APPLE, GOOGLE },
        };
    },
    computed: {
        isOnline() {
            return this.event.venue_type === 'online';
        },
        isMultiDay() {
            return !dayjs(this.event.start_at_local).isSame(dayjs(this.event.end_at_local), 'day');
        },
        month() {
            return eventMonth(this.event);
        },
        day() {
            return eventDay(this.event);
        },
        dateLabel() {
            const start = dayjs(this.event.start_at_local);

            if (!this.isMultiDay) {
                return start.format('dddd, MMMM D, YYYY');
            }

            return `${start.format('MMM D')} – ${dayjs(this.event.end_at_local).format('MMM D, YYYY')}`;
        },
        timeFormat() {
            return this.isMultiDay ? `MMM D, ${timeFormat()}` : timeFormat();
        },
        eventTimeLabel() {
            const range = `${dayjs(this.event.start_at_local).format(this.timeFormat)} – ${dayjs(this.event.end_at_local).format(this.timeFormat)}`;
            const offset = this.event.timezone ? timezoneOffset(this.event.timezone, utc(this.event.start_at).toDate()) : '';

            return offset ? `${range} ${offset}` : range;
        },
        // Only when the visitor's clock differs from the event's, so nobody converts zones in their head.
        localTimeLabel() {
            const start = utc(this.event.start_at);

            if (!this.event.timezone || start.local().utcOffset() === start.tz(this.event.timezone).utcOffset()) {
                return '';
            }

            /* translators: 1: start time, 2: end time, both in the visitor's own timezone */
            return this.$t('%1$s – %2$s in your time',
                start.local().format(this.timeFormat),
                utc(this.event.end_at).local().format(this.timeFormat)
            );
        },
        phase() {
            if (this.event.is_past || this.now >= utc(this.event.end_at).valueOf()) {
                return 'ended';
            }

            return this.now >= utc(this.event.start_at).valueOf() ? 'live' : 'upcoming';
        },
        statusLabel() {
            if (this.phase === 'ended') {
                return this.$t('Ended');
            }

            if (this.phase === 'live') {
                return this.$t('Happening now');
            }

            /* translators: %s: relative time, e.g. "in 3 days" */
            return this.$t('Starts %s', utc(this.event.start_at).from(this.now));
        },
        meetingLink() {
            return /^https?:\/\//i.test(this.event.online_meeting_url) ? this.event.online_meeting_url : '';
        },
        countLabel() {
            return guestCountLabel(this.event);
        },
        hasActions() {
            return hasEventActions(this.event);
        },
        // Once someone is going, or for open events anyone can attend.
        canAddToCalendar() {
            return this.phase !== 'ended' && (this.event.entry_type === 'open' || isSeated(this.event));
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
        // Keeps "Starts in …" and the live / ended state current while the page stays open.
        this.ticker = setInterval(() => {
            this.now = Date.now();
        }, 60000);
    },
    beforeUnmount() {
        clearInterval(this.ticker);
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
        downloadIcs() {
            const lines = [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//Fluent Events//EN',
                'CALSCALE:GREGORIAN',
                'BEGIN:VEVENT',
                `UID:fluent-event-${this.event.id}@${window.location.host}`,
                `DTSTAMP:${icsStamp(new Date().toISOString())}`,
                `DTSTART:${icsStamp(this.event.start_at)}`,
                `DTEND:${icsStamp(this.event.end_at)}`,
                `SUMMARY:${icsText(this.event.title)}`,
                `DESCRIPTION:${icsText(this.summary)}`,
                `LOCATION:${icsText(this.place)}`,
                `URL:${window.location.href}`,
                'END:VEVENT',
                'END:VCALENDAR',
            ];

            const blob = new Blob([lines.map(icsFold).join('\r\n')], { type: 'text/calendar;charset=utf-8' });
            const link = document.createElement('a');

            link.href = URL.createObjectURL(blob);
            link.download = `${this.feedSlug}.ics`;
            link.click();
            URL.revokeObjectURL(link.href);
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
