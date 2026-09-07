<template>
    <div class="fe-event-card" @click="$emit('open', event)">
        <div class="fe-event-card__main">
            <div class="fe-event-card__badges">
                <span class="fe-event-card__badge">
                    <el-icon aria-hidden="true"><Clock /></el-icon>
                    {{ time }}
                </span>

                <span v-if="dayCount" class="fe-event-card__badge">
                    {{ $t('%d days', dayCount) }}
                </span>

                <el-tag class="capitalize" :type="$eventStatusType(event.status)" size="small">
                    {{ $t(event.status) }}
                </el-tag>

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
                    {{ event.title }}
                </button>
            </h3>

            <p class="fe-event-card__meta">
                <span v-if="event.author">{{ $t('by %s', event.author.name) }}</span>
                <span>{{ location }}</span>
            </p>

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
    </div>
</template>

<script type="text/javascript">
    import { Clock, TopRight } from '@element-plus/icons-vue';
    import { daySpan } from '@/utils/date';

    export default {
        name: 'EventCard',
        components: {
            Clock,
            TopRight,
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
        },
        emits: ['open'],
        computed: {
            isOnline() {
                return this.event.venue_type === 'online';
            },
            time() {
                return this.$formatTime(this.event.start_at_local);
            },
            dayCount() {
                const span = daySpan(this.event.start_at_local, this.event.end_at_local);

                return span > 1 ? span : 0;
            },
            location() {
                if (this.isOnline) {
                    return this.$t('Online event');
                }

                return this.event.meta?.venue
                    || this.event.meta?.address
                    || this.$t('No venue yet');
            },
            calendarColor() {
                return this.event.calendar?.meta?.theme_color
                    || 'var(--fe-secondary-border, #9ca3af)';
            },
            coverUrl() {
                const url = this.event.meta?.cover_url || '';
                return /^https?:/i.test(url) ? url : '';
            },
        },
    };
</script>
