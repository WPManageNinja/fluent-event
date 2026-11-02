<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendar-detail">
            <Skeleton v-if="loading" type="calendar_detail" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendar" />

            <template v-else>
                <nav class="fe-page__breadcrumb" :aria-label="$t('Breadcrumb')">
                    <router-link class="fe-page__breadcrumb-link" :to="{ name: 'calendars' }">
                        {{ $t('Calendars') }}
                    </router-link>

                    <el-icon class="fe-page__breadcrumb-separator" aria-hidden="true">
                        <ArrowRight />
                    </el-icon>
                </nav>

                <div class="fe-calendar-detail__mast">
                    <div class="fe-calendar-detail__cover" :style="coverStyle">
                        <el-button
                            v-if="$userCan('write_calendars')"
                            class="fe-calendar-detail__edit"
                            @click="goToEdit"
                        >
                            {{ $t('Edit Calendar') }}
                        </el-button>
                    </div>

                    <span
                        class="fe-calendar-detail__mark"
                        :style="markStyle"
                        aria-hidden="true"
                    >
                        {{ initials }}
                    </span>

                    <div class="fe-calendar-detail__identity">
                        <div class="fe-calendar-detail__title-row">
                            <h1 class="fe-page__title">{{ calendar.title }}</h1>

                            <el-tag
                                v-if="calendar.status && calendar.status !== 'public'"
                                class="capitalize"
                                :type="$calendarStatusType(calendar.status)"
                                size="small"
                            >
                                {{ $calendarStatusLabel(calendar.status) }}
                            </el-tag>
                        </div>

                        <p class="fe-calendar-detail__slug">{{ calendar.slug }}</p>
                    </div>
                </div>

                <Tabs v-model="tab" :tabs="tabs">
                    <template #overview>
                        <CalendarTabLayout :calendar="calendar" :upcoming-events="upcomingEvents">
                            <StatGroups v-if="hasStats" :stats="stats" />

                            <div class="fe-card">
                                <template v-if="calendar.description">
                                    <p class="fe-calendar-detail__about">{{ $t('About this calendar') }}</p>

                                    <div class="fe-rich-text" v-html="calendar.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in CalendarController::getCalendar() -->
                                </template>

                                <el-empty v-else :description="$t('No description yet')" />
                            </div>
                        </CalendarTabLayout>
                    </template>

                    <template #events>
                        <CalendarTabLayout :calendar="calendar" :upcoming-events="upcomingEvents">
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

                            <EventsView :period="period" :calendar-id="calendarId" />
                        </CalendarTabLayout>
                    </template>

                    <template #settings>
                        <CalendarTabLayout :calendar="calendar" :upcoming-events="upcomingEvents">
                            <CalendarDangerZone :calendar="calendar" @deleted="goToList" />
                        </CalendarTabLayout>
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
    import StatGroups from '@/modules/dashboard/parts/StatGroups.vue';
    import CalendarTabLayout from './parts/CalendarTabLayout.vue';
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
            StatGroups,
            CalendarTabLayout,
            CalendarDangerZone,
        },
        data() {
            return {
                loading: true,
                error: '',
                tab: 'overview',
                period: 'upcoming',
                calendar: {},
                stats: {},
                upcomingEvents: [],
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
            hasStats() {
                return Object.keys(this.stats).length > 0;
            },
            calendarId() {
                return Number(this.$route.params.id);
            },
            initials() {
                return (this.calendar.title || '').trim().slice(0, 2);
            },
            coverStyle() {
                const color = this.calendar.meta?.theme_color;
                const cover = this.calendar.meta?.cover_url;

                if (cover) {
                    return { backgroundImage: `url(${cover})` };
                }

                return color ? { color } : {};
            },
            markStyle() {
                const color = this.calendar.meta?.theme_color;

                if (!color) return {};

                const channel = (value) => {
                    const c = parseInt(value, 16) / 255;

                    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
                };

                const luminance = 0.2126 * channel(color.slice(1, 3))
                    + 0.7152 * channel(color.slice(3, 5))
                    + 0.0722 * channel(color.slice(5, 7));

                return {
                    backgroundColor: color,
                    color: luminance > 0.45
                        ? 'var(--fe-on-hue-dark, #19283a)'
                        : 'var(--fe-on-hue-light, #ffffff)',
                };
            },
        },
        methods: {
            fetchCalendar() {
                this.$requestServer({
                    path: `calendars/${this.$route.params.id}`,
                    onSuccess: response => {
                        this.calendar = response.calendar;
                        this.stats = response.stats || {};
                        this.upcomingEvents = response.upcoming_events || [];
                        this.$changeTitle(this.calendar.title);
                    },
                    onError: () => {
                        this.calendar = {};
                        this.stats = {};
                        this.upcomingEvents = [];
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
