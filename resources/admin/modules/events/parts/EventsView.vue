<template>
    <div class="fe-events-view">
        <div class="fe-events-view__toolbar">
            <SearchInput
                v-model="query.search"
                class="fe-events-view__search"
                :placeholder="$t('Search events')"
                type="search_on_enter"
                @search="refetch"
            />

            <slot name="toolbar" />

            <SegmentedSwitch
                v-if="!viewLocked"
                v-model="view"
                class="fe-switch--compact"
                :options="views"
                :label="$t('Switch between card, timeline and calendar view')"
                @change="rememberView"
            />

            <ActionPopover :width="320" placement="bottom-end" @show="syncFilters">
                <template #trigger="{ visible }">
                    <el-button :aria-label="$t('Filter')" :title="$t('Filter')" :aria-expanded="visible">
                        <el-icon><Filter /></el-icon>
                    </el-button>
                </template>

                <template #default="{ hide }">
                    <div class="fe-events-view__filters">
                        <el-form label-position="top">
                            <el-form-item :label="$t('Status')">
                                <el-radio-group
                                    v-model="pendingFilters.status"
                                    class="fe-events-view__filter"
                                >
                                    <el-radio
                                        v-for="option in statuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </el-radio>
                                </el-radio-group>
                            </el-form-item>

                            <el-form-item v-if="showCalendar" :label="$t('Calendar')">
                                <el-select v-model="pendingFilters.calendar_id" filterable>
                                    <el-option :label="$t('All')" :value="0" />
                                    <el-option
                                        v-for="calendar in calendars"
                                        :key="calendar.id"
                                        :label="calendar.title"
                                        :value="calendar.id"
                                    />
                                </el-select>
                            </el-form-item>

                            <el-form-item v-if="!isCalendar" :label="$t('Date Range')">
                                <el-date-picker
                                    v-model="pendingFilters.dates"
                                    type="daterange"
                                    :format="dateFormat"
                                    value-format="YYYY-MM-DD"
                                    :start-placeholder="$t('From')"
                                    :end-placeholder="$t('To')"
                                />
                            </el-form-item>
                        </el-form>

                        <el-button type="primary" size="small" @click="applyFilters(hide)">
                            {{ $t('Apply') }}
                        </el-button>
                    </div>
                </template>
            </ActionPopover>
        </div>

        <div v-if="activeFilters.length" class="fe-events-view__chips">
            <el-tag
                v-for="filter in activeFilters"
                :key="filter.key"
                type="info"
                size="small"
                closable
                @close="removeFilter(filter.key)"
            >
                {{ $t('%s: %s', filter.label, filter.value) }}
            </el-tag>
        </div>

        <Skeleton v-if="loading && !fetched" :type="skeletonType" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchEvents" />

        <EmptyState
            v-else-if="!isCalendar && !events.length"
            :title="emptyState.title"
            :message="emptyState.message"
        />

        <div v-else :class="{ 'fe-is-loading': loading }" :aria-busy="loading">
            <EventsGrid
                v-if="isCalendar"
                v-model:view-mode="calendarViewMode"
                :events="events"
                :month="month"
                @change-month="changeMonth"
                @open="openEvent"
            />

            <EventsCards
                v-else-if="isCards"
                :events="events"
                :pagination="pagination"
                :show-calendar="showCalendar"
                @fetch="fetchEvents"
                @open="openEvent"
            />

            <EventsTimeline
                v-else
                :events="events"
                :pagination="pagination"
                :show-calendar="showCalendar"
                @fetch="fetchEvents"
                @open="openEvent"
            />
        </div>

        <EventDrawer v-model="drawerOpen" :event="activeEvent" @missing="refetch" />
    </div>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { Calendar, Filter, Grid, Tickets } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import EmptyState from '@/components/EmptyState.vue';
    import SearchInput from '@/components/SearchInput.vue';
    import ActionPopover from '@/components/ActionPopover.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import Storage from '@/utils/Storage';
    import EventsCards from './EventsCards.vue';
    import EventsGrid from './EventsGrid.vue';
    import EventsTimeline from './EventsTimeline.vue';
    import EventDrawer from './EventDrawer.vue';

    export default {
        name: 'EventsView',
        components: {
            Filter,
            ErrorState,
            EmptyState,
            SearchInput,
            ActionPopover,
            SegmentedSwitch,
            Skeleton,
            EventsCards,
            EventsGrid,
            EventsTimeline,
            EventDrawer,
        },
        props: {
            period: {
                type: String,
                default: 'upcoming',
            },
            calendarId: {
                type: Number,
                default: 0,
            },
            showCalendar: {
                type: Boolean,
                default: false,
            },
        },
        data() {
            const settings = window.fluentEventAdmin?.general_settings;
            const viewLocked = settings?.disable_event_view_switch === 'yes';
            const savedView = viewLocked ? null : Storage.get('events_view');
            const defaultView = settings?.default_events_view || 'timeline';

            return {
                loading: false,
                fetched: false,
                error: '',
                viewLocked,
                view: ['cards', 'timeline', 'calendar'].includes(savedView) ? savedView : defaultView,
                month: dayjs().format('YYYY-MM-DD'),
                calendarViewMode: 'month',
                events: [],
                calendars: [],
                activeEvent: null,
                drawerOpen: false,
                query: {
                    search: '',
                    status: '',
                    calendar_id: 0,
                    dates: [],
                },
                pendingFilters: {
                    status: '',
                    calendar_id: 0,
                    dates: [],
                },
                pagination: {
                    total: 0,
                    per_page: 10,
                    current_page: 1,
                },
            };
        },
        computed: {
            isCalendar() {
                return this.view === 'calendar';
            },
            isCards() {
                return this.view === 'cards';
            },
            skeletonType() {
                return this.isCalendar ? 'event-calendar' : this.isCards ? 'event-cards' : 'timeline';
            },
            dateFormat() {
                return window.fluentEventAdmin?.date_format || 'MMM D, YYYY';
            },
            views() {
                return [
                    { value: 'cards', label: this.$t('Cards'), icon: Grid },
                    { value: 'timeline', label: this.$t('Timeline'), icon: Tickets },
                    { value: 'calendar', label: this.$t('Calendar'), icon: Calendar },
                ];
            },
            statuses() {
                return [
                    { value: '', label: this.$t('All') },
                    ...this.$eventStatuses(),
                ];
            },
            scopedCalendarId() {
                return this.calendarId || this.query.calendar_id;
            },
            activeFilters() {
                const filters = [];

                if (this.query.status) {
                    filters.push({
                        key: 'status',
                        label: this.$t('Status'),
                        value: this.statuses.find(
                            option => option.value === this.query.status
                        ).label,
                    });
                }

                if (this.query.calendar_id) {
                    const calendar = this.calendars.find(
                        item => item.id === this.query.calendar_id
                    );

                    filters.push({
                        key: 'calendar_id',
                        label: this.$t('Calendar'),
                        value: calendar ? calendar.title : this.query.calendar_id,
                    });
                }

                if (!this.isCalendar && this.query.dates.length === 2) {
                    filters.push({
                        key: 'dates',
                        label: this.$t('Date'),
                        value: this.query.dates
                            .map(date => this.$formatDate(date))
                            .join(' – '),
                    });
                }

                return filters;
            },
            emptyState() {
                if (this.query.search || this.activeFilters.length) {
                    return {
                        title: this.$t('No events found'),
                        message: this.$t('No events match your search or filters.'),
                    };
                }

                if (this.period === 'past') {
                    return {
                        title: this.$t('No past events'),
                        message: this.$t('Events show up here once they end.'),
                    };
                }

                return {
                    title: this.period === 'upcoming' ? this.$t('No upcoming events') : this.$t('No events yet'),
                    message: this.$t('New events will show up here.'),
                };
            },
        },
        watch: {
            view() {
                this.refetch();
            },
            period() {
                this.refetch();
            },
            calendarId() {
                this.refetch();
            },
        },
        methods: {
            rememberView(view) {
                Storage.set('events_view', view);
            },
            requestParams() {
                const params = {
                    search: this.query.search,
                    status: this.query.status,
                    calendar_id: this.scopedCalendarId || '',
                };

                if (this.isCalendar) {
                    const month = dayjs(this.month);

                    return {
                        ...params,
                        paginate: false,
                        period: this.period,
                        start_date: month.startOf('month').startOf('week').format('YYYY-MM-DD'),
                        end_date: month.endOf('month').endOf('week').format('YYYY-MM-DD'),
                    };
                }

                return {
                    ...params,
                    page: this.pagination.current_page,
                    per_page: this.pagination.per_page,
                    period: this.period,
                    start_date: this.query.dates[0] || '',
                    end_date: this.query.dates[1] || '',
                };
            },
            fetchEvents() {
                this.$requestServer({
                    path: 'events',
                    payload: this.requestParams(),
                    onSuccess: response => {
                        this.fetched = true;

                        if (this.isCalendar) {
                            this.events = response.events;
                            return;
                        }

                        this.events = response.events.data;
                        this.pagination.total = response.events.total;
                        this.pagination.per_page = +response.events.per_page;
                    },
                    onError: () => {
                        this.events = [];
                    },
                });
            },
            fetchCalendars() {
                this.$requestServer({
                    path: 'calendars/options',
                    loadingState: null,
                    errorState: null,
                    errorNotify: true,
                    onSuccess: response => {
                        this.calendars = response.calendars;
                    },
                });
            },
            refetch() {
                this.pagination.current_page = 1;
                this.fetchEvents();
            },
            changeMonth(month) {
                this.month = month;
                this.fetchEvents();
            },
            syncFilters() {
                if (this.showCalendar && !this.calendars.length) {
                    this.fetchCalendars();
                }

                this.pendingFilters = {
                    status: this.query.status,
                    calendar_id: this.query.calendar_id,
                    dates: [...this.query.dates],
                };
            },
            applyFilters(hide) {
                this.query = {
                    search: this.query.search,
                    status: this.pendingFilters.status,
                    calendar_id: this.pendingFilters.calendar_id,
                    dates: [...(this.pendingFilters.dates || [])],
                };
                hide();
                this.refetch();
            },
            removeFilter(key) {
                const empty = { status: '', calendar_id: 0, dates: [] };
                this.query[key] = empty[key];
                this.refetch();
            },
            openEvent(event) {
                this.activeEvent = event;
                this.drawerOpen = true;
            },
        },
        mounted() {
            this.fetchEvents();
        },
    };
</script>
