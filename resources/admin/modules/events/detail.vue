<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-event-page">
            <el-skeleton v-if="loading" :rows="6" animated />

            <ErrorState v-else-if="error" :message="error" @retry="fetchEvent" />

            <template v-else>
                <div class="fe-page__header">
                    <div>
                        <nav class="fe-page__breadcrumb" :aria-label="$t('Breadcrumb')">
                            <router-link
                                class="fe-page__breadcrumb-link"
                                :to="{ name: 'events' }"
                            >
                                {{ $t('Events') }}
                            </router-link>

                            <el-icon class="fe-page__breadcrumb-separator" aria-hidden="true">
                                <ArrowRight />
                            </el-icon>
                        </nav>

                        <h1 class="fe-page__title">{{ event.title }}</h1>
                    </div>

                    <div class="fe-page__actions">
                        <el-button type="secondary" @click="goToEdit">
                            {{ $t('Edit Event') }}
                        </el-button>
                    </div>
                </div>

                <Tabs v-model="tab" :tabs="tabs">
                    <template #overview>
                        <div class="fe-card fe-event-overview">
                            <img
                                v-if="coverUrl"
                                class="fe-event-overview__cover"
                                :src="coverUrl"
                                :alt="$t('Cover image of %s', event.title)"
                            />

                            <div class="fe-event-overview__info">
                                <h2 class="fe-event-overview__title">{{ $t('Overview') }}</h2>

                                <div class="fe-event-overview__badges">
                                    <div class="fe-event-overview__badge">
                                        <span class="fe-event-overview__badge-label">{{ $t('Status') }}</span>

                                        <el-tag class="capitalize" :type="statusType" size="small">
                                            {{ $t(event.status) }}
                                        </el-tag>
                                    </div>
                                </div>

                                <div class="fe-event-overview__row">
                                    <div class="fe-event-overview__date">
                                        <span class="fe-event-overview__date-month">{{ month }}</span>
                                        <span class="fe-event-overview__date-day">{{ day }}</span>
                                    </div>

                                    <div>
                                        <p class="fe-event-overview__row-title">{{ fullDate }}</p>
                                        <p class="fe-event-overview__row-text">{{ timeRange }}</p>
                                    </div>
                                </div>

                                <div class="fe-event-overview__row">
                                    <span class="fe-event-overview__icon">
                                        <el-icon aria-hidden="true">
                                            <VideoCamera v-if="isOnline" />
                                            <LocationInformation v-else />
                                        </el-icon>
                                    </span>

                                    <div v-if="isOnline">
                                        <p class="fe-event-overview__row-title">{{ $t('Online event') }}</p>

                                        <a
                                            v-if="meetingUrl"
                                            class="fe-event-overview__row-link"
                                            :href="meetingUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {{ meetingUrl }}
                                        </a>
                                    </div>

                                    <div v-else>
                                        <p class="fe-event-overview__row-title">
                                            {{ event.meta?.venue || $t('No venue yet') }}
                                        </p>

                                        <p v-if="event.meta?.address" class="fe-event-overview__row-text">
                                            {{ event.meta.address }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="event.calendar" class="fe-event-overview__row">
                                    <span class="fe-event-overview__icon">
                                        <span
                                            class="fe-color-swatch fe-event-overview__swatch"
                                            :style="{ background: calendarColor }"
                                        ></span>
                                    </span>

                                    <div>
                                        <p class="fe-event-overview__row-title">{{ event.calendar.title }}</p>

                                        <p v-if="visibility" class="fe-event-overview__row-text capitalize">
                                            {{ $t('Visibility: %s', $t(visibility)) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #description>
                        <div class="fe-card">
                            <div v-if="event.description" class="fe-rich-text" v-html="event.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->

                            <el-empty v-else :description="$t('No description yet.')" />
                        </div>
                    </template>

                    <template #guests>
                        <div class="fe-card">
                            <EventGuests :event-id="event.id" />
                        </div>
                    </template>

                    <template #settings>
                        <EventDangerZone :event="event" @deleted="goToList" />
                    </template>
                </Tabs>
            </template>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { ArrowRight, LocationInformation, VideoCamera } from '@element-plus/icons-vue';
    import { daySpan } from '@/utils/date';
    import Tabs from '@/components/Tabs.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import EventGuests from './parts/EventGuests.vue';
    import EventDangerZone from './parts/EventDangerZone.vue';

    export default {
        name: 'EventDetail',
        components: {
            ArrowRight,
            LocationInformation,
            VideoCamera,
            Tabs,
            ErrorState,
            EventGuests,
            EventDangerZone,
        },
        data() {
            return {
                loading: true,
                error: '',
                tab: 'overview',
                event: {},
            };
        },
        computed: {
            tabs() {
                return [
                    { value: 'overview', label: this.$t('Overview') },
                    { value: 'description', label: this.$t('Description') },
                    { value: 'guests', label: this.$t('Guests') },
                    { value: 'settings', label: this.$t('Settings') },
                ];
            },
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
            visibility() {
                return this.event.calendar?.visibility || '';
            },
            calendarColor() {
                return this.event.calendar?.meta?.theme_color
                    || 'var(--fe-secondary-border, #9ca3af)';
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
                    return this.$formatDate(this.event.start_at_local, 'dddd, MMMM D, YYYY');
                }

                const start = this.$formatDate(this.event.start_at_local, 'MMM D');
                const end = this.$formatDate(this.event.end_at_local, 'MMM D, YYYY');

                return `${start} – ${end}`;
            },
            timeRange() {
                const format = this.isMultiDay ? 'MMM D, h:mm A' : 'h:mm A';
                const start = this.$formatDate(this.event.start_at_local, format);
                const end = this.$formatDate(this.event.end_at_local, format);

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
        },
        methods: {
            fetchEvent() {
                this.loading = true;
                this.$get(`events/${this.$route.params.id}`)
                    .then(response => {
                        this.error = '';
                        this.event = response.event;
                        this.$changeTitle(this.event.title);
                    })
                    .catch(error => {
                        this.error = error?.message || '';
                        this.event = {};
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
            goToEdit() {
                this.$router.push({
                    name: 'event_edit',
                    params: { id: this.$route.params.id },
                });
            },
            goToList() {
                this.$router.push({ name: 'events' });
            },
        },
        mounted() {
            this.fetchEvent();
        },
    };
</script>
