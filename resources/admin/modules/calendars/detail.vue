<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendar-detail">
            <el-skeleton v-if="loading" :rows="6" animated />

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
                        <el-button type="secondary" @click="goToEdit">
                            {{ $t('Edit Calendar') }}
                        </el-button>
                    </div>
                </div>

                <Tabs v-model="tab" :tabs="tabs">
                    <template #overview>
                        <div class="fe-card">
                            <div v-if="calendar.description" class="fe-calendar-detail__description" v-html="calendar.description"></div>

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

                                <el-button type="primary" @click="goToEventCreate">
                                    {{ $t('Add Event') }}
                                </el-button>
                            </div>
                        </div>

                        <EventsTimeline :period="period" :calendar-id="calendarId" />
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
    import ErrorState from '@/components/ErrorState.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import EventsTimeline from '@/modules/events/parts/EventsTimeline.vue';
    import CalendarDangerZone from './parts/CalendarDangerZone.vue';

    export default {
        name: 'CalendarDetail',
        components: {
            ArrowRight,
            Tabs,
            ErrorState,
            SegmentedSwitch,
            EventsTimeline,
            CalendarDangerZone,
        },
        data() {
            return {
                loading: true,
                error: '',
                tab: 'overview',
                period: 'upcoming',
                calendar: {},
            };
        },
        computed: {
            tabs() {
                return [
                    { value: 'overview', label: this.$t('Overview') },
                    { value: 'events', label: this.$t('Events') },
                    { value: 'settings', label: this.$t('Settings') },
                ];
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
                this.loading = true;
                this.$get(`calendars/${this.$route.params.id}`)
                    .then(response => {
                        this.error = '';
                        this.calendar = response.calendar;
                        this.$changeTitle(this.calendar.title);
                    })
                    .catch(error => {
                        this.error = error?.message || '';
                        this.calendar = {};
                    })
                    .finally(() => {
                        this.loading = false;
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
