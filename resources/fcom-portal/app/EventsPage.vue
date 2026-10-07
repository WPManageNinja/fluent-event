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

        <el-tabs v-model="tab" class="fe-events-page__tabs" @tab-change="reload">
            <el-tab-pane :label="$t('All')" name="all"/>
            <el-tab-pane :label="$t('Upcoming')" name="upcoming"/>
            <el-tab-pane :label="$t('Past')" name="past"/>
            <!-- <el-tab-pane :label="$t('Calendar')" name="calendar"/> -->
        </el-tabs>

        <p v-if="error" class="fe-events-page__error" role="alert">{{ error }}</p>

        <!-- <el-calendar v-if="tab === 'calendar'" v-model="calendarMonth" v-loading="loading" class="fe-events-page__calendar">
            <template #date-cell="{ data }">
                <div class="fe-events-page__day">
                    <span class="fe-events-page__day-number">{{ data.day.split('-')[2] }}</span>
                    <a
                        v-for="event in eventsOn(data.day)"
                        :key="event.id"
                        href="#"
                        class="fe-events-page__day-event"
                        @click.prevent.stop="openPost(event)"
                    >{{ event.title }}</a>
                </div>
            </template>
        </el-calendar> -->

        <div v-loading="loading" class="fe-events-page__list">
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
                class="fe-event-item"
                @click="onItemClick($event, event)"
            >
                <span class="fe-event-item__date" aria-hidden="true">
                    <span class="fe-event-item__month">{{ month(event) }}</span>
                    <span class="fe-event-item__day">{{ day(event) }}</span>
                </span>

                <div class="fe-event-item__info">
                    <div class="fe-event-item__head">
                        <a href="#" class="fe-event-item__title" :title="event.title" @click.prevent="openPost(event)">
                            <span class="fe-event-item__title-text">{{ event.title }}</span>
                        </a>
                        <template v-if="event.entry_type === 'open'">
                            <span v-if="isSeated(event)" class="fe-event-status">{{ $t('Registered') }}</span>
                            <span v-else-if="['cancelled', 'not_going'].includes(event.my_status)" class="fe-event-status fe-event-status--danger">{{ $t('Cancelled') }}</span>
                        </template>
                    </div>
                    <span class="fe-event-item__meta">{{ dateRange(event) }}</span>
                    <span class="fe-event-item__meta">
                        {{ event.venue_type === 'online' ? $t('Online event') : (event.venue || event.address) }}
                        <template v-if="!spaceSlug && event.post.space"> · {{ event.post.space.title }}</template>
                    </span>
                    <span v-if="countLabel(event)" class="fe-event-item__meta">{{ countLabel(event) }}</span>
                    <p v-if="errors[event.id]" class="fe-event-item__error" role="alert">{{ errors[event.id] }}</p>
                </div>

                <event-actions
                    v-if="event.is_past || event.entry_type !== 'none'"
                    class="fe-event-item__actions"
                    :event="event"
                    @done="applyResponse(event, $event)"
                    @error="errors[event.id] = $event"
                ></event-actions>

                <event-menu v-if="event.edit_url" class="fe-event-item__menu" :edit-url="event.edit_url"></event-menu>
            </div>

            <div v-if="currentPage < lastPage" class="fe-events-page__more">
                <el-button class="fcom_secondary_button" :loading="loading" @click="fetchEvents(currentPage + 1)">{{ $t('Load more') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script>
import { eventsApi, errorMessage, eventDateRange, eventMonth, eventDay, guestCountLabel, portalVars } from '../utils';
import EventActions from '../EventActions';
import EventMenu from '../EventMenu';

export default {
    name: 'EventsPage',
    components: { EventActions, EventMenu },
    props: {
        spaceSlug: { type: String, default: '' },
        router: { type: Object, required: true },
    },
    data() {
        return {
            tab: 'upcoming',
            // calendarMonth: new Date(),
            events: [],
            errors: {},
            // calendarEvents: [],
            currentPage: 1,
            lastPage: 1,
            loading: false,
            error: '',
        };
    },
    computed: {
        canCreate() {
            return !!this.spaceSlug && !!portalVars().can_create_events;
        },
        emptyState() {
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
    // watch: {
    //     calendarMonth() {
    //         if (this.tab === 'calendar') {
    //             this.fetchCalendar();
    //         }
    //     },
    // },
    mounted() {
        this.reload();
    },
    methods: {
        dateRange: eventDateRange,
        month: eventMonth,
        day: eventDay,
        countLabel: guestCountLabel,
        isSeated(event) {
            return ['going', 'checked_in'].includes(event.my_status);
        },
        reload() {
            // if (this.tab === 'calendar') {
            //     this.fetchCalendar();
            //     return;
            // }

            this.fetchEvents(1);
        },
        async fetchEvents(page) {
            this.loading = true;
            this.error = '';

            try {
                const response = await eventsApi.get('community/events', {
                    scope: this.tab,
                    space: this.spaceSlug,
                    page,
                    per_page: 20,
                });

                this.events = page === 1 ? response.events : this.events.concat(response.events);
                if (page === 1) {
                    this.errors = {};
                }
                this.currentPage = response.current_page;
                this.lastPage = response.last_page;
            } catch (error) {
                this.error = errorMessage(error);
            } finally {
                this.loading = false;
            }
        },
        // async fetchCalendar() {
        //     const first = new Date(this.calendarMonth.getFullYear(), this.calendarMonth.getMonth(), 1);
        //     const last = new Date(this.calendarMonth.getFullYear(), this.calendarMonth.getMonth() + 1, 0);
        //     const format = (date) => window.dayjs(date).format('YYYY-MM-DD');
        //
        //     this.loading = true;
        //     this.error = '';
        //
        //     try {
        //         const response = await eventsApi.get('community/events', {
        //             scope: 'all',
        //             space: this.spaceSlug,
        //             from: format(first),
        //             to: format(last),
        //             per_page: 100,
        //         });
        //
        //         this.calendarEvents = response.events;
        //     } catch (error) {
        //         this.error = errorMessage(error);
        //     } finally {
        //         this.loading = false;
        //     }
        // },
        // eventsOn(day) {
        //     return this.calendarEvents.filter((event) => {
        //         const start = event.start_at_local.slice(0, 10);
        //         const end = event.end_at_local.slice(0, 10);
        //         return day >= start && day <= end;
        //     });
        // },
        createEvent() {
            this.router.push({ name: 'fe_space_create_event', params: { space: this.spaceSlug } });
        },
        // The row opens the post, except for clicks on its own links, buttons and menu.
        onItemClick(clickEvent, event) {
            if (!clickEvent.target.closest('a, button, [role="menu"]')) {
                this.openPost(event);
            }
        },
        // The register response carries the event payload only; keep the post the list already has.
        applyResponse(event, response) {
            const index = this.events.indexOf(event);

            if (index !== -1) {
                this.events.splice(index, 1, { ...event, ...response.event });
            }
        },
        openPost(event) {
            this.router.push({ name: 'single_feed', params: { feed_slug: event.post.slug } });
        },
    },
};
</script>
