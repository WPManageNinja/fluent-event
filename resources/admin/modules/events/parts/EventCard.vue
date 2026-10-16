<template>
    <div class="fe-event-card" @click="$emit('open', event)">
        <div class="fe-event-card__main">
            <div v-if="showDate || dayCount || (showCalendar && event.calendar)" class="fe-event-card__badges">
                <span v-if="showDate" class="fe-event-card__badge">
                    <el-icon aria-hidden="true"><Calendar /></el-icon>
                    {{ date }}
                </span>

                <span v-if="dayCount" class="fe-event-card__badge">
                    {{ $t('%d days', dayCount) }}
                </span>

                <span
                    v-if="showCalendar && event.calendar"
                    class="fe-event-card__badge fe-event-card__badge--calendar"
                    :style="{ '--fe-calendar-hue': calendarColor }"
                >
                    <span class="fe-color-swatch fe-event-card__swatch"></span>
                    {{ event.calendar.title }}
                </span>
            </div>

            <h3 class="fe-event-card__title">
                <button
                    type="button"
                    class="fe-event-card__open"
                    @click.stop="$emit('open', event)"
                >
                    <span class="fe-event-card__title-text">{{ event.title }}</span>
                </button>
            </h3>

            <p class="fe-event-card__time">{{ timeRange }}</p>

            <div class="fe-event-card__meta">
                <el-tag class="capitalize" :type="$eventStatusType(event.status)" size="small">
                    {{ $eventStatusLabel(event.status) }}
                </el-tag>

                <span class="fe-event-card__venue">
                    <el-icon aria-hidden="true">
                        <VideoCamera v-if="isOnline" />
                        <MapLocation v-else />
                    </el-icon>
                    {{ isOnline ? $t('Online') : $t('In person') }}
                </span>
            </div>

            <router-link
                class="fe-event-card__manage"
                :to="{ name: 'event_detail', params: { id: event.id } }"
                @click.stop
            >
                {{ $t('Manage Event') }}
                <el-icon aria-hidden="true"><TopRight /></el-icon>
            </router-link>
        </div>

        <img
            v-if="coverUrl"
            class="fe-event-card__cover"
            :src="coverUrl"
            :alt="$t('Cover image of %s', event.title)"
        />

        <div v-else class="fe-event-card__cover fe-event-card__cover--empty" aria-hidden="true">
            <el-icon><Calendar /></el-icon>
        </div>
    </div>
</template>

<script type="text/javascript">
    import { Calendar, MapLocation, TopRight, VideoCamera } from '@element-plus/icons-vue';
    import { daySpan } from '@/utils/date';

    export default {
        name: 'EventCard',
        components: {
            Calendar,
            MapLocation,
            TopRight,
            VideoCamera,
        },
        props: {
            event: {
                type: Object,
                required: true,
            },
            showCalendar: {
                type: Boolean,
                default: false,
            },
            showDate: {
                type: Boolean,
                default: false,
            },
        },
        emits: ['open'],
        computed: {
            isOnline() {
                return this.event.venue_type === 'online';
            },
            date() {
                return this.$formatDate(this.event.start_at_site);
            },
            timeRange() {
                const start = this.$formatTime(this.event.start_at_site);
                const end = this.event.end_at_site
                    ? this.$formatTime(this.event.end_at_site)
                    : '';

                return end && end !== start ? `${start} – ${end}` : start;
            },
            dayCount() {
                const span = daySpan(this.event.start_at_site, this.event.end_at_site);

                return span > 1 ? span : 0;
            },
            calendarColor() {
                return this.$calendarColor(this.event.calendar);
            },
            coverUrl() {
                return this.$httpUrl(this.event.meta?.cover_url);
            },
        },
    };
</script>
