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

            <SegmentedSwitch
                v-model="view"
                class="fe-switch--compact"
                :options="views"
                :label="$t('Switch between timeline and calendar view')"
            />

            <ActionPopover :width="320" placement="bottom-end" @show="syncFilters">
                <template #trigger>
                    <el-button :aria-label="$t('Filter')" :title="$t('Filter')">
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

                            <el-form-item v-if="showCalendarFilter" :label="$t('Calendar')">
                                <el-select v-model="pendingFilters.calendar_id">
                                    <el-option :label="$t('All')" :value="0" />
                                    <el-option
                                        v-for="calendar in calendars"
                                        :key="calendar.id"
                                        :label="calendar.title"
                                        :value="calendar.id"
                                    />
                                </el-select>
                            </el-form-item>

                            <el-form-item v-if="!isGrid" :label="$t('Date range')">
                                <el-date-picker
                                    v-model="pendingFilters.dates"
                                    type="daterange"
                                    format="MMM D, YYYY"
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

        <Skeleton v-if="loading && !fetched" :type="isGrid ? 'event-grid' : 'timeline'" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchEvents" />

        <div v-else :class="{ 'fe-is-loading': loading }">
            <EventsGrid
                v-if="isGrid"
                v-model:view-mode="calendarViewMode"
                :events="events"
                :month="month"
                @change-month="changeMonth"
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

        <EventDrawer v-model="drawerOpen" :event="activeEvent" />
    </div>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { Calendar, Filter, Tickets } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import SearchInput from '@/components/SearchInput.vue';
    import ActionPopover from '@/components/ActionPopover.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import EventsGrid from './EventsGrid.vue';
    import EventsTimeline from './EventsTimeline.vue';
    import EventDrawer from './EventDrawer.vue';

    export default {
        name: 'EventsView',
        components: {
            Filter,
            ErrorState,
            SearchInput,
            ActionPopover,
            SegmentedSwitch,
            Skeleton,
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
        emits: ['update:period'],
        data() {
            return {
                loading: false,
                fetched: false,
                error: '',
                view: 'timeline',
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
            isGrid() {
                return this.view === 'grid';
            },
            showCalendarFilter() {
                return this.showCalendar && this.$userCan(
                    ['read_calendars', 'write_calendars'], { match_type: 'any' }
                );
            },
            views() {
                return [
                    { value: 'timeline', label: this.$t('Timeline'), icon: Tickets },
                    { value: 'grid', label: this.$t('Calendar'), icon: Calendar },
                ];
            },
            statuses() {
                return [
                    { value: '', label: this.$t('All') },
                    { value: 'public', label: this.$t('Public') },
                    { value: 'private', label: this.$t('Private') },
                    { value: 'draft', label: this.$t('Draft') },
                    { value: 'archived', label: this.$t('Archived') },
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

                if (!this.isGrid && this.query.dates.length === 2) {
                    filters.push({
                        key: 'dates',
                        label: this.$t('Date'),
                        value: this.query.dates
                            .map(date => this.$formatDate(date, 'MMM D, YYYY'))
                            .join(' – '),
                    });
                }

                return filters;
            },
        },
        watch: {
            view(value) {
                if (value === 'grid' && this.period) {
                    this.$emit('update:period', '');
                    return;
                }
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
            requestParams() {
                const params = {
                    search: this.query.search,
                    status: this.query.status,
                    calendar_id: this.scopedCalendarId || '',
                };

                if (this.isGrid) {
                    const month = dayjs(this.month);

                    return {
                        ...params,
                        paginate: false,
                        period: this.period,
                        start_date: month.startOf('month').startOf('week')
                            .subtract(1, 'day').format('YYYY-MM-DD'),
                        end_date: month.endOf('month').endOf('week')
                            .add(1, 'day').format('YYYY-MM-DD'),
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

                        if (this.isGrid) {
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
                    path: 'calendars',
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

            if (this.showCalendarFilter) {
                this.fetchCalendars();
            }
        },
    };
</script>
