<template>
    <div class="fe-events-page">
        <div class="fe-events-page__header">
            <h1 class="fe-events-page__title">{{ spaceSlug ? $t('Events') : $t('All Events') }}</h1>
            <el-button
                v-if="canCreate"
                class="fcom_primary_button"
                @click="createEvent"
            >
                {{ $t('Create new event') }}
            </el-button>
        </div>

        <el-tabs v-model="tab" class="fe-events-page__tabs" @tab-change="changeTab">
            <el-tab-pane :label="$t('All')" name="all"/>
            <el-tab-pane :label="$t('Upcoming')" name="upcoming"/>
            <el-tab-pane :label="$t('Past')" name="past"/>
        </el-tabs>

        <div class="fe-events-page__toolbar">
            <el-input
                v-model="search"
                class="fe-events-page__search"
                clearable
                :placeholder="$t('Search events')"
                :aria-label="$t('Search events')"
                @keydown.enter="reload"
                @clear="reload"
            >
                <template #prefix>
                    <el-icon><Search/></el-icon>
                </template>
            </el-input>

            <segmented-switch
                v-model="view"
                :options="views"
                :label="$t('Switch between card, timeline and calendar view')"
            />
        </div>

        <p v-if="error" class="fe-events-page__error" role="alert">{{ error }}</p>

        <events-calendar
            v-if="view === 'calendar'"
            v-model:mode="calendarMode"
            v-loading="loading"
            :events="events"
            :date="calendarDate"
            @change-date="calendarDate = $event"
            @open="openPost"
        />

        <div
            v-else
            v-loading="loading"
            class="fe-events-page__list"
            :class="{ 'fe-events-page__list--cards': view === 'cards', 'fe-events-page__list--timeline': view === 'timeline' }"
        >
            <div v-if="!loading && !events.length" class="fe-events-page__empty">
                <span class="fe-events-page__empty-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 20 20" fill="none">
                        <path d="M6.66667 1.66699V4.16699M13.3333 1.66699V4.16699M2.5 7.50033H17.5M4.16667 3.33366H15.8333C16.7538 3.33366 17.5 4.07985 17.5 5.00033V16.667C17.5 17.5875 16.7538 18.3337 15.8333 18.3337H4.16667C3.24619 18.3337 2.5 17.5875 2.5 16.667V5.00033C2.5 4.07985 3.24619 3.33366 4.16667 3.33366Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>

                <h2 class="fe-events-page__empty-title">{{ emptyState.title }}</h2>
                <p class="fe-events-page__empty-text">{{ emptyState.text }}</p>
            </div>

            <div
                v-for="event in events"
                :key="event.id"
                class="fe-events-page__row"
                :class="{ 'fe-events-page__row--timeline': view === 'timeline' }"
            >
                <template v-if="view === 'timeline'">
                    <div class="fe-events-page__gutter">
                        <span class="fe-events-page__date">{{ timelineDate(event) }}</span>
                        <span class="fe-events-page__weekday">{{ weekday(event) }}</span>
                    </div>
                    <span class="fe-events-page__dot" aria-hidden="true"></span>
                </template>

                <div
                    class="fe-event-item"
                    :class="{ 'fe-event-item--card': view === 'cards' }"
                >
                    <img v-if="event.cover_url" class="fe-event-item__cover" :src="event.cover_url" alt=""/>
                    <span v-else class="fe-event-item__cover fe-event-item__cover--empty" aria-hidden="true">
                        <el-icon><Calendar/></el-icon>
                    </span>

                    <span v-if="view === 'cards'" class="fe-event-item__date" aria-hidden="true">
                        <span class="fe-event-item__month">{{ month(event) }}</span>
                        <span class="fe-event-item__day">{{ day(event) }}</span>
                    </span>

                    <div class="fe-event-item__info">
                        <div class="fe-event-item__head">
                            <a href="#" class="fe-event-item__title" :title="event.title" @click.prevent="openPost(event)">
                                <span class="fe-event-item__title-text">{{ event.title }}</span>
                            </a>
                            <event-menu class="fe-event-item__menu" :event="event"></event-menu>
                        </div>
                        <span class="fe-event-item__meta fe-event-item__meta--date">{{ startDate(event) }}</span>
                        <span class="fe-event-item__meta">{{ event.venue_type === 'online' ? $t('Online') : $t('In person') }}</span>
                        <p v-if="errors[event.id]" class="fe-event-item__error" role="alert">{{ errors[event.id] }}</p>
                    </div>

                    <div v-if="hasActions(event)" class="fe-event-item__actions">
                        <span v-if="countLabel(event)" class="fe-event-item__count">{{ countLabel(event) }}</span>
                        <event-actions
                            :event="event"
                            @done="applyResponse(event, $event)"
                            @error="errors[event.id] = $event"
                        ></event-actions>
                    </div>
                </div>
            </div>

            <div v-if="currentPage < lastPage" class="fe-events-page__more">
                <el-button class="fcom_secondary_button" :loading="loading" @click="fetchEvents(currentPage + 1)">{{ $t('Load more') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script>
import { Calendar, Grid, Search, Tickets } from '@element-plus/icons-vue';
import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
import Storage from '@/utils/Storage';
import { eventsApi, errorMessage, eventMonth, eventDay, guestCountLabel, portalVars, hasEventActions } from '../utils';
import EventActions from '../EventActions';
import EventMenu from '../EventMenu';
import EventsCalendar from './EventsCalendar.vue';

const dayjs = (value) => window.dayjs(value);

export default {
    name: 'EventsPage',
    components: { Calendar, Search, EventActions, EventMenu, EventsCalendar, SegmentedSwitch },
    props: {
        spaceSlug: { type: String, default: '' },
        router: { type: Object, required: true },
    },
    data() {
        const savedTab = Storage.get('portal_events_tab');
        const savedView = Storage.get('portal_events_view');
        const savedMode = Storage.get('portal_events_calendar_mode');

        return {
            tab: ['all', 'upcoming', 'past'].includes(savedTab) ? savedTab : 'upcoming',
            view: ['cards', 'timeline', 'calendar'].includes(savedView) ? savedView : 'timeline',
            search: '',
            calendarDate: dayjs().format('YYYY-MM-DD'),
            calendarMode: ['month', 'week', 'day'].includes(savedMode) ? savedMode : 'month',
            events: [],
            errors: {},
            currentPage: 1,
            lastPage: 1,
            loading: false,
            error: '',
            requestId: 0,
        };
    },
    computed: {
        canCreate() {
            return !!this.spaceSlug && !!portalVars().can_create_events;
        },
        views() {
            return [
                { value: 'cards', label: this.$t('Cards'), icon: Grid },
                { value: 'timeline', label: this.$t('Timeline'), icon: Tickets },
                { value: 'calendar', label: this.$t('Calendar'), icon: Calendar },
            ];
        },
        emptyState() {
            if (this.search) {
                return { title: this.$t('No events found'), text: this.$t('Try a different search term.') };
            }

            const comingText = this.spaceSlug
                ? this.$t('Events posted in this space will show up here.')
                : this.$t('Events from across the community will show up here.');

            return {
                all: { title: this.$t('No events yet'), text: comingText },
                upcoming: { title: this.$t('No upcoming events'), text: comingText },
                past: { title: this.$t('No past events'), text: this.$t('Events that have ended will show up here.') },
            }[this.tab];
        },
    },
    watch: {
        // Cards and timeline share one paged list; the calendar loads its own month.
        view(view, previous) {
            Storage.set('portal_events_view', view);

            if (view === 'calendar' || previous === 'calendar') {
                this.reload();
            }
        },
        // The calendar always loads its whole month grid, so only a new month refetches.
        calendarDate(date, previous) {
            if (date.slice(0, 7) !== previous.slice(0, 7)) {
                this.reload();
            }
        },
        calendarMode(mode) {
            Storage.set('portal_events_calendar_mode', mode);
        },
    },
    mounted() {
        this.reload();
    },
    methods: {
        month: eventMonth,
        day: eventDay,
        countLabel: guestCountLabel,
        startDate(event) {
            return dayjs(event.start_at_local).format('ddd, MMM D, YYYY · h:mm A');
        },
        timelineDate(event) {
            return dayjs(event.start_at_local).format('MMM D, YYYY');
        },
        weekday(event) {
            return dayjs(event.start_at_local).format('dddd');
        },
        hasActions: hasEventActions,
        reload() {
            this.fetchEvents(1);
        },
        changeTab() {
            Storage.set('portal_events_tab', this.tab);
            this.reload();
        },
        async fetchEvents(page) {
            const requestId = ++this.requestId;
            const params = {
                scope: this.tab,
                space: this.spaceSlug,
                search: this.search,
                page,
                per_page: 20,
            };

            // The whole visible grid, padded a day each side so events crossing a timezone edge still land.
            if (this.view === 'calendar') {
                const month = dayjs(this.calendarDate);

                params.from = month.startOf('month').startOf('week').subtract(1, 'day').format('YYYY-MM-DD');
                params.to = month.endOf('month').endOf('week').add(1, 'day').format('YYYY-MM-DD');
                params.per_page = 100;
            }

            this.loading = true;
            this.error = '';

            try {
                const response = await eventsApi.get('community/events', params);

                // A newer request (tab, view or month change) owns the list now.
                if (requestId !== this.requestId) {
                    return;
                }

                this.events = page === 1 ? response.events : this.events.concat(response.events);
                if (page === 1) {
                    this.errors = {};
                }
                this.currentPage = response.current_page;
                this.lastPage = response.last_page;
            } catch (error) {
                if (requestId === this.requestId) {
                    this.error = errorMessage(error);
                }
            } finally {
                if (requestId === this.requestId) {
                    this.loading = false;
                }
            }
        },
        createEvent() {
            this.router.push({ name: 'fe_space_create_event', params: { space: this.spaceSlug } });
        },
        // The register response carries the event payload only; keep the post link the list already has.
        applyResponse(event, response) {
            const index = this.events.indexOf(event);

            if (index !== -1) {
                this.events.splice(index, 1, { ...event, ...response.event });
            }
        },
        openPost(event) {
            this.router.push({ name: 'single_feed', params: { feed_slug: event.post_slug } });
        },
    },
};
</script>
