<template>
    <Drawer
        :model-value="modelValue"
        class="fe-event-drawer"
        size="560px"
        :title="$t('Event Details')"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <!-- On a failed load the list's copy may be stale, so the error replaces the whole body. -->
        <ErrorState
            v-if="loadError === 'missing'"
            :title="$t('This event no longer exists')"
            :message="$t('It has been removed from the events list.')"
        >
            <template #action>
                <el-button type="primary" @click="close">{{ $t('Close') }}</el-button>
            </template>
        </ErrorState>
        <ErrorState
            v-else-if="loadError"
            :message="$t('Could not load this event. Please try again.')"
            @retry="loadEvent(event)"
        />
        <div v-else-if="event" class="fe-event-detail">
            <img
                v-if="coverUrl"
                class="fe-event-detail__cover"
                :src="coverUrl"
                :alt="$t('Cover image of %s', event.title)"
            />

            <div class="fe-event-detail__tags">
                <el-tag class="capitalize" :type="$eventStatusType(event.status)" size="small">
                    {{ $eventStatusLabel(event.status) }}
                </el-tag>
            </div>

            <h2 class="fe-event-detail__title">{{ event.title }}</h2>

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
                    <p class="fe-event-detail__row-text">{{ $formatTimeRange(event) }}</p>
                    <p v-if="showSiteTime" class="fe-event-detail__row-text fe-event-detail__row-text--muted">{{ $formatTimeRange(event, 'site') }}</p>
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

            <div v-if="loadingDescription || description" class="fe-event-detail__section">
                <h3 class="fe-event-detail__section-title">{{ $t('Description') }}</h3>

                <Skeleton v-if="loadingDescription" :rows="3" />
                <div v-else class="fe-rich-text fe-event-detail__description" v-html="description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->
            </div>
        </div>
    </Drawer>
</template>

<script type="text/javascript">
    import { LocationInformation, TopRight, VideoCamera } from '@element-plus/icons-vue';
    import { daySpan } from '@/utils/date';
    import Drawer from '@/components/Drawer.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';

    export default {
        name: 'EventDrawer',
        components: {
            Drawer,
            ErrorState,
            Skeleton,
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
        emits: ['update:modelValue', 'missing'],
        data() {
            return {
                description: '',
                loadingDescription: false,
                loadError: '',
            };
        },
        watch: {
            // The event list leaves descriptions out, so the drawer loads the one it shows.
            event: {
                immediate: true,
                handler(event) {
                    this.loadEvent(event);
                },
            },
        },
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
            showSiteTime() {
                return this.event.start_at_site !== this.event.start_at_local || this.event.end_at_site !== this.event.end_at_local;
            },
            meetingUrl() {
                return this.$httpUrl(this.event.meta?.online_meeting_url);
            },
            coverUrl() {
                return this.$httpUrl(this.event.meta?.cover_url);
            },
            calendarColor() {
                return this.$calendarColor(this.event.calendar);
            },
        },
        methods: {
            loadEvent(event) {
                this.description = '';
                this.loadError = '';

                if (!event) return;

                // One loading state, so a slow answer for an event opened earlier is dropped.
                this.$requestServer({
                    path: `events/${event.id}`,
                    loadingState: 'loadingDescription',
                    errorState: null,
                    onSuccess: response => {
                        this.description = response.event.description;
                    },
                    onError: error => {
                        // A deleted event can't come back, so the list drops it too; anything else is worth a retry.
                        this.loadError = error?.status === 404 ? 'missing' : 'failed';

                        if (this.loadError === 'missing') {
                            this.$emit('missing');
                        }
                    },
                });
            },
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
