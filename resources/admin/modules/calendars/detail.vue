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

                <header class="fe-calendar-detail__head">
                    <span class="fe-calendar-detail__mark" :style="markStyle" aria-hidden="true">
                        {{ initials }}
                    </span>

                    <div class="fe-calendar-detail__identity">
                        <div class="fe-calendar-detail__title-row">
                            <h1 class="fe-page__title">{{ calendar.title }}</h1>
                            <el-tag
                                v-if="calendar.status"
                                class="capitalize"
                                :type="$calendarStatusType(calendar.status)"
                                size="small"
                            >
                                {{ $calendarStatusLabel(calendar.status) }}
                            </el-tag>
                            <el-tooltip
                                v-if="calendar.meta?.fcom_space_id"
                                :content="$t('This calendar belongs to a FluentCommunity space.')"
                                :trigger="['hover', 'focus']"
                                placement="top"
                            >
                                <span
                                    class="fe-community-mark"
                                    role="img"
                                    tabindex="0"
                                    :aria-label="$t('This calendar belongs to a FluentCommunity space.')"
                                >
                                    <img class="fe-community-mark__logo" :src="appVars.fcom_logo" alt="" />
                                </span>
                            </el-tooltip>
                        </div>

                        <p class="fe-calendar-detail__meta">
                            <span class="fe-calendar-detail__slug">{{ calendar.slug }}</span>
                            <span v-if="createdLine">{{ createdLine }}</span>
                        </p>
                    </div>

                    <div class="fe-calendar-detail__actions">
                        <el-button @click="goToEdit">
                            {{ $t('Edit') }}
                        </el-button>
                        <el-button type="primary" @click="goToEventCreate">
                            {{ $t('Add Event') }}
                        </el-button>
                    </div>
                </header>

                <!-- The server drops the tiles a member may not read, so render whatever arrives. -->
                <StatGroups v-if="hasStats" :stats="stats" />

                <Tabs v-if="tabs.length" v-model="tab" :tabs="tabs">
                    <template #events>
                        <EventsView :period="period" :calendar-id="calendarId">
                            <template #toolbar>
                                <SegmentedSwitch
                                    v-model="period"
                                    :options="periods"
                                    :label="$t('Filter events by period')"
                                />
                            </template>
                        </EventsView>
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
    import StatGroups from '@/modules/dashboard/parts/StatGroups.vue';
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
            CalendarDangerZone,
        },
        data() {
            return {
                loading: true,
                error: '',
                tab: 'events',
                period: 'upcoming',
                calendar: {},
                stats: {},
            };
        },
        computed: {
            tabs() {
                return [
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
            hasStats() {
                return Object.keys(this.stats).length > 0;
            },
            createdLine() {
                if (!this.calendar.created_at) {
                    return '';
                }

                const date = this.$formatDate(this.calendar.created_at);

                return this.calendar.creator_name
                    ? this.$t('Created %s by %s', date, this.calendar.creator_name)
                    : this.$t('Created %s', date);
            },
            calendarId() {
                return Number(this.$route.params.id);
            },
            initials() {
                return (this.calendar.title || '').trim().slice(0, 2);
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
                    notFoundPage: true,
                    onSuccess: response => {
                        this.calendar = response.calendar;
                        this.stats = response.stats || {};
                        this.$changeTitle(this.calendar.title);
                    },
                    onError: () => {
                        this.calendar = {};
                        this.stats = {};
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
