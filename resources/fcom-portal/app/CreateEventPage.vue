<template>
    <div class="fe-create-event">
        <div class="fe-create-event__header">
            <button
                type="button"
                class="fe-create-event__back"
                :aria-label="$t('Back')"
                :title="$t('Back')"
                @click="goBack"
            >
                <el-icon><ArrowLeft/></el-icon>
            </button>

            <h1 class="fe-create-event__heading">{{ $t('Create Event') }}</h1>
        </div>

        <form class="fe-create-event__grid" @submit.prevent="submit">
            <aside class="fe-create-event__aside">
                <div class="fe-create-event__cover">
                    <label v-loading="uploading" class="fe-create-event__cover-box">
                        <img
                            v-if="event.cover_url"
                            class="fe-create-event__cover-img"
                            :src="event.cover_url"
                            :alt="$t('Event cover image')"
                        />

                        <span v-else class="fe-create-event__cover-empty">
                            <el-icon class="fe-create-event__cover-icon" aria-hidden="true"><Picture/></el-icon>
                            <span>{{ $t('Click to add a cover image') }}</span>
                        </span>

                        <input
                            class="fe-create-event__file"
                            type="file"
                            accept="image/*"
                            :disabled="uploading"
                            :aria-label="$t('Add a cover image')"
                            @change="uploadCover"
                        />
                    </label>

                    <el-button
                        v-if="event.cover_url"
                        class="fe-create-event__cover-remove"
                        circle
                        :aria-label="$t('Remove cover image')"
                        :title="$t('Remove cover image')"
                        @click="event.cover_url = ''"
                    >
                        <el-icon><Delete/></el-icon>
                    </el-button>
                </div>
            </aside>

            <div class="fe-create-event__main">
                <label class="fe-create-event__title-label" for="fe-create-event-title">{{ $t('Event name') }}</label>
                <input
                    id="fe-create-event-title"
                    ref="titleInput"
                    v-model="event.title"
                    class="fe-create-event__title"
                    type="text"
                    maxlength="255"
                    :placeholder="$t('Event Name')"
                />

                <div class="fe-create-event__card fe-create-event__schedule">
                    <div v-for="bound in bounds" :key="bound.dateKey" class="fe-create-event__window-row">
                        <span class="fe-create-event__window-label">{{ bound.label }}</span>

                        <el-date-picker
                            v-model="event[bound.dateKey]"
                            class="fe-create-event__date"
                            type="date"
                            format="MMM D, YYYY"
                            value-format="YYYY-MM-DD"
                            :clearable="false"
                            :aria-label="bound.dateLabel"
                            :disabled-date="bound.disabledDate"
                            @change="syncEndDate"
                        />

                        <el-time-picker
                            v-model="event[bound.timeKey]"
                            class="fe-create-event__time"
                            format="h:mm A"
                            value-format="HH:mm"
                            :clearable="false"
                            :aria-label="bound.timeLabel"
                        />
                    </div>

                    <div class="fe-create-event__window-row">
                        <span class="fe-create-event__window-label">{{ $t('Timezone') }}</span>

                        <el-select-v2
                            v-model="event.timezone"
                            class="fe-create-event__timezone"
                            :options="timezones"
                            filterable
                            :aria-label="$t('Timezone')"
                        />
                    </div>
                </div>

                <div class="fe-create-event__card">
                    <button
                        type="button"
                        class="fe-create-event__row"
                        :aria-expanded="expanded.location"
                        @click="expanded.location = !expanded.location"
                    >
                        <el-icon class="fe-create-event__row-icon" aria-hidden="true"><LocationInformation/></el-icon>

                        <span class="fe-create-event__row-text">
                            <span class="fe-create-event__row-title">{{ $t('Add Venue Type') }}</span>
                            <span class="fe-create-event__row-hint">{{ $t('Offline location or virtual link') }}</span>
                        </span>

                        <el-icon
                            class="fe-create-event__row-chevron"
                            :class="{ 'fe-create-event__row-chevron--open': expanded.location }"
                            aria-hidden="true"
                        >
                            <ArrowRight/>
                        </el-icon>
                    </button>

                    <div v-show="expanded.location" class="fe-create-event__row-body">
                        <el-radio-group v-model="event.venue_type" :aria-label="$t('Venue type')">
                            <el-radio value="offline">{{ $t('In person') }}</el-radio>
                            <el-radio value="online">{{ $t('Online') }}</el-radio>
                        </el-radio-group>

                        <el-form-item v-if="event.venue_type === 'online'" :label="$t('Meeting link *')" label-position="top">
                            <el-input v-model="event.online_meeting_url" type="url" placeholder="https://meet.example.com/standup"/>
                        </el-form-item>

                        <template v-else>
                            <el-form-item :label="$t('Venue name')" label-position="top">
                                <el-input v-model="event.venue" maxlength="255" :placeholder="$t('e.g. Kingsway Hall')"/>
                            </el-form-item>

                            <el-form-item :label="$t('Address')" label-position="top">
                                <el-input
                                    v-model="event.address"
                                    type="textarea"
                                    :rows="2"
                                    maxlength="500"
                                    :placeholder="$t('Street, city, country')"
                                />
                            </el-form-item>
                        </template>
                    </div>
                </div>

                <div class="fe-create-event__card">
                    <button
                        type="button"
                        class="fe-create-event__row"
                        :aria-expanded="expanded.registration"
                        @click="expanded.registration = !expanded.registration"
                    >
                        <el-icon class="fe-create-event__row-icon" aria-hidden="true"><User/></el-icon>

                        <span class="fe-create-event__row-text">
                            <span class="fe-create-event__row-title">{{ $t('Registration Type') }}</span>
                            <span class="fe-create-event__row-hint">{{ $t('How guests join, and how many can') }}</span>
                        </span>

                        <el-icon
                            class="fe-create-event__row-chevron"
                            :class="{ 'fe-create-event__row-chevron--open': expanded.registration }"
                            aria-hidden="true"
                        >
                            <ArrowRight/>
                        </el-icon>
                    </button>

                    <div v-show="expanded.registration" class="fe-create-event__row-body">
                        <el-form-item :label="$t('Entry type *')" label-position="top">
                            <el-radio-group
                                v-model="event.entry_type"
                                class="fe-create-event__choices"
                                :aria-label="$t('Registration Type')"
                            >
                                <el-radio v-for="option in entryTypes" :key="option.value" :value="option.value">
                                    <span class="fe-create-event__choice-title">{{ option.label }}</span>
                                    <span class="fe-create-event__choice-hint">{{ option.hint }}</span>
                                </el-radio>
                            </el-radio-group>
                        </el-form-item>

                        <el-form-item v-if="event.entry_type !== 'none'" :label="$t('Seats')" label-position="top">
                            <div class="fe-create-event__switch-group">
                                <div class="fe-create-event__switch-row">
                                    <el-switch v-model="event.limit_seats" :aria-label="$t('Limit seats')"/>
                                    <span class="fe-create-event__switch-text">{{ $t('Limit seats') }}</span>
                                    <el-input-number
                                        v-if="event.limit_seats"
                                        v-model="event.max_guests"
                                        class="fe-create-event__number"
                                        :min="1"
                                        :controls="false"
                                        :aria-label="$t('Max guests')"
                                    />
                                </div>

                                <div class="fe-create-event__switch-row">
                                    <el-switch
                                        v-model="event.show_guest_count"
                                        active-value="yes"
                                        inactive-value="no"
                                        :aria-label="$t('Show how many guests are going')"
                                    />
                                    <span class="fe-create-event__switch-text">{{ $t('Show how many guests are going') }}</span>
                                </div>
                            </div>
                        </el-form-item>
                    </div>
                </div>

                <div class="fe-create-event__card">
                    <button
                        type="button"
                        class="fe-create-event__row"
                        :aria-expanded="expanded.description"
                        @click="expanded.description = !expanded.description"
                    >
                        <el-icon class="fe-create-event__row-icon" aria-hidden="true"><Document/></el-icon>

                        <span class="fe-create-event__row-text">
                            <span class="fe-create-event__row-title">{{ $t('Add Description') }}</span>
                            <span class="fe-create-event__row-hint">{{ $t('What should guests know before they come?') }}</span>
                        </span>

                        <el-icon
                            class="fe-create-event__row-chevron"
                            :class="{ 'fe-create-event__row-chevron--open': expanded.description }"
                            aria-hidden="true"
                        >
                            <ArrowRight/>
                        </el-icon>
                    </button>

                    <div v-show="expanded.description" class="fe-create-event__row-body">
                        <el-input
                            v-model="event.description"
                            type="textarea"
                            :rows="6"
                            :aria-label="$t('Add Description')"
                        />
                    </div>
                </div>

                <div class="fe-create-event__card">
                    <button
                        type="button"
                        class="fe-create-event__row"
                        :aria-expanded="expanded.post"
                        @click="expanded.post = !expanded.post"
                    >
                        <el-icon class="fe-create-event__row-icon" aria-hidden="true"><ChatLineSquare/></el-icon>

                        <span class="fe-create-event__row-text">
                            <span class="fe-create-event__row-title">{{ $t('Feed post') }}</span>
                            <span class="fe-create-event__row-hint">{{ $t('What members see in the space feed, above the event.') }}</span>
                        </span>

                        <el-icon
                            class="fe-create-event__row-chevron"
                            :class="{ 'fe-create-event__row-chevron--open': expanded.post }"
                            aria-hidden="true"
                        >
                            <ArrowRight/>
                        </el-icon>
                    </button>

                    <div v-show="expanded.post" class="fe-create-event__row-body">
                        <el-form-item
                            v-if="postTitlePref"
                            :label="postTitlePref === 'required' ? $t('Post title *') : $t('Post title')"
                            label-position="top"
                        >
                            <el-input v-model="post.title" maxlength="192"/>
                        </el-form-item>

                        <el-form-item :label="$t('Post message *')" label-position="top">
                            <el-input v-model="post.message" type="textarea" :rows="4"/>
                        </el-form-item>

                        <div v-if="canSchedule" class="fe-create-event__post-schedule">
                            <button
                                v-if="post.scheduled_at"
                                type="button"
                                class="fe-create-event__post-schedule-info"
                                @click="openSchedule"
                            >
                                <span>{{ scheduleLabel }}</span>
                                <el-icon aria-hidden="true"><EditPen/></el-icon>
                            </button>

                            <el-button v-else class="fcom_secondary_button" @click="openSchedule">
                                <el-icon aria-hidden="true"><Calendar/></el-icon>
                                <span>{{ $t('Schedule post') }}</span>
                            </el-button>
                        </div>
                    </div>
                </div>

                <p v-if="error" class="fe-create-event__error" role="alert">{{ error }}</p>

                <el-button
                    class="fcom_primary_button fe-create-event__submit"
                    native-type="submit"
                    :loading="saving"
                    :disabled="saving || uploading"
                >
                    {{ $t('Create Event') }}
                </el-button>
            </div>
        </form>

        <!-- fcom's own schedule modal classes, so it matches the feed composer's. -->
        <el-dialog
            v-model="scheduleOpen"
            class="fe-schedule-dialog"
            modal-class="fcom_common_dialog"
            width="500px"
            append-to-body
            :close-on-click-modal="false"
        >
            <template #header>
                <div class="dialog_header">
                    <div class="dialog_left schedule_header_left">
                        <h4>{{ $t('Schedule Post') }}</h4>
                        <p>{{ $t('Choose a date and time in the future when you want your post to be published') }}</p>
                    </div>
                </div>
            </template>

            <el-form label-position="top" class="fcom_schedule_form">
                <el-form-item :label="$t('Date')">
                    <el-date-picker
                        v-model="schedule.date"
                        type="date"
                        format="MMM D, YYYY"
                        value-format="YYYY-MM-DD"
                        :placeholder="$t('Select Date')"
                        :disabled-date="isPastDay"
                        @change="schedule.time = ''"
                    />
                </el-form-item>

                <el-form-item :label="$t('Time')">
                    <el-select v-model="schedule.time" :disabled="!schedule.date" :placeholder="$t('Select Time')">
                        <template #prefix>
                            <el-icon aria-hidden="true"><Clock/></el-icon>
                        </template>
                        <el-option v-for="time in scheduleTimes" :key="time.value" :label="time.label" :value="time.value"/>
                    </el-select>
                </el-form-item>
            </el-form>

            <p class="fe-schedule-dialog__help">{{ $t("The date and time is based on your site's Timezone") }}</p>

            <template #footer>
                <div class="dialog_footer">
                    <el-button class="fcom_secondary_button" @click="removeSchedule">{{ $t('Remove Schedule') }}</el-button>
                    <el-button
                        class="fcom_primary_button"
                        :disabled="!schedule.date || !schedule.time"
                        @click="applySchedule"
                    >
                        {{ $t('Select Schedule') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import dayjs from 'dayjs';
import { ArrowLeft, ArrowRight, Calendar, ChatLineSquare, Clock, Delete, Document, EditPen, LocationInformation, Picture, User } from '@element-plus/icons-vue';
import { communityApi, errorMessage, t, timezoneOffset } from '../utils';

export default {
    name: 'CreateEventPage',
    components: { ArrowLeft, ArrowRight, Calendar, ChatLineSquare, Clock, Delete, Document, EditPen, LocationInformation, Picture, User },
    props: {
        spaceSlug: { type: String, required: true },
        router: { type: Object, required: true },
    },
    data() {
        const start = dayjs().add(1, 'hour').startOf('hour');
        const end = start.add(1, 'hour');
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        const zones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];

        return {
            // Built timezone options once per visit
            timezones: Object.freeze((zones.includes(timezone) ? zones : [timezone, ...zones]).map((zone) => ({
                value: zone,
                label: `${zone} (${timezoneOffset(zone)})`,
            }))),
            post: { title: '', message: '', scheduled_at: '' },
            event: {
                title: '',
                description: '',
                start_date: start.format('YYYY-MM-DD'),
                start_time: start.format('HH:mm'),
                end_date: end.format('YYYY-MM-DD'),
                end_time: end.format('HH:mm'),
                timezone,
                venue_type: 'offline',
                venue: '',
                address: '',
                online_meeting_url: '',
                cover_url: '',
                entry_type: '',
                limit_seats: false,
                max_guests: 50,
                show_guest_count: 'yes',
            },
            // The feed post is required, so it starts open.
            expanded: { location: false, registration: false, description: false, post: true },
            uploading: false,
            saving: false,
            error: '',
            scheduleOpen: false,
            schedule: { date: '', time: '' },
        };
    },
    computed: {
        // fcom-pro schedules only for community or space moderators; anyone else's post publishes at once.
        canSchedule() {
            const vars = window.fluentComAdmin || {};
            const permissions = vars.permissions || {};
            const space = ((vars.auth || {}).spaces || {})[this.spaceSlug] || {};

            return !!vars.pro && !!(
                permissions.community_admin
                || permissions.community_moderator
                || (space.permissions || {}).community_moderator
            );
        },
        // 10-minute slots; fcom-pro rejects anything 30 minutes out or less, so today starts past that.
        scheduleTimes() {
            if (!this.schedule.date) {
                return [];
            }

            const day = dayjs(this.schedule.date);
            let slot = day.startOf('day');

            if (day.isSame(dayjs(), 'day')) {
                const earliest = dayjs().add(31, 'minute');
                slot = earliest.add((10 - (earliest.minute() % 10)) % 10, 'minute').startOf('minute');
            }

            const times = [];

            while (slot.isSame(day, 'day')) {
                times.push({ value: slot.format('HH:mm'), label: slot.format('h:mm A') });
                slot = slot.add(10, 'minute');
            }

            return times;
        },
        scheduleLabel() {
            const at = dayjs(this.post.scheduled_at);

            return t('Scheduled for %1$s at %2$s', at.format('MMM D, YYYY'), at.format('h:mm A'));
        },
        // fcom's own post title setting: '' (off), 'optional' or 'required'.
        postTitlePref() {
            const features = (window.fluentComAdmin && window.fluentComAdmin.features) || {};
            return features.has_post_title || '';
        },
        bounds() {
            return [
                {
                    label: this.$t('Start'),
                    dateKey: 'start_date',
                    timeKey: 'start_time',
                    dateLabel: this.$t('Start date'),
                    timeLabel: this.$t('Start time'),
                },
                {
                    label: this.$t('End'),
                    dateKey: 'end_date',
                    timeKey: 'end_time',
                    dateLabel: this.$t('End date'),
                    timeLabel: this.$t('End time'),
                    disabledDate: (date) => dayjs(date).isBefore(this.event.start_date, 'day'),
                },
            ];
        },
        entryTypes() {
            return [
                { value: 'rsvp', label: this.$t('RSVP'), hint: this.$t('Logged-in members say whether they are going.') },
                { value: 'open', label: this.$t('Open registration'), hint: this.$t('Anyone who can see the event registers with a name and email.') },
                { value: 'none', label: this.$t('No registration'), hint: this.$t('An announcement only. Nobody registers.') },
            ];
        },
    },
    mounted() {
        this.$refs.titleInput.focus();
    },
    methods: {
        isPastDay(date) {
            return dayjs(date).isBefore(dayjs(), 'day');
        },
        openSchedule() {
            const at = this.post.scheduled_at ? dayjs(this.post.scheduled_at) : null;

            this.schedule = at ? { date: at.format('YYYY-MM-DD'), time: at.format('HH:mm') } : { date: '', time: '' };
            this.scheduleOpen = true;
        },
        applySchedule() {
            this.post.scheduled_at = `${this.schedule.date} ${this.schedule.time}:00`;
            this.scheduleOpen = false;
        },
        removeSchedule() {
            this.post.scheduled_at = '';
            this.scheduleOpen = false;
        },
        syncEndDate() {
            if (dayjs(this.event.end_date).isBefore(this.event.start_date)) {
                this.event.end_date = this.event.start_date;
            }
        },
        goBack() {
            this.router.push({ name: 'fe_space_events', params: { space: this.spaceSlug } });
        },
        async uploadCover(changeEvent) {
            const file = changeEvent.target.files[0];
            changeEvent.target.value = '';

            if (!file) {
                return;
            }

            const data = new FormData();
            data.append('file', file);

            this.uploading = true;
            this.error = '';

            try {
                this.event.cover_url = (await communityApi.post('feeds/media-upload', data)).media.url;
            } catch (error) {
                this.error = errorMessage(error);
            } finally {
                this.uploading = false;
            }
        },
        // Opens every collapsed section that still has a required field empty.
        missingRequired() {
            const event = this.event;
            const missing = {
                location: event.venue_type === 'online' && !event.online_meeting_url,
                registration: !event.entry_type,
                post: (this.postTitlePref === 'required' && !this.post.title.trim()) || !this.post.message.trim(),
            };

            Object.keys(missing).forEach((section) => {
                if (missing[section]) {
                    this.expanded[section] = true;
                }
            });

            return !event.title.trim() || Object.values(missing).some(Boolean);
        },
        async submit() {
            if (this.missingRequired()) {
                this.error = t('Please fill in the required fields.');
                return;
            }

            const event = this.event;

            this.saving = true;
            this.error = '';

            try {
                const response = await communityApi.post('feeds', {
                    title: this.post.title,
                    message: this.post.message,
                    space: this.spaceSlug,
                    content_type: 'event',
                    scheduled_at: this.post.scheduled_at || undefined,
                    event: {
                        title: event.title,
                        description: event.description,
                        start_at: `${event.start_date} ${event.start_time}:00`,
                        end_at: `${event.end_date} ${event.end_time}:00`,
                        timezone: event.timezone,
                        venue_type: event.venue_type,
                        venue: event.venue,
                        address: event.address,
                        online_meeting_url: event.online_meeting_url,
                        cover_url: event.cover_url,
                        entry_type: event.entry_type,
                        max_guests: event.limit_seats ? event.max_guests : -1,
                        show_guest_count: event.show_guest_count,
                    },
                });

                if (response.feed && response.feed.status === 'published') {
                    this.router.push({ name: 'single_feed', params: { feed_slug: response.feed.slug } });
                } else {
                    this.goBack();
                }
            } catch (error) {
                this.error = errorMessage(error);
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
