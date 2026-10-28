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

                    <div v-if="showMeter" class="fe-capacity">
                        <div class="fe-capacity__head">
                            <span>{{ countLabel }}</span>
                            <span v-if="!isFullForMe && phase !== 'ended'" class="fe-capacity__status">{{ capacityStatus }}</span>
                        </div>
                        <div
                            class="fe-capacity__track"
                            role="progressbar"
                            aria-valuemin="0"
                            :aria-valuemax="event.capacity"
                            :aria-valuenow="event.guest_count"
                            :aria-valuetext="countLabel"
                            :aria-label="$t('Capacity')"
                        >
                            <span class="fe-capacity__fill" :style="{ width: `${capacityPercent}%` }"></span>
                        </div>
                    </div>

                    <div
                        v-if="notice"
                        class="fe-event-view__notice"
                        :class="{
                            'fe-event-view__notice--cancelled': notice.icon === 'cancelled',
                            'fe-event-view__notice--waiting': notice.icon === 'waiting',
                        }"
                    >
                        <span class="fe-event-view__notice-icon" aria-hidden="true">
                            <el-icon>
                                <Clock v-if="notice.icon === 'waiting' || notice.icon === 'upcoming'"/>
                                <CircleClose v-else-if="notice.icon === 'cancelled'"/>
                                <Ticket v-else/>
                            </el-icon>
                        </span>
                        <div class="fe-event-view__notice-text">
                            <span class="fe-event-view__notice-title">{{ notice.title }}</span>
                            <span class="fe-event-view__notice-desc">{{ notice.desc }}</span>
                        </div>
                    </div>

                    <div v-if="hasActions || (countLabel && !showMeter)" class="fe-event-view__rsvp">
                        <event-actions v-if="hasActions" :event="event" @done="applyResponse" @error="error = $event"></event-actions>
                        <span v-if="countLabel && !showMeter" class="fe-event-view__count">{{ countLabel }}</span>
                    </div>

                    <p v-if="closesLabel" class="fe-event-view__note">{{ closesLabel }}</p>

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
import { ArrowLeft, Calendar, CircleClose, Clock, MapLocation, Ticket, VideoCamera } from '@element-plus/icons-vue';
import { communityApi, eventPhase, guestCountLabel, isSeated, portalVars, siteDateTime, timeFormat } from '../utils';
import { buildIcs, downloadIcs, icsStamp } from '@/utils/date';
import { APPLE, GOOGLE } from '../icons';
import EventActions from '../EventActions';
import EventMenu from '../EventMenu';

const dayjs = (value) => window.dayjs(value);
const utc = (value) => window.dayjs.utc(value);

// The event on its own page, read through fcom's post endpoint so it is visible exactly when its post is.
export default {
    name: 'EventViewPage',
    components: { ArrowLeft, Calendar, CircleClose, Clock, MapLocation, Ticket, VideoCamera, EventActions, EventMenu },
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
            icons: { APPLE, GOOGLE },
        };
    },
    computed: {
        isOnline() {
            return this.event.venue_type === 'online';
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
        isFullForMe() {
            return this.event.is_full && !isSeated(this.event);
        },
        // Waitlisted and cancelled are the host's to change; the member gets a notice, not an RSVP.
        isHostOwned() {
            return ['waitlisted', 'cancelled'].includes(this.event.my_status);
        },
        canJoinWaitlist() {
            return this.isFullForMe && !!this.event.waitlist && this.phase !== 'ended' && !this.isHostOwned;
        },
        // Before registration opens or after it closes; a member already going keeps the RSVP to give up the seat.
        registrationShut() {
            return this.event.entry_type === 'rsvp'
                && this.phase !== 'ended'
                && !this.event.registration_open
                && !isSeated(this.event)
                && !this.isHostOwned;
        },
        closesLabel() {
            if (!this.event.registration_open || !this.event.registration_closes_at) {
                return '';
            }

            return this.$t('Registration closes %s', siteDateTime(this.event.registration_closes_at));
        },
        notice() {
            if (this.event.my_status === 'cancelled') {
                return {
                    icon: 'cancelled',
                    title: this.$t('Your registration has been cancelled'),
                    desc: this.$t('Contact the host if you would still like to attend.'),
                };
            }

            if (this.event.my_status === 'waitlisted' && this.phase !== 'ended') {
                return {
                    icon: 'waiting',
                    title: this.$t('You are on the waitlist'),
                    desc: this.$t('If a seat opens up, you will be moved to going.'),
                };
            }

            if (this.registrationShut) {
                return this.event.registration_opens_at
                    ? {
                        icon: 'upcoming',
                        title: this.$t('Registration not open yet'),
                        desc: this.$t('Registration opens %s.', siteDateTime(this.event.registration_opens_at)),
                    }
                    : {
                        icon: 'full',
                        title: this.$t('Registration closed'),
                        desc: this.$t('This event is no longer taking registrations.'),
                    };
            }

            if (!this.isFullForMe) {
                return null;
            }

            return {
                icon: 'full',
                title: this.$t('Event full'),
                desc: this.canJoinWaitlist
                    ? this.$t('Join the waitlist to be moved to going if a seat opens up.')
                    : this.$t('This event is full and no longer taking registrations.'),
            };
        },
        // Only a capped event whose host shows the count; an ended one keeps it as a record of how full it got.
        showMeter() {
            return !!this.event.capacity && !!this.countLabel;
        },
        capacityPercent() {
            return Math.min(100, Math.round((this.event.guest_count / this.event.capacity) * 100));
        },
        capacityStatus() {
            if (this.event.is_full) {
                return this.$t('Event full');
            }

            const left = this.event.capacity - this.event.guest_count;

            return left === 1 ? this.$t('1 spot left') : this.$t('%d spots left', left);
        },
        // The status above already says Ended, so an ended event gets no RSVP slot; a full one keeps it only to join the waitlist.
        hasActions() {
            return this.event.entry_type === 'rsvp'
                && this.phase !== 'ended'
                && !this.isHostOwned
                && !this.registrationShut
                && (!this.isFullForMe || this.canJoinWaitlist);
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
    },
    methods: {
        applyResponse(response) {
            this.event = { ...this.event, ...response.event };
        },
        dateRange(start, end) {
            const from = dayjs(start);

            if (from.isSame(dayjs(end), 'day')) {
                return from.format('dddd, MMMM D, YYYY');
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
                const response = await communityApi.get(`feeds/${this.feedSlug}/by-slug`);

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

            downloadIcs(text, `${this.feedSlug}.ics`);
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
