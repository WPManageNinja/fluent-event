<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendar-detail">
            <Skeleton v-if="loading" type="calendar_detail" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendar" />

            <template v-else>
                <div class="fe-page__header">
                    <div>
                        <nav class="fe-page__breadcrumb" :aria-label="$t('Breadcrumb')">
                            <router-link
                                class="fe-page__breadcrumb-link"
                                :to="{ name: 'calendars' }"
                            >
                                {{ $t('Calendars') }}
                            </router-link>

                            <el-icon class="fe-page__breadcrumb-separator" aria-hidden="true">
                                <ArrowRight />
                            </el-icon>
                        </nav>

                        <h1 class="fe-page__title">{{ calendar.title }}</h1>
                    </div>

                    <div class="fe-page__actions">
                        <el-button
                            v-if="$userCan('write_calendars')"
                            type="secondary"
                            @click="goToEdit"
                        >
                            {{ $t('Edit Calendar') }}
                        </el-button>
                    </div>
                </div>

                <Tabs v-model="tab" :tabs="tabs">
                    <template #overview>
                        <div class="fe-card">
                            <div v-if="calendar.description" class="fe-rich-text" v-html="calendar.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in CalendarController::getCalendar() -->

                            <el-empty v-else :description="$t('No description yet.')" />
                        </div>
                    </template>

                    <template #events>
                        <div class="fe-calendar-detail__events-bar">
                            <h2 class="fe-calendar-detail__events-title">{{ $t('Events') }}</h2>

                            <div class="fe-calendar-detail__events-actions">
                                <SegmentedSwitch
                                    v-model="period"
                                    :options="periods"
                                    :label="$t('Filter events by period')"
                                />

                                <el-button
                                    v-if="$userCan('write_events')"
                                    type="primary"
                                    @click="goToEventCreate"
                                >
                                    {{ $t('Add Event') }}
                                </el-button>
                            </div>
                        </div>

                        <EventsView v-model:period="period" :calendar-id="calendarId" />
                    </template>

                    <template #settings>
                        <CalendarDangerZone :calendar="calendar" @deleted="goToList" />
                    </template>
                </Tabs>
            </template>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { ArrowRight } from '@element-plus/icons-vue';
    import Tabs from '@/components/Tabs.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import EventsView from '@/modules/events/parts/EventsView.vue';
    import CalendarDangerZone from './parts/CalendarDangerZone.vue';

    export default {
        name: 'CalendarDetail',
        components: {
            ArrowRight,
            Tabs,
            Skeleton,
            ErrorState,
            SegmentedSwitch,
            EventsView,
            CalendarDangerZone,
        },
        data() {
            return {
                loading: false,
                error: '',
                tab: 'overview',
                period: 'upcoming',
                calendar: {},
            };
        },
        computed: {
            tabs() {
                const tabs = [
                    { value: 'overview', label: this.$t('Overview') },
                ];

                if (this.$userCan(['read_events', 'write_events'], { match_type: 'any' })) {
                    tabs.push({ value: 'events', label: this.$t('Events') });
                }

                if (this.$userCan('write_calendars')) {
                    tabs.push({ value: 'settings', label: this.$t('Settings') });
                }

                return tabs;
            },
            periods() {
                return [
                    { value: '', label: this.$t('All') },
                    { value: 'upcoming', label: this.$t('Upcoming') },
                    { value: 'past', label: this.$t('Past') },
                ];
            },
            calendarId() {
                return Number(this.$route.params.id);
            },
        },
        methods: {
            fetchCalendar() {
                this.$requestServer({
                    path: `calendars/${this.$route.params.id}`,
                    onSuccess: response => {
                        this.calendar = response.calendar;
                        this.$changeTitle(this.calendar.title);
                    },
                    onError: () => {
                        this.calendar = {};
                    },
                });
            },
            goToEdit() {
                this.$router.push({
                    name: 'calendar_edit',
                    params: { id: this.$route.params.id },
                });
            },
            goToEventCreate() {
                this.$router.push({
                    name: 'event_create',
                    query: { calendar_id: this.calendarId },
                });
            },
            goToList() {
                this.$router.push({ name: 'calendars' });
            },
        },
        mounted() {
            this.fetchCalendar();
        },
    };
</script>
