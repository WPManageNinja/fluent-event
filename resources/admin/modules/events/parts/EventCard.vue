<template>
    <div
        class="fe-event-card"
        role="button"
        tabindex="0"
        :aria-label="$t('View %s', event.title)"
        @click="$emit('open', event)"
        @keydown.enter.prevent="$emit('open', event)"
        @keydown.space.prevent="$emit('open', event)"
    >
        <div class="fe-event-card__main">
            <div class="fe-event-card__badges">
                <span class="fe-event-card__badge">
                    <el-icon aria-hidden="true"><Clock /></el-icon>
                    {{ time }}
                </span>

                <el-tag class="capitalize" :type="statusType" size="small">
                    {{ $t(event.status) }}
                </el-tag>
            </div>

            <h3 class="fe-event-card__title">{{ event.title }}</h3>

            <p v-if="event.author" class="fe-event-card__meta">
                <el-icon aria-hidden="true"><User /></el-icon>
                {{ $t('by %s', event.author.name) }}
            </p>

            <p v-if="showCalendar && event.calendar" class="fe-event-card__meta">
                <span
                    class="fe-color-swatch fe-event-card__swatch"
                    :style="{ background: calendarColor }"
                ></span>
                {{ $t('Calendar: %s', event.calendar.title) }}
            </p>

            <p class="fe-event-card__meta">
                <el-icon aria-hidden="true">
                    <VideoCamera v-if="isOnline" />
                    <LocationInformation v-else />
                </el-icon>
                {{ location }}
            </p>
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
    import { Clock, LocationInformation, User, VideoCamera } from '@element-plus/icons-vue';

    export default {
        name: 'EventCard',
        components: {
            Clock,
            LocationInformation,
            User,
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
        },
        emits: ['open'],
        computed: {
            isOnline() {
                return this.event.venue_type === 'online';
            },
            statusType() {
                return {
                    public: 'success',
                    private: 'primary',
                    draft: 'info',
                    archived: 'danger',
                }[this.event.status] || 'info';
            },
            time() {
                return this.$formatDate(this.event.start_at_local, 'hh:mm A');
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
