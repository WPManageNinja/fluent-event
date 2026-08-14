<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-dashboard">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Dashboard') }}</h1>

                <div class="fe-page__actions">
                    <el-button
                        :loading="loading"
                        :disabled="loading"
                        @click="fetchStats"
                    >
                        {{ $t('Refresh') }}
                    </el-button>
                </div>
            </div>

            <Skeleton v-if="loading" type="dashboard" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchStats" />

            <el-row v-else :gutter="20">
                <!-- Left side stats and charts. -->
                <el-col :md="16" :sm="24">
                    <StatGroups :stats="stats" />
                    <StatGroups :title="$t('Calendars')" :stats="calendarStats" />
                    <StatGroups :title="$t('Events')" :stats="eventStats" :md="6" />
                    <StatGroups :title="$t('Guests')" :stats="guestStats" />

                    <GuestGrowth
                        :section-title="$t('Guest Trends')"
                        end-point="reports/guest-trends"
                    />
                </el-col>

                <el-col :md="8" :sm="24">
                    <section class="fe-dashboard__card">
                        <h2 class="fe-dashboard__card-title">{{ $t('Ongoing Events') }}</h2>

                        <template v-if="ongoingEvents.length">
                            <EventListItem
                                v-for="event in ongoingEvents"
                                :key="`ongoing-${event.id}`"
                                :event="event"
                                :meta="$t('Ends %s', formatEventDate(event.end_at_local))"
                            />
                        </template>
                        <p v-else class="fe-dashboard__empty">
                            {{ $t('No events are running right now.') }}
                        </p>
                    </section>

                    <section class="fe-dashboard__card">
                        <h2 class="fe-dashboard__card-title">{{ $t('Upcoming Events') }}</h2>

                        <template v-if="upcomingEvents.length">
                            <EventListItem
                                v-for="event in upcomingEvents"
                                :key="`upcoming-${event.id}`"
                                :event="event"
                                :meta="$t('Starts %s', formatEventDate(event.start_at_local))"
                            />
                        </template>
                        <p v-else class="fe-dashboard__empty">
                            {{ $t('Upcoming events will be shown here.') }}
                        </p>
                    </section>

                    <section class="fe-dashboard__card">
                        <h2 class="fe-dashboard__card-title">{{ $t('Top Events') }}</h2>

                        <template v-if="topEvents.length">
                            <EventListItem
                                v-for="event in topEvents"
                                :key="`top-${event.id}`"
                                :event="event"
                                :meta="eventCalendarMeta(event)"
                                :count="$t('%d guests', event.guests_count)"
                            />
                        </template>
                        <p v-else class="fe-dashboard__empty">
                            {{ $t('No guests joined yet.') }}
                        </p>
                    </section>
                </el-col>
            </el-row>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { Refresh } from '@element-plus/icons-vue';
    import Skeleton from '@/components/Skeleton.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import GuestGrowth from './parts/GuestGrowth.vue';
    import StatGroups from './parts/StatGroups.vue';
    import EventListItem from './parts/EventListItem.vue';

    export default {
        name: 'Dashboard',
        components: {
            Refresh,
            Skeleton,
            ErrorState,
            GuestGrowth,
            StatGroups,
            EventListItem,
        },
        data() {
            return {
                loading: false,
                error: '',
                stats: {},
                guestStats: {},
                calendarStats: {},
                eventStats: {},
                ongoingEvents: [],
                upcomingEvents: [],
                topEvents: [],
            };
        },
        methods: {
            fetchStats() {
                if (this.loading) return;
                this.loading = true;

                this.$get('reports')
                    .then(response => {
                        this.error = '';
                        this.stats = response.stats;
                        this.guestStats = response.guest_stats;
                        this.calendarStats = response.calendar_stats;
                        this.eventStats = response.event_stats;
                        this.ongoingEvents = response.ongoing_events;
                        this.upcomingEvents = response.upcoming_events;
                        this.topEvents = response.top_events;
                    })
                    .catch(error => {
                        this.error = error?.message || this.$t('Something went wrong');
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
            formatEventDate(date) {
                return this.$formatDate(date, 'MMM D, hh:mm A');
            },
            eventCalendarMeta(event) {
                return event.calendar?.title
                    ? this.$t('Calendar: %s', event.calendar.title)
                    : this.formatEventDate(event.start_at_local);
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Dashboard'));
            this.fetchStats();
        },
    };
</script>
