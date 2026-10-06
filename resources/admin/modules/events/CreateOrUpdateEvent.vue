<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-page--narrow fe-event-create">
            <div class="fe-page__header">
                <div>
                    <h1 class="fe-page__title">{{ pageTitle }}</h1>
                    <p class="fe-page__subtitle">{{ pageSubtitle }}</p>
                </div>

                <div class="fe-page__actions">
                    <el-button @click="$goBack">
                        {{ $t('Go Back') }}
                    </el-button>
                </div>
            </div>

            <Skeleton v-if="loadingEvent" type="event-form" />

            <ErrorState v-else-if="error" :message="error" @retry="loadForm" />

            <div v-else-if="!loadingCalendars && !calendars.length" class="fe-card">
                <el-empty :description="$t('An event needs a calendar to live in, and there are none yet.')">
                    <!-- <el-button
                        v-if="$userCan('write_calendars')"
                        type="primary"
                        @click="goToCalendarCreate"
                    >
                        {{ $t('Create a Calendar') }}
                    </el-button> -->
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
                        <div class="fe-event-form__header">
                            <el-select
                                v-model="event.calendar_id"
                                class="fe-event-form__calendar-select"
                                :aria-label="$t('Calendar')"
                                :placeholder="$t('Choose a calendar')"
                                filterable
                                :loading="loadingCalendars"
                                :loading-text="$t('Loading…')"
                            >
                                <template #prefix>
                                    <span
                                        class="fe-color-swatch fe-event-form__calendar-swatch"
                                        :style="{ background: calendarColor }"
                                    ></span>
                                </template>

                                <el-option
                                    v-for="calendar in calendars"
                                    :key="calendar.id"
                                    :label="calendar.title"
                                    :value="calendar.id"
                                >
                                    <span class="fe-event-form__calendar-option">
                                        <span
                                            class="fe-color-swatch fe-event-form__calendar-swatch"
                                            :style="{ background: calendar.meta?.theme_color }"
                                        ></span>
                                        {{ calendar.title }}
                                    </span>
                                </el-option>
                            </el-select>

                            <el-select
                                v-model="event.status"
                                class="fe-event-form__header-select"
                                :aria-label="$t('Status')"
                            >
                                <el-option
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :label="option.label"
                                    :value="option.value"
                                />
                            </el-select>
                        </div>

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
                                        :format="dateFormat"
                                        value-format="YYYY-MM-DD"
                                        :clearable="false"
                                        :aria-label="bound.dateLabel"
                                        :disabled-date="bound.disabledDate"
                                        @change="syncEndDate"
                                    />

                                    <el-time-picker
                                        v-model="event[bound.timeKey]"
                                        class="fe-event-form__time"
                                        :format="timeFormat"
                                        value-format="HH:mm"
                                        :clearable="false"
                                        :aria-label="bound.timeLabel"
                                    />
                                </div>

                                <div class="fe-event-form__window-row">
                                    <span class="fe-event-form__window-label">
                                        {{ $t('Timezone') }}
                                    </span>

                                    <el-select-v2
                                        v-model="event.timezone"
                                        class="fe-event-form__timezone"
                                        :options="timezones"
                                        filterable
                                        :aria-label="$t('Timezone')"
                                    />
                                </div>
                            </div>
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
                                    <span class="fe-event-form__row-title">{{ $t('Add Venue Type') }}</span>
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
                                <el-radio-group v-model="event.venue_type" :aria-label="$t('Venue type')">
                                    <el-radio
                                        v-for="option in venueTypes"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </el-radio>
                                </el-radio-group>

                                <!-- A div, not <el-form>: a nested form swallows Enter and submits natively. -->
                                <div v-if="event.venue_type === 'online'" class="fe-form">
                                    <el-form-item :label="$t('Meeting link *')" label-position="top">
                                        <el-input
                                            v-model="event.online_meeting_url"
                                            type="url"
                                            placeholder="https://meet.example.com/standup"
                                        />
                                        <span class="fe-form__hint">
                                            {{ $t('Only http and https links are accepted.') }}
                                        </span>
                                    </el-form-item>
                                </div>

                                <div v-else class="fe-form">
                                    <el-form-item :label="$t('Venue name')" label-position="top">
                                        <el-input
                                            v-model="event.venue"
                                            :placeholder="$t('e.g. Kingsway Hall')"
                                        />
                                    </el-form-item>

                                    <el-form-item :label="$t('Address')" label-position="top">
                                        <el-input
                                            v-model="event.address"
                                            type="textarea"
                                            :rows="2"
                                            :placeholder="$t('Street, city, country')"
                                        />
                                    </el-form-item>
                                </div>
                            </div>
                        </div>

                        <div class="fe-event-form__card">
                            <button
                                type="button"
                                class="fe-event-form__row"
                                :aria-expanded="expanded.registration"
                                @click="toggle('registration')"
                            >
                                <el-icon class="fe-event-form__row-icon" aria-hidden="true">
                                    <User />
                                </el-icon>

                                <span class="fe-event-form__row-text">
                                    <span class="fe-event-form__row-title">{{ $t('Registration Type') }}</span>
                                    <span class="fe-event-form__row-hint">
                                        {{ $t('How guests join, and how many can') }}
                                    </span>
                                </span>

                                <el-icon
                                    class="fe-event-form__row-chevron"
                                    :class="{ 'fe-event-form__row-chevron--open': expanded.registration }"
                                    aria-hidden="true"
                                >
                                    <ArrowRight />
                                </el-icon>
                            </button>

                            <div v-show="expanded.registration" class="fe-event-form__row-body">
                                <div class="fe-form">
                                    <el-form-item :label="$t('Entry type *')" label-position="top">
                                        <el-radio-group
                                            v-model="event.entry_type"
                                            class="fe-form__choices"
                                            :aria-label="$t('Registration Type')"
                                        >
                                            <el-radio
                                                v-for="option in entryTypes"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                <span class="fe-form__choice-title">{{ option.label }}</span>
                                                <span class="fe-form__hint">{{ option.hint }}</span>
                                            </el-radio>
                                        </el-radio-group>
                                    </el-form-item>

                                    <el-form-item
                                        v-if="event.entry_type !== 'none'"
                                        :label="$t('Seats')"
                                        label-position="top"
                                    >
                                        <div class="fe-form__switch-group">
                                            <div class="fe-form__switch-row">
                                                <el-switch v-model="event.limit_seats" :aria-label="$t('Limit seats')" />
                                                <span class="fe-form__switch-text">{{ $t('Limit seats') }}</span>
                                                <el-input-number
                                                    v-if="event.limit_seats"
                                                    v-model="event.max_guests"
                                                    class="fe-form__number"
                                                    :min="0"
                                                    :controls="false"
                                                    :aria-label="$t('Max guests')"
                                                />
                                            </div>

                                            <div class="fe-form__switch-row">
                                                <el-switch
                                                    v-model="event.show_guest_count"
                                                    active-value="yes"
                                                    inactive-value="no"
                                                    :aria-label="$t('Show how many guests are going')"
                                                />
                                                <span class="fe-form__switch-text">{{ $t('Show how many guests are going') }}</span>
                                            </div>
                                        </div>
                                    </el-form-item>
                                </div>
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
                                    <span id="fe-event-description-label" class="fe-event-form__row-title">{{ $t('Add Description') }}</span>
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
                                <WpEditor
                                    v-model="event.description"
                                    :height="180"
                                    aria-labelledby="fe-event-description-label"
                                />
                            </div>
                        </div>

                        <el-button
                            type="primary"
                            class="fe-event-form__submit"
                            native-type="submit"
                            :loading="saving"
                            :disabled="saving"
                        >
                            {{ submitLabel }}
                        </el-button>
                    </div>
                </div>
            </form>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { Document, LocationInformation, ArrowRight, Picture, User } from '@element-plus/icons-vue';
    import WpEditor from '@/components/WpEditor.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import MediaPicker from '@/components/MediaPicker.vue';
    import Skeleton from '@/components/Skeleton.vue';

    const config = window.fluentEventAdmin;

    // Built once per page load: ~420 zones each need their own Intl formatter.
    let timezoneOptions = null;

    export default {
        name: 'CreateOrUpdateEvent',
        components: {
            Document,
            LocationInformation,
            ArrowRight,
            Picture,
            User,
            WpEditor,
            ErrorState,
            MediaPicker,
            Skeleton,
        },
        data() {
            return {
                loadingCalendars: false,
                loadingEvent: false,
                calendarError: '',
                eventError: '',
                saving: false,
                calendars: [],
                event: {},
                expanded: {
                    location: false,
                    registration: false,
                    description: false,
                },
            };
        },
        computed: {
            error() {
                return this.eventError || this.calendarError;
            },
            eventId() {
                return this.$route.params.id;
            },
            dateFormat() {
                return window.fluentEventAdmin?.date_format || 'ddd, MMM D';
            },
            timeFormat() {
                return window.fluentEventAdmin?.time_format || 'hh:mm A';
            },
            isEditing() {
                return !!this.eventId;
            },
            pageTitle() {
                return this.isEditing ? this.$t('Edit Event') : this.$t('Create Event');
            },
            pageSubtitle() {
                return this.isEditing
                    ? this.$t('Update the event details. Guests already registered are not affected.')
                    : this.$t('Events live inside a calendar. Pick one, say when it happens, and publish.');
            },
            submitLabel() {
                return this.isEditing ? this.$t('Update Event') : this.$t('Create Event');
            },
            statuses() {
                return this.$eventStatuses();
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
                        disabledDate: date => dayjs(date).isBefore(this.event.start_date, 'day'),
                    },
                ];
            },
            timezones() {
                if (timezoneOptions) return timezoneOptions;

                const zones = Intl.supportedValuesOf?.('timeZone') || [];
                const list = zones.includes(this.defaultTimezone)
                    ? zones
                    : [this.defaultTimezone, ...zones];

                return timezoneOptions = list.map(zone => ({
                    value: zone,
                    label: `${zone} (${this.offsetOf(zone)})`,
                }));
            },
            defaultTimezone() {
                const timezone = config?.timezone || '';
                if (this.isValidTimezone(timezone)) return timezone;

                return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            },
            calendarColor() {
                const selected = this.calendars.find(
                    calendar => calendar.id === this.event.calendar_id
                );

                return selected?.meta?.theme_color;
            },
            entryTypes() {
                return [
                    {
                        value: 'rsvp',
                        label: this.$t('RSVP'),
                        hint: this.$t('Logged-in members say whether they are going.'),
                    },
                    {
                        value: 'open',
                        label: this.$t('Open registration'),
                        hint: this.$t('Anyone who can see the event registers with a name and email.'),
                    },
                    {
                        value: 'none',
                        label: this.$t('No registration'),
                        hint: this.$t('An announcement only. Nobody registers.'),
                    },
                ];
            },
        },
        methods: {
            initForm(event = null) {
                const meta = event?.meta || {};
                const start = event ? dayjs(event.start_at_local) : dayjs().add(1, 'hour').startOf('hour');
                const end = event ? dayjs(event.end_at_local) : start.add(1, 'hour');

                return {
                    calendar_id: event?.calendar_id || null,
                    status: event?.status || this.statuses[0].value,
                    title: event?.title || '',
                    description: event?.description || '',
                    start_date: start.format('YYYY-MM-DD'),
                    start_time: start.format('HH:mm'),
                    end_date: end.format('YYYY-MM-DD'),
                    end_time: end.format('HH:mm'),
                    timezone: event?.timezone || this.defaultTimezone,
                    venue_type: event?.venue_type || this.venueTypes[0].value,
                    online_meeting_url: meta.online_meeting_url || '',
                    venue: meta.venue || '',
                    address: meta.address || '',
                    cover_url: meta.cover_url || '',
                    entry_type: meta.entry_type || '',
                    limit_seats: (meta.max_guests ?? -1) >= 0,
                    max_guests: (meta.max_guests ?? -1) >= 0 ? meta.max_guests : 50,
                    show_guest_count: meta.show_guest_count || 'yes',
                };
            },
            loadForm() {
                this.event = this.initForm();
                this.fetchCalendars();

                if (this.isEditing) {
                    this.fetchEvent();
                    return;
                }

                this.focusTitle();
            },
            fetchCalendars() {
                this.$requestServer({
                    path: 'calendars',
                    payload: { view: 'options' },
                    loadingState: 'loadingCalendars',
                    errorState: 'calendarError',
                    onSuccess: response => {
                        this.calendars = response.calendars;
                        this.preSelectCalendar();
                    },
                    onError: () => {
                        this.calendars = [];
                    },
                    
                });
            },
            fetchEvent() {
                this.$requestServer({
                    path: `events/${this.eventId}`,
                    loadingState: 'loadingEvent',
                    errorState: 'eventError',
                    onSuccess: response => {
                        this.event = this.initForm(response.event);

                        this.expanded.location = !!(this.event.venue
                            || this.event.address
                            || this.event.online_meeting_url);
                        this.expanded.registration = !!this.event.entry_type;
                        this.expanded.description = !!this.event.description;

                        this.focusTitle();
                    },
                });
            },
            focusTitle() {
                this.$nextTick(() => this.$refs.titleInput?.focus());
            },
            preSelectCalendar() {
                if (this.isEditing) return;
                const requested = Number(this.$route.query.calendar_id);
                const preSelected = this.calendars.find(calendar => calendar.id === requested);
                this.event.calendar_id = preSelected?.id || this.calendars[0]?.id;
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
                this.$requestServer({
                    method: this.isEditing ? 'patch' : 'post',
                    path: this.isEditing ? `events/${this.eventId}` : 'events',
                    payload: {
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
                        entry_type: this.event.entry_type,
                        max_guests: this.event.limit_seats ? this.event.max_guests : -1,
                        show_guest_count: this.event.show_guest_count,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    onSuccess: response => {
                        this.$router.push({
                            name: 'event_detail',
                            params: { id: this.eventId || response.event.id },
                        });
                    },
                    onError: error => {
                        this.expanded.location = true;
                        this.$handleError(error);
                    },
                });
            },
            // goToCalendarCreate() {
            //     this.$router.push({ name: 'calendar_create' });
            // },
        },
        mounted() {
            this.$changeTitle(this.pageTitle);
            this.loadForm();
        },
    };
</script>
