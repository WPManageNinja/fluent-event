<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-event-page">
            <Skeleton v-if="loading" type="event_detail" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchEvent" />

            <!-- A 404 leaves the event empty while the not-found page replaces this route. -->
            <template v-else-if="event.id">
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
                        <el-button
                            @click="goToEdit"
                        >
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

                                        <el-tag class="capitalize" :type="$eventStatusType(event.status)" size="small">
                                            {{ $eventStatusLabel(event.status) }}
                                        </el-tag>
                                    </div>

                                    <div v-if="event.meta?.entry_type" class="fe-event-overview__badge">
                                        <span class="fe-event-overview__badge-label">{{ $t('Registration Type') }}</span>

                                        <span class="fe-badge">
                                            {{ $entryTypeLabel(event.meta.entry_type) }}
                                        </span>
                                    </div>

                                    <div v-if="guestSummary" class="fe-event-overview__badge">
                                        <span class="fe-event-overview__badge-label">{{ $t('Guests') }}</span>

                                        <span class="fe-badge">{{ guestSummary }}</span>
                                    </div>
                                </div>

                                <div class="fe-event-overview__row">
                                    <div class="fe-event-overview__date">
                                        <span class="fe-event-overview__date-month">{{ month }}</span>
                                        <span class="fe-event-overview__date-day">{{ day }}</span>
                                    </div>

                                    <div>
                                        <p class="fe-event-overview__row-title">{{ fullDate }}</p>
                                        <p class="fe-event-overview__row-text">{{ $formatTimeRange(event) }}</p>
                                        <p v-if="showSiteTime" class="fe-event-overview__row-text fe-event-overview__row-text--muted">{{ $formatTimeRange(event, 'site') }}</p>
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
                                        <p class="fe-event-overview__row-title">{{ $t('Online Event') }}</p>

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

                                        <p v-if="event.meta?.address" class="fe-event-overview__row-text fe-event-overview__row-text--address">
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
                                        <router-link
                                            class="fe-event-overview__row-link"
                                            :to="{ name: 'calendar_detail', params: { id: event.calendar.id } }"
                                            :title="$t('Open %s', event.calendar.title)"
                                        >
                                            <span class="fe-event-overview__row-title">
                                                {{ event.calendar.title }}
                                            </span>

                                            <el-icon
                                                class="fe-event-overview__row-link-icon"
                                                aria-hidden="true"
                                            >
                                                <TopRight />
                                            </el-icon>
                                        </router-link>

                                        <p
                                            v-if="calendarStatus"
                                            class="fe-event-overview__row-text"
                                        >
                                            {{ $t('Calendar status: %s', $calendarStatusLabel(calendarStatus)) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="fe-event-overview__row">
                                    <span class="fe-event-overview__icon">
                                        <el-icon aria-hidden="true"><Link /></el-icon>
                                    </span>

                                    <div class="fe-event-overview__public">
                                        <p class="fe-event-overview__row-title">{{ $t('Public Page') }}</p>

                                        <p v-if="publicPageNote" class="fe-event-overview__row-text">
                                            {{ publicPageNote }}
                                        </p>

                                        <div v-else class="fe-event-overview__public-link">
                                            <a
                                                class="fe-event-overview__row-link"
                                                :href="publicUrl"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                {{ publicUrl }}
                                            </a>

                                            <el-button
                                                link
                                                :aria-label="$t('Copy link')"
                                                :title="$t('Copy link')"
                                                @click="copyPublicUrl"
                                            >
                                                <el-icon><DocumentCopy /></el-icon>
                                            </el-button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #description>
                        <div v-if="event.description" class="fe-card">
                            <div class="fe-rich-text" v-html="event.description"></div> <!-- coding-rules-scan:ignore — wp_kses_post runs in Event::getDescriptionAttribute() -->
                        </div>

                        <EmptyState
                            v-else
                            :title="$t('No description yet')"
                            :message="$t('The description tells guests what this event is about.')"
                        >
                            <template #action>
                                <el-button @click="goToEdit">{{ $t('Add Description') }}</el-button>
                            </template>
                        </EmptyState>
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
    import { ArrowRight, DocumentCopy, Link, LocationInformation, TopRight, VideoCamera } from '@element-plus/icons-vue';
    import { daySpan, lastDay } from '@/utils/date';
    import Tabs from '@/components/Tabs.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import EmptyState from '@/components/EmptyState.vue';
    import EventGuests from './parts/EventGuests.vue';
    import EventDangerZone from './parts/EventDangerZone.vue';

    export default {
        name: 'EventDetail',
        components: {
            ArrowRight,
            DocumentCopy,
            Link,
            LocationInformation,
            TopRight,
            VideoCamera,
            Tabs,
            Skeleton,
            ErrorState,
            EmptyState,
            EventGuests,
            EventDangerZone,
        },
        data() {
            return {
                loading: true,
                error: '',
                tab: 'overview',
                event: {},
                publicUrl: '',
                guestCounts: null,
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
            // As on the list cards: going counts everyone coming, checked in or not.
            guestSummary() {
                const counts = this.guestCounts;

                if (!counts) {
                    return '';
                }

                const going = this.$t('%d going', counts.going + counts.checked_in);

                return counts.checked_in ? `${going} · ${this.$t('%d checked in', counts.checked_in)}` : going;
            },
            calendarStatus() {
                return this.event.calendar?.status || '';
            },
            // Why an event has no public page.
            publicPageNote() {
                if (!['public', 'unlisted'].includes(this.event.status)) {
                    return this.$t('Only public and unlisted events have a public page.');
                }

                return this.calendarStatus === 'public'
                    ? ''
                    : this.$t('Its calendar is not public, so this event has no public page.');
            },
            calendarColor() {
                return this.$calendarColor(this.event.calendar);
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
                const end = this.$formatDate(lastDay(this.event.start_at_local, this.event.end_at_local), 'MMM D, YYYY');

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
        },
        methods: {
            fetchEvent() {
                this.$requestServer({
                    path: `events/${this.$route.params.id}`,
                    notFoundPage: true,
                    onSuccess: response => {
                        this.event = response.event;
                        this.publicUrl = response.public_url;
                        this.$changeTitle(this.event.title);
                        this.fetchGuestCounts();
                    },
                    onError: () => {
                        this.event = {};
                    },
                });
            },
            // The guests list already counts every status; one row is enough to get them. A view-only event takes no guests.
            fetchGuestCounts() {
                if (!['rsvp', 'open'].includes(this.event.meta?.entry_type)) {
                    return;
                }

                this.$requestServer({
                    path: `events/${this.$route.params.id}/guests`,
                    payload: { per_page: 1 },
                    loadingState: null,
                    errorState: null,
                    onSuccess: response => {
                        this.guestCounts = response.counts;
                    },
                });
            },
            copyPublicUrl() {
                navigator.clipboard.writeText(this.publicUrl)
                    .then(() => this.$notifySuccess(this.$t('Link copied.')))
                    .catch(() => this.$notifyError(this.$t('The link could not be copied.')));
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
