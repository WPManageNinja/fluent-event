<template>
    <Drawer
        :model-value="modelValue"
        class="fe-event-drawer"
        size="560px"
        :title="$t('Event Details')"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div v-if="event" class="fe-event-detail">
            <img
                v-if="coverUrl"
                class="fe-event-detail__cover"
                :src="coverUrl"
                :alt="$t('Cover image of %s', event.title)"
            />

            <div class="fe-event-detail__tags">
                <el-tag class="capitalize" :type="$eventStatusType(event.status)" size="small">
                    {{ $t(event.status) }}
                </el-tag>
            </div>

            <h2 class="fe-event-detail__title">{{ event.title }}</h2>

            <p v-if="event.author" class="fe-event-detail__host">
                {{ $t('Created by %s', event.author.name) }}
            </p>

            <div v-if="event.calendar" class="fe-event-detail__row">
                <span class="fe-event-detail__row-icon">
                    <span
                        class="fe-color-swatch fe-event-detail__swatch"
                        :style="{ background: calendarColor }"
                    ></span>
                </span>

                <div>
                    <router-link
                        class="fe-event-detail__row-link-title"
                        :to="{ name: 'calendar_detail', params: { id: event.calendar.id } }"
                        :title="$t('Open %s', event.calendar.title)"
                        @click="close"
                    >
                        <span class="fe-event-detail__row-title">{{ event.calendar.title }}</span>

                        <el-icon class="fe-event-detail__row-link-icon" aria-hidden="true">
                            <TopRight />
                        </el-icon>
                    </router-link>

                    <p class="fe-event-detail__row-text">{{ $t('Calendar') }}</p>
                </div>
            </div>

            <div class="fe-event-detail__row">
                <div class="fe-event-detail__date-box">
                    <span class="fe-event-detail__date-month">{{ month }}</span>
                    <span class="fe-event-detail__date-day">{{ day }}</span>
                </div>

                <div>
                    <p class="fe-event-detail__row-title">{{ fullDate }}</p>
                    <p class="fe-event-detail__row-text">{{ timeRange }}</p>
                </div>
            </div>

            <div class="fe-event-detail__row">
                <span class="fe-event-detail__row-icon">
                    <el-icon aria-hidden="true">
                        <VideoCamera v-if="isOnline" />
                        <LocationInformation v-else />
                    </el-icon>
                </span>

                <div v-if="isOnline">
                    <p class="fe-event-detail__row-title">{{ $t('Online event') }}</p>
                    <a
                        v-if="meetingUrl"
                        class="fe-event-detail__row-link"
                        :href="meetingUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ meetingUrl }}
                    </a>
                </div>

                <div v-else>
                    <p class="fe-event-detail__row-title">
                        {{ event.meta?.venue || $t('No venue yet') }}
                    </p>
                    <p v-if="event.meta?.address" class="fe-event-detail__row-text">
                        {{ event.meta.address }}
                    </p>
                </div>
            </div>

            <el-button
                class="fe-event-detail__action"
                type="primary"
                @click="goToDetail"
            >
                {{ $t('View Full Details') }}
            </el-button>

            <div v-if="event.description" class="fe-event-detail__section">
                <h3 class="fe-event-detail__section-title">{{ $t('Description') }}</h3>

                <div class="fe-event-detail__description" v-html="event.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->
            </div>
        </div>
    </Drawer>
</template>

<script type="text/javascript">
    import { LocationInformation, TopRight, VideoCamera } from '@element-plus/icons-vue';
    import { daySpan } from '@/utils/date';
    import Drawer from '@/components/Drawer.vue';

    export default {
        name: 'EventDrawer',
        components: {
            Drawer,
            LocationInformation,
            TopRight,
            VideoCamera,
        },
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            event: {
                type: Object,
                default: null,
            },
        },
        emits: ['update:modelValue'],
        computed: {
            isOnline() {
                return this.event?.venue_type === 'online';
            },
            month() {
                return this.$formatDate(this.event.start_at_local, 'MMM');
            },
            day() {
                return this.$formatDate(this.event.start_at_local, 'D');
            },
            isMultiDay() {
                return daySpan(this.event.start_at_local, this.event.end_at_local) > 1;
            },
            fullDate() {
                if (!this.isMultiDay) {
                    return this.$formatDate(this.event.start_at_local);
                }

                const start = this.$formatDate(this.event.start_at_local, 'MMM D');
                const end = this.$formatDate(this.event.end_at_local, 'MMM D, YYYY');

                return `${start} – ${end}`;
            },
            timeRange() {
                const format = date => this.isMultiDay
                    ? this.$formatDateTime(date)
                    : this.$formatTime(date);

                const start = format(this.event.start_at_local);
                const end = format(this.event.end_at_local);

                return `${start} – ${end} (${this.event.timezone})`;
            },
            meetingUrl() {
                const url = this.event.meta?.online_meeting_url || '';
                return /^https?:/i.test(url) ? url : '';
            },
            coverUrl() {
                const url = this.event.meta?.cover_url || '';
                return /^https?:/i.test(url) ? url : '';
            },
            calendarColor() {
                return this.event.calendar?.meta?.theme_color
                    || 'var(--fe-secondary-border, #9ca3af)';
            },
        },
        methods: {
            close() {
                this.$emit('update:modelValue', false);
            },
            goToDetail() {
                this.close();
                this.$router.push({ name: 'event_detail', params: { id: this.event.id } });
            },
        },
    };
</script>
