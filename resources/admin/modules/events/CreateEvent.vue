<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-event-create">
            <div class="fe-page__header">
                <div>
                    <h1 class="fe-page__title">{{ $t('Create Event') }}</h1>
                    <p class="fe-page__subtitle">
                        {{ $t('Events live inside a calendar. Pick one, say when it happens, and publish.') }}
                    </p>
                </div>

                <div class="fe-page__actions">
                    <el-button type="secondary" @click="$goBack">
                        {{ $t('Go Back') }}
                    </el-button>
                </div>
            </div>

            <el-skeleton v-if="loading" animated>
                <template #template>
                    <div class="fe-event-form__grid">
                        <el-skeleton-item
                            variant="image"
                            class="fe-event-form__skeleton-cover"
                        />
                        <div class="fe-event-form__main">
                            <el-skeleton-item
                                variant="h1"
                                class="fe-event-form__skeleton-title"
                            />
                            <el-skeleton-item
                                v-for="row in 4"
                                :key="row"
                                variant="rect"
                                class="fe-event-form__skeleton-card"
                            />
                        </div>
                    </div>
                </template>
            </el-skeleton>

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendars" />

            <div v-else-if="!calendars.length" class="fe-card">
                <el-empty :description="$t('An event needs a calendar to live in, and there are none yet.')">
                    <el-button type="primary" @click="goToCalendarCreate">
                        {{ $t('Create a Calendar') }}
                    </el-button>
                </el-empty>
            </div>

            <form v-else class="fe-event-form" @submit.prevent="saveEvent">
                <div class="fe-event-form__grid">
                    <aside class="fe-event-form__aside">
                        <MediaPicker
                            v-model="event.cover_url"
                            class="fe-cover-picker"
                            :frame-title="$t('Select a cover image')"
                            :action-label="$t('Use this image')"
                            :alt="$t('Event cover image')"
                            :add-label="$t('Add a cover image')"
                            :change-label="$t('Change cover image')"
                            :remove-label="$t('Remove cover image')"
                        >
                            <template #placeholder>
                                <div class="fe-cover-picker__placeholder">
                                    <el-icon
                                        class="fe-cover-picker__placeholder-icon"
                                        aria-hidden="true"
                                    >
                                        <Picture />
                                    </el-icon>
                                    <span class="fe-cover-picker__placeholder-text">
                                        {{ $t('Click to add a cover image') }}
                                    </span>
                                </div>
                            </template>
                        </MediaPicker>
                    </aside>

                    <div class="fe-event-form__main">
                        <label class="fe-event-form__title-label" for="fe-event-title">
                            {{ $t('Event name') }}
                        </label>
                        <input
                            id="fe-event-title"
                            ref="titleInput"
                            v-model="event.title"
                            class="fe-event-form__title"
                            type="text"
                            :placeholder="$t('Event Name')"
                        />

                        <div class="fe-event-form__card fe-event-form__schedule">
                            <div class="fe-event-form__window">
                                <div
                                    v-for="bound in bounds"
                                    :key="bound.key"
                                    class="fe-event-form__window-row"
                                >
                                    <span class="fe-event-form__window-label">{{ bound.label }}</span>

                                    <el-date-picker
                                        v-model="event[bound.dateKey]"
                                        class="fe-event-form__date"
                                        type="date"
                                        format="ddd, MMM D"
                                        value-format="YYYY-MM-DD"
                                        :clearable="false"
                                        :aria-label="bound.dateLabel"
                                        @change="syncEndDate"
                                    />

                                    <el-time-picker
                                        v-model="event[bound.timeKey]"
                                        class="fe-event-form__time"
                                        format="hh:mm A"
                                        value-format="HH:mm"
                                        :clearable="false"
                                        :aria-label="bound.timeLabel"
                                    />
                                </div>

                                <div class="fe-event-form__window-row">
                                    <span class="fe-event-form__window-label">
                                        {{ $t('Timezone') }}
                                    </span>

                                    <el-select
                                        v-model="event.timezone"
                                        class="fe-event-form__timezone"
                                        filterable
                                        :aria-label="$t('Timezone')"
                                    >
                                        <el-option
                                            v-for="zone in timezones"
                                            :key="zone.value"
                                            :label="zone.label"
                                            :value="zone.value"
                                        />
                                    </el-select>
                                </div>
                            </div>
                        </div>

                        <div class="fe-event-form__card fe-event-form__field">
                            <label class="fe-event-form__field-label" for="fe-event-calendar">
                                {{ $t('Calendar *') }}
                            </label>

                            <el-select
                                id="fe-event-calendar"
                                v-model="event.calendar_id"
                                class="fe-event-form__calendar-select"
                                :placeholder="$t('Choose a calendar')"
                            >
                                <el-option
                                    v-for="calendar in calendars"
                                    :key="calendar.id"
                                    :label="calendar.title"
                                    :value="calendar.id"
                                />
                            </el-select>
                        </div>

                        <div class="fe-event-form__card fe-event-form__field">
                            <span class="fe-event-form__field-label">{{ $t('Status') }}</span>

                            <el-radio-group v-model="event.status">
                                <el-radio
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </el-radio>
                            </el-radio-group>
                        </div>

                        <div class="fe-event-form__card">
                            <button
                                type="button"
                                class="fe-event-form__row"
                                :aria-expanded="expanded.location"
                                @click="toggle('location')"
                            >
                                <el-icon class="fe-event-form__row-icon" aria-hidden="true">
                                    <LocationInformation />
                                </el-icon>

                                <span class="fe-event-form__row-text">
                                    <span class="fe-event-form__row-title">{{ venueSummary }}</span>
                                    <span class="fe-event-form__row-hint">
                                        {{ $t('Offline location or virtual link') }}
                                    </span>
                                </span>

                                <el-icon
                                    class="fe-event-form__row-chevron"
                                    :class="{ 'fe-event-form__row-chevron--open': expanded.location }"
                                    aria-hidden="true"
                                >
                                    <ArrowRight />
                                </el-icon>
                            </button>

                            <div v-show="expanded.location" class="fe-event-form__row-body">
                                <el-radio-group v-model="event.venue_type">
                                    <el-radio
                                        v-for="option in venueTypes"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </el-radio>
                                </el-radio-group>

                                <el-form v-if="event.venue_type === 'online'" class="fe-form" label-position="top">
                                    <el-form-item :label="$t('Meeting link *')">
                                        <el-input
                                            v-model="event.online_meeting_url"
                                            type="url"
                                            placeholder="https://meet.example.com/standup"
                                        />
                                        <span class="fe-form__hint">
                                            {{ $t('Only http and https links are accepted.') }}
                                        </span>
                                    </el-form-item>
                                </el-form>

                                <el-form v-else class="fe-form" label-position="top">
                                    <el-form-item :label="$t('Venue name')">
                                        <el-input
                                            v-model="event.venue"
                                            :placeholder="$t('e.g. Kingsway Hall')"
                                        />
                                    </el-form-item>

                                    <el-form-item :label="$t('Address')">
                                        <el-input
                                            v-model="event.address"
                                            type="textarea"
                                            :rows="2"
                                            :placeholder="$t('Street, city, country')"
                                        />
                                    </el-form-item>
                                </el-form>
                            </div>
                        </div>

                        <div class="fe-event-form__card">
                            <button
                                type="button"
                                class="fe-event-form__row"
                                :aria-expanded="expanded.description"
                                @click="toggle('description')"
                            >
                                <el-icon class="fe-event-form__row-icon" aria-hidden="true">
                                    <Document />
                                </el-icon>

                                <span class="fe-event-form__row-text">
                                    <span class="fe-event-form__row-title">{{ $t('Add Description') }}</span>
                                    <span class="fe-event-form__row-hint">
                                        {{ $t('What should guests know before they come?') }}
                                    </span>
                                </span>

                                <el-icon
                                    class="fe-event-form__row-chevron"
                                    :class="{ 'fe-event-form__row-chevron--open': expanded.description }"
                                    aria-hidden="true"
                                >
                                    <ArrowRight />
                                </el-icon>
                            </button>

                            <div v-if="expanded.description" class="fe-event-form__row-body">
                                <WpEditor v-model="event.description" :height="180" />
                            </div>
                        </div>

                        <el-button
                            type="primary"
                            class="fe-event-form__submit"
                            native-type="submit"
                            :loading="saving"
                            :disabled="saving"
                        >
                            {{ $t('Create Event') }}
                        </el-button>
                    </div>
                </div>
            </form>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { Document, LocationInformation, ArrowRight, Picture } from '@element-plus/icons-vue';
    import WpEditor from '@/components/WpEditor.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import MediaPicker from '@/components/MediaPicker.vue';

    const config = window.fluentEventAdmin;

    export default {
        name: 'CreateEvent',
        components: {
            Document,
            LocationInformation,
            ArrowRight,
            Picture,
            WpEditor,
            ErrorState,
            MediaPicker,
        },
        data() {
            return {
                loading: false,
                error: '',
                saving: false,
                calendars: [],
                event: {},
                expanded: {
                    location: false,
                    description: false,
                },
            };
        },
        computed: {
            statuses() {
                return [
                    { value: 'public', label: this.$t('Public') },
                    { value: 'private', label: this.$t('Private') },
                    { value: 'draft', label: this.$t('Draft') },
                ];
            },
            venueTypes() {
                return [
                    { value: 'offline', label: this.$t('In person') },
                    { value: 'online', label: this.$t('Online') },
                ];
            },
            bounds() {
                return [
                    {
                        key: 'start',
                        label: this.$t('Start'),
                        dateKey: 'start_date',
                        timeKey: 'start_time',
                        dateLabel: this.$t('Start date'),
                        timeLabel: this.$t('Start time'),
                    },
                    {
                        key: 'end',
                        label: this.$t('End'),
                        dateKey: 'end_date',
                        timeKey: 'end_time',
                        dateLabel: this.$t('End date'),
                        timeLabel: this.$t('End time'),
                    },
                ];
            },
            timezones() {
                const zones = Intl.supportedValuesOf?.('timeZone') || [];
                const list = zones.includes(this.defaultTimezone)
                    ? zones
                    : [this.defaultTimezone, ...zones];

                return list.map(zone => ({
                    value: zone,
                    label: `${zone} (${this.offsetOf(zone)})`,
                }));
            },
            defaultTimezone() {
                const timezone = config?.timezone || '';
                if (this.isValidTimezone(timezone)) return timezone;

                return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            },
            venueSummary() {
                if (this.event.venue_type === 'online') {
                    return this.event.online_meeting_url || this.$t('Add Venue Type');
                }

                return this.event.venue || this.$t('Add Venue Type');
            },
        },
        methods: {
            initForm() {
                const start = dayjs().add(1, 'hour').startOf('hour');

                return {
                    calendar_id: null,
                    status: this.statuses[0].value,
                    title: '',
                    description: '',
                    start_date: start.format('YYYY-MM-DD'),
                    start_time: start.format('HH:mm'),
                    end_date: start.format('YYYY-MM-DD'),
                    end_time: start.add(1, 'hour').format('HH:mm'),
                    timezone: this.defaultTimezone,
                    venue_type: this.venueTypes[0].value,
                    online_meeting_url: '',
                    venue: '',
                    address: '',
                    cover_url: '',
                };
            },
            fetchCalendars() {
                if(this.loading) return;
                this.loading = true;
                this.$get('calendars', { per_page: 100 })
                    .then(response => {
                        this.error = '';
                        this.calendars = response.calendars.data;
                        this.event = this.initForm();
                        const requested = Number(this.$route.query.calendar_id);
                        const preSelected = this.calendars.find(calendar => calendar.id === requested);
                        this.event.calendar_id = preSelected?.id || this.calendars[0]?.id;
                    })
                    .catch(error => {
                        this.error = error?.message || '';
                        this.calendars = [];
                    })
                    .finally(() => {
                        this.loading = false;
                        this.$nextTick(() => this.$refs.titleInput?.focus());
                    });
            },
            syncEndDate() {
                if (dayjs(this.event.end_date).isBefore(this.event.start_date)) {
                    this.event.end_date = this.event.start_date;
                }
            },
            isValidTimezone(timezone) {
                if (!timezone) return false;

                try {
                    new Intl.DateTimeFormat('en-US', { timeZone: timezone });
                    return true;
                } catch {
                    return false;
                }
            },
            offsetOf(timezone) {
                if (!timezone) return '';

                try {
                    return new Intl.DateTimeFormat('en-US', {
                        timeZone: timezone,
                        timeZoneName: 'longOffset',
                    })
                        .formatToParts(new Date())
                        .find(part => part.type === 'timeZoneName')?.value || '';
                } catch {
                    return '';
                }
            },
            toggle(section) {
                this.expanded[section] = !this.expanded[section];
            },
            saveEvent() {
                if (this.saving) return;
                this.saving = true;

                this.$post('events', {
                    calendar_id: this.event.calendar_id,
                    status: this.event.status,
                    title: this.event.title,
                    description: this.event.description,
                    start_at: `${this.event.start_date} ${this.event.start_time}:00`,
                    end_at: `${this.event.end_date} ${this.event.end_time}:00`,
                    timezone: this.event.timezone,
                    venue_type: this.event.venue_type,
                    cover_url: this.event.cover_url,
                    online_meeting_url: this.event.online_meeting_url,
                    venue: this.event.venue,
                    address: this.event.address,
                })
                    .then(response => {
                        this.$notifySuccess(response.message);
                        this.$router.push({ name: 'events' });
                    })
                    .catch(error => {
                        this.expanded.location = true;
                        this.$handleError(error);
                    })
                    .finally(() => {
                        this.saving = false;
                    });
            },
            goToCalendarCreate() {
                this.$router.push({ name: 'calendar_create' });
            },
        },
        created() {
            this.fetchCalendars();
        },
        mounted() {
            this.$changeTitle(this.$t('Create Event'));
        },
    };
</script>
