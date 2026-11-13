<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-page--narrow fe-event-create">
            <div class="fe-page__header">
                <div>
                    <h1 class="fe-page__title">{{ pageTitle }}</h1>
                    <p class="fe-page__subtitle">{{ pageSubtitle }}</p>
                </div>

                <div class="fe-page__actions">
                    <el-button @click="$goBack()">
                        {{ $t('Go Back') }}
                    </el-button>
                </div>
            </div>

            <Skeleton v-if="loadingEvent" type="event-form" />

            <ErrorState v-else-if="error" :message="error" @retry="loadForm" />

            <div v-else-if="!loadingCalendars && !calendars.length" class="fe-card">
                <el-empty :description="$t('An event needs a calendar to live in, and there are none yet.')">
                    <el-button
                        v-if="$userCan('write_calendars')"
                        type="primary"
                        @click="goToCalendarCreate"
                    >
                        {{ $t('Create a Calendar') }}
                    </el-button>
                </el-empty>
            </div>

            <div v-else class="fe-card fe-card--flush">
                <el-form
                    id="fe-event-form"
                    class="fe-form fe-form--sectioned fe-event-form"
                    label-position="top"
                    novalidate
                    @submit.prevent="saveEvent"
                >
                    <section class="fe-form__section" data-field="cover_url">
                        <div class="fe-form__section-rail">
                            <h2 class="fe-form__section-title">{{ $t('Cover Image') }}</h2>
                            <p class="fe-form__section-desc">
                                {{ $t('Shown at the top of the event page and on event cards.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <MediaPicker
                                v-model="event.cover_url"
                                class="fe-cover-picker"
                                :frame-title="$t('Select a cover image')"
                                :action-label="$t('Use this image')"
                                :alt="$t('Event cover image')"
                                :add-label="$t('Add a cover image')"
                                :change-label="$t('Change Cover Image')"
                                :remove-label="$t('Remove cover image')"
                            >
                                <template #placeholder>
                                    <div class="fe-cover-picker__placeholder">
                                        <el-icon class="fe-cover-picker__placeholder-icon" aria-hidden="true">
                                            <Picture />
                                        </el-icon>
                                        <span class="fe-cover-picker__placeholder-text">
                                            {{ $t('Click to add a cover image') }}
                                        </span>
                                    </div>
                                </template>
                            </MediaPicker>
                        </div>
                    </section>

                    <section class="fe-form__section">
                        <div class="fe-form__section-rail">
                            <h2 class="fe-form__section-title">{{ $t('Basics') }}</h2>
                            <p class="fe-form__section-desc">
                                {{ $t('What the event is called and which calendar it belongs to.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <el-form-item
                                :label="$t('Event Name')"
                                for="fe-event-title"
                                required
                                data-field="title"
                            >
                                <el-input
                                    id="fe-event-title"
                                    ref="titleInput"
                                    v-model="event.title"
                                    :placeholder="$t('e.g. Friday Q&A')"
                                />
                            </el-form-item>

                            <el-form-item
                                :label="$t('Calendar')"
                                required
                                data-field="calendar_id"
                            >
                                <el-select
                                    v-model="event.calendar_id"
                                    :aria-label="$t('Calendar')"
                                    :placeholder="$t('Choose a calendar')"
                                    filterable
                                    fit-input-width
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
                                            <span class="fe-event-form__calendar-name" :title="calendar.title">{{ calendar.title }}</span>
                                            <el-tag
                                                v-if="calendar.status !== 'public'"
                                                class="fe-event-form__calendar-status"
                                                :type="$calendarStatusType(calendar.status)"
                                                size="small"
                                            >
                                                {{ $calendarStatusLabel(calendar.status) }}
                                            </el-tag>
                                        </span>
                                    </el-option>
                                </el-select>
                            </el-form-item>
                        </div>
                    </section>

                    <section class="fe-form__section" data-field="status">
                        <div class="fe-form__section-rail">
                            <h2 class="fe-form__section-title">{{ $t('Visibility') }}</h2>
                            <p class="fe-form__section-desc">
                                {{ $t('Choose who can find this event.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <el-radio-group
                                v-model="event.status"
                                class="fe-form__choices"
                                :aria-label="$t('Visibility')"
                            >
                                <el-radio
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                    border
                                >
                                    <span class="fe-form__choice-title">{{ option.label }}</span>
                                    <span class="fe-form__hint">{{ option.hint }}</span>
                                </el-radio>
                            </el-radio-group>
                        </div>
                    </section>

                    <section class="fe-form__section" data-field="description">
                        <div class="fe-form__section-rail">
                            <h2 id="fe-event-description-label" class="fe-form__section-title">
                                {{ $t('Description') }}
                            </h2>
                            <p class="fe-form__section-desc">
                                {{ $t('Optional. Shown on the event page.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <MarkdownEditor
                                v-model="event.description_md"
                                :upload="uploadImage"
                                :placeholder="$t('Describe the event, or type / to add images, lists and more')"
                                aria-labelledby="fe-event-description-label"
                            />
                        </div>
                    </section>

                    <section class="fe-form__section">
                        <div class="fe-form__section-rail">
                            <h2 class="fe-form__section-title">{{ $t('Date and Time') }}</h2>
                            <p class="fe-form__section-desc">
                                {{ $t('When the event starts and ends.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <el-form-item
                                data-field="start_at end_at timezone"
                            >
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
                            </el-form-item>
                        </div>
                    </section>

                    <section class="fe-form__section" data-field="venue_type">
                        <div class="fe-form__section-rail">
                            <h2 class="fe-form__section-title">{{ $t('Venue Type') }}</h2>
                            <p class="fe-form__section-desc">
                                {{ $t('A venue for in-person events, a link for online ones.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <SegmentedSwitch
                                v-model="event.venue_type"
                                class="fe-event-form__venue-switch"
                                :options="venueTypes"
                                :label="$t('Venue Type')"
                                show-labels
                            />

                            <el-form-item
                                v-if="event.venue_type === 'online'"
                                :label="$t('Meeting Link')"
                                for="fe-event-meeting-link"
                                required
                                data-field="online_meeting_url"
                            >
                                <el-input
                                    id="fe-event-meeting-link"
                                    v-model="event.online_meeting_url"
                                    type="url"
                                    placeholder="https://meet.example.com/standup"
                                />
                                <span class="fe-form__hint">
                                    {{ $t('Only http and https links are accepted.') }}
                                </span>
                            </el-form-item>

                            <template v-else>
                                <el-form-item
                                    :label="$t('Venue Name')"
                                    for="fe-event-venue"
                                    data-field="venue"
                                >
                                    <el-input
                                        id="fe-event-venue"
                                        v-model="event.venue"
                                        :maxlength="appVars.event_venue_max_length"
                                        :placeholder="$t('e.g. Kingsway Hall')"
                                    />
                                </el-form-item>

                                <el-form-item
                                    :label="$t('Address')"
                                    for="fe-event-address"
                                    data-field="address"
                                >
                                    <el-input
                                        id="fe-event-address"
                                        v-model="event.address"
                                        type="textarea"
                                        :rows="2"
                                        :placeholder="$t('Street, city, country')"
                                    />
                                </el-form-item>
                            </template>
                        </div>
                    </section>

                    <section class="fe-form__section">
                        <div class="fe-form__section-rail">
                            <h2 class="fe-form__section-title">{{ $t('Registration') }}</h2>
                            <p class="fe-form__section-desc">
                                {{ $t('How guests join and what they see.') }}
                            </p>
                        </div>

                        <div class="fe-form__section-fields">
                            <el-form-item :label="$t('Entry Type')" data-field="entry_type">
                                <el-radio-group
                                    v-model="event.entry_type"
                                    class="fe-form__choices"
                                    :aria-label="$t('Entry Type')"
                                >
                                    <el-radio
                                        v-for="option in $entryTypes()"
                                        :key="option.value"
                                        :value="option.value"
                                        border
                                    >
                                        <span class="fe-form__choice-title">{{ option.label }}</span>
                                        <span class="fe-form__hint">{{ option.hint }}</span>
                                    </el-radio>
                                </el-radio-group>
                            </el-form-item>

                            <div v-if="['rsvp', 'open'].includes(event.entry_type)" class="fe-form__switch-row" data-field="show_guest_count">
                                <label class="fe-form__switch-text" for="fe-event-show-guest-count">
                                    {{ $t('Show how many guests are going') }}
                                </label>

                                <el-switch
                                    id="fe-event-show-guest-count"
                                    v-model="event.show_guest_count"
                                    active-value="yes"
                                    inactive-value="no"
                                />
                            </div>
                        </div>
                    </section>

                    <div class="fe-form__actions fe-form__actions--bar">
                        <el-button
                            type="primary"
                            native-type="submit"
                            :loading="saving"
                            :disabled="saving"
                        >
                            {{ submitLabel }}
                        </el-button>
                    </div>
                </el-form>
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { MapLocation, Picture, VideoCamera } from '@element-plus/icons-vue';
    import MarkdownEditor from '@/components/MarkdownEditor.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import MediaPicker from '@/components/MediaPicker.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import Skeleton from '@/components/Skeleton.vue';

    const config = window.fluentEventAdmin;

    // Built once per page load: ~420 zones each need their own Intl formatter.
    let timezoneOptions = null;

    export default {
        name: 'CreateOrUpdateEvent',
        components: {
            Picture,
            MarkdownEditor,
            ErrorState,
            MediaPicker,
            SegmentedSwitch,
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
                    { value: 'offline', label: this.$t('In Person'), icon: MapLocation },
                    { value: 'online', label: this.$t('Online'), icon: VideoCamera },
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
                    description_md: meta.description_md || '',
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
                    entry_type: event ? (meta.entry_type || '') : 'rsvp',
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
                    path: 'calendars/options',
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
                    notFoundPage: true,
                    onSuccess: response => {
                        this.event = this.initForm(response.event);
                    },
                    // loadingEvent drops after onSuccess, so the title input only exists from here.
                    onFinally: () => this.focusTitle(),
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
            saveEvent() {
                this.$requestServer({
                    method: this.isEditing ? 'patch' : 'post',
                    path: this.isEditing ? `events/${this.eventId}` : 'events',
                    payload: {
                        calendar_id: this.event.calendar_id,
                        status: this.event.status,
                        title: this.event.title,
                        description_md: this.event.description_md,
                        start_at: `${this.event.start_date} ${this.event.start_time}:00`,
                        end_at: `${this.event.end_date} ${this.event.end_time}:00`,
                        timezone: this.event.timezone,
                        venue_type: this.event.venue_type,
                        cover_url: this.event.cover_url,
                        online_meeting_url: this.event.online_meeting_url,
                        venue: this.event.venue,
                        address: this.event.address,
                        entry_type: this.event.entry_type,
                        show_guest_count: this.event.show_guest_count,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.$goBack({
                            name: 'event_detail',
                            params: { id: this.eventId || response.event.id },
                        });
                    },
                });
            },
            // Description images go to the media library, as the cover does.
            async uploadImage(file) {
                const data = new FormData();
                data.append('file', file);

                try {
                    return (await wp.apiFetch({ path: '/wp/v2/media', method: 'POST', body: data })).source_url;
                } catch (error) {
                    this.$handleError(error);
                    throw error;
                }
            },
            goToCalendarCreate() {
                this.$router.push({ name: 'calendar_create' });
            },
        },
        mounted() {
            this.$changeTitle(this.pageTitle);
            this.loadForm();
        },
    };
</script>
