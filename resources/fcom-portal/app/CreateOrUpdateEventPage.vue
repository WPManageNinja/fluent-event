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

            <h1 class="fe-create-event__heading">{{ eventSlug ? $t('Edit Event') : $t('Create Event') }}</h1>
        </div>

        <el-skeleton v-if="loading" :rows="8" animated role="status" :aria-label="$t('Loading event')"/>

        <el-empty v-else-if="!editable" :description="$t('This event is not available.')"/>

        <el-alert v-if="editable && eventSlug && loadedEvent.feed_url" type="info" :closable="false" show-icon class="fe-create-event__notice">
            {{ $t('To change the post text or its space, edit the post itself.') }}
            <a class="fe-create-event__notice-link" :href="loadedEvent.feed_url">{{ $t('View post') }}</a>
        </el-alert>

        <div v-if="editable" class="fe-create-event__form">
            <section v-if="!eventSlug" class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Where do you want to post this event?') }}</h2>
                </div>

                <div class="fe-create-event__section-fields">
                    <el-radio-group
                        v-model="placement"
                        class="fe-create-event__choices"
                        :aria-label="$t('Where do you want to post this event?')"
                        @change="onPlacement"
                    >
                        <el-radio value="space" border>
                            <span class="fe-create-event__choice-title">{{ $t('In a space') }}</span>
                            <span class="fe-create-event__hint">{{ $t('Only members of the space can see it.') }}</span>
                        </el-radio>
                        <!-- The disabled card says why; the tooltip says where to turn it on. -->
                        <el-tooltip
                            :disabled="globalPosts"
                            :content="$t('To turn it on: FluentCommunity → Portal Settings → General → Global Post Status → Enable Global Posts Status Form.')"
                            placement="top"
                        >
                            <el-radio value="global" border :disabled="!globalPosts">
                                <span class="fe-create-event__choice-title">{{ $t('Community-wide') }}</span>
                                <span class="fe-create-event__hint">
                                    {{ globalPosts ? $t('Posted on your profile, outside any space. Everyone in the community can see it.') : $t('Global posts are turned off in FluentCommunity settings.') }}
                                </span>
                            </el-radio>
                        </el-tooltip>
                    </el-radio-group>

                    <!-- Chosen here rather than in fcom's composer, whose own picker stays hidden. -->
                    <el-form-item
                        v-if="placement === 'space'"
                        class="fe-create-event__space"
                        :label="$t('Space')"
                        label-position="top"
                        for="fe-create-event-space"
                        required
                    >
                        <el-select
                            id="fe-create-event-space"
                            v-model="space"
                            filterable
                            :placeholder="$t('Select a space')"
                            @change="onSpace"
                        >
                            <el-option v-for="option in spaces" :key="option.slug" :label="option.title" :value="option.slug">
                                <span v-if="option.emoji" class="fe-create-event__space-emoji" aria-hidden="true">{{ option.emoji }}</span>
                                {{ option.title }}
                            </el-option>
                            <template #empty>
                                <p class="fe-create-event__space-empty">{{ $t('There are no spaces you can post in.') }}</p>
                            </template>
                        </el-select>
                    </el-form-item>
                </div>
            </section>

            <section v-if="!eventSlug" class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Feed Post') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('What members see in the feed, above the event.') }}</p>
                </div>

                <!-- fcom's composer runs on fcom's Vue; the page shell teleports it in here. -->
                <div id="fe-create-event-composer" class="fe-create-event__section-fields"></div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Cover Image') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('Shown at the top of the event page and on event cards.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
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
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Basics') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('What the event is called.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <el-form-item
                        :label="$t('Event Name')"
                        label-position="top"
                        for="fe-create-event-title"
                        required
                    >
                        <el-input
                            id="fe-create-event-title"
                            ref="titleInput"
                            v-model="event.title"
                            maxlength="255"
                            :placeholder="$t('e.g. Friday Q&A')"
                        />
                    </el-form-item>
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 id="fe-create-event-description-label" class="fe-create-event__section-title">{{ $t('Description') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('Optional. Shown on the event page.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <!-- fcom's own markdown editor lands here from the route shell; see createEventShell. -->
                    <div id="fe-create-event-description" class="fe-create-event__description" role="group" aria-labelledby="fe-create-event-description-label"></div>
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Date and Time') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('When the event starts and ends.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <div class="fe-create-event__window">
                        <div v-for="bound in bounds" :key="bound.dateKey" class="fe-create-event__window-row">
                            <span class="fe-create-event__window-label">{{ bound.label }}</span>

                            <el-date-picker
                                v-model="event[bound.dateKey]"
                                class="fe-create-event__date"
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
                                class="fe-create-event__time"
                                popper-class="fe-create-event__time-pop"
                                :format="timeFormat"
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
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title fe-create-event__section-title--info">
                        {{ $t('Start Time Display') }}
                        <button
                            type="button"
                            class="fe-create-event__info"
                            :aria-label="$t('What the two choices look like')"
                            @click="startDisplayHelp = true"
                        >
                            <el-icon><QuestionFilled /></el-icon>
                        </button>
                    </h2>
                    <p class="fe-create-event__section-desc">{{ $t('How the event page shows the time until the start.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <el-radio-group
                        v-model="event.start_display"
                        class="fe-create-event__choices"
                        :aria-label="$t('Start Time Display')"
                    >
                        <el-radio v-for="option in startDisplays" :key="option.value" :value="option.value" border>
                            <span class="fe-create-event__choice-title">{{ option.label }}</span>
                            <span class="fe-create-event__hint">{{ option.hint }}</span>
                        </el-radio>
                    </el-radio-group>

                    <el-dialog v-model="startDisplayHelp" class="fe-start-display-help" :title="$t('Start Time Display')" width="560px" append-to-body destroy-on-close>
                        <figure v-for="option in startDisplays" :key="option.value" class="fe-start-display-help__item">
                            <figcaption class="fe-start-display-help__caption">
                                <span class="fe-start-display-help__title">{{ option.label }}</span>
                                <span class="fe-start-display-help__text">{{ option.details }}</span>
                            </figcaption>
                            <StartDisplayPreview
                                :value="option.value"
                                role="img"
                                :aria-label="$t('%s on the event page', option.label)"
                            />
                        </figure>
                    </el-dialog>
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Venue Type') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('A venue for in-person events, a link for online ones.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <SegmentedSwitch
                        v-model="event.venue_type"
                        class="fe-create-event__venue-switch"
                        :options="venueTypes"
                        :label="$t('Venue Type')"
                        show-labels
                    />

                    <el-form-item
                        v-if="event.venue_type === 'online'"
                        :label="$t('Meeting Link')"
                        label-position="top"
                        for="fe-create-event-meeting-link"
                        required
                    >
                        <el-input
                            id="fe-create-event-meeting-link"
                            v-model="event.online_meeting_url"
                            type="url"
                            placeholder="https://meet.example.com/standup"
                        />
                        <span class="fe-create-event__hint">{{ $t('Only http and https links are accepted.') }}</span>
                    </el-form-item>

                    <template v-else>
                        <el-form-item :label="$t('Venue Name')" label-position="top" for="fe-create-event-venue">
                            <el-input
                                id="fe-create-event-venue"
                                v-model="event.venue"
                                :maxlength="venueMaxLength"
                                :placeholder="$t('e.g. Kingsway Hall')"
                            />
                        </el-form-item>

                        <el-form-item :label="$t('Address')" label-position="top" for="fe-create-event-address">
                            <el-input
                                id="fe-create-event-address"
                                v-model="event.address"
                                type="textarea"
                                :rows="2"
                                maxlength="500"
                                :placeholder="$t('Street, city, country')"
                            />
                        </el-form-item>
                    </template>
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Entry Type') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('How guests join and what they see.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <el-radio-group
                        v-model="event.entry_type"
                        class="fe-create-event__choices"
                        :aria-label="$t('Entry Type')"
                    >
                        <el-radio v-for="option in entryTypes" :key="option.value" :value="option.value" border>
                            <span class="fe-create-event__choice-title">{{ option.label }}</span>
                            <span class="fe-create-event__hint">{{ option.hint }}</span>
                        </el-radio>
                    </el-radio-group>

                </div>
            </section>

            <section v-if="['rsvp', 'open'].includes(event.entry_type)" class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Guest control') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('Seats and the guest count guests see.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <el-form-item :label="$t('Capacity')" label-position="top" for="fe-create-event-capacity">
                        <el-input-number
                            id="fe-create-event-capacity"
                            v-model="event.capacity"
                            class="fe-create-event__capacity"
                            align="left"
                            :min="1"
                            step-strictly
                            :controls="false"
                            :placeholder="$t('Unlimited')"
                        />
                        <span class="fe-create-event__hint">{{ $t('How many guests can be going at once. Leave empty for no limit.') }}</span>
                    </el-form-item>

                    <div class="fe-create-event__switch-row">
                        <label class="fe-create-event__switch-text" for="fe-create-event-show-guest-count">
                            {{ $t('Show how many guests are going') }}
                        </label>

                        <el-switch
                            id="fe-create-event-show-guest-count"
                            v-model="event.show_guest_count"
                            active-value="yes"
                            inactive-value="no"
                        />
                    </div>

                    <div v-if="event.capacity" class="fe-create-event__switch-row">
                        <label class="fe-create-event__switch-text" for="fe-create-event-show-spots-left">
                            {{ $t('Show how many spots are left') }}
                        </label>

                        <el-switch
                            id="fe-create-event-show-spots-left"
                            v-model="event.show_spots_left"
                            active-value="yes"
                            inactive-value="no"
                        />
                    </div>
                </div>
            </section>
        </div>

        <div v-if="editable" class="fe-create-event__actions">
            <el-button
                class="fcom_primary_button"
                :loading="saving"
                :disabled="saving || uploading"
                @click="submit"
            >
                {{ eventSlug ? $t('Update Event') : $t('Create Event') }}
            </el-button>
        </div>
    </div>
</template>

<script>
import dayjs from 'dayjs';
import { ElNotification } from 'element-plus';
import { ArrowLeft, Delete, MapLocation, Picture, QuestionFilled, VideoCamera } from '@element-plus/icons-vue';
import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
import StartDisplayPreview from '@/components/StartDisplayPreview.vue';
import { communityApi, dateFormat, errorMessage, eventsApi, portalVars, setPageTitle, timeFormat, timezoneOffset, uploadMedia } from '../utils';

export default {
    name: 'CreateOrUpdateEventPage',
    components: { ArrowLeft, Delete, Picture, QuestionFilled, SegmentedSwitch, StartDisplayPreview },
    props: {
        router: { type: Object, required: true },
        // The edit route passes these instead of the create-only props below.
        eventSlug: { type: String, default: '' },
        onLoaded: { type: Function, default: () => {} },
        // The markdown stays in the shell, beside fcom's editor.
        getDescription: { type: Function, default: () => '' },
        onChange: { type: Function, default: () => {} },
        submitPost: { type: Function, default: () => {} },
        // The composer belongs to fcom's Vue; it follows this choice through onPlacement.
        globalPosts: { type: Boolean, default: true },
        onPlacement: { type: Function, default: () => {} },
        // The discussion spaces the member may post in, and the one the link named (?space=).
        spaces: { type: Array, default: () => [] },
        initialSpace: { type: String, default: '' },
        onSpace: { type: Function, default: () => {} },
        // "Clone Event" (?clone=<slug>): a new event, prefilled from this one and its post.
        cloneSlug: { type: String, default: '' },
    },
    data() {
        const start = dayjs().add(1, 'hour').startOf('hour');
        const end = start.add(1, 'hour');
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        const zones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];

        return {
            placement: 'space',
            space: this.spaces.some((option) => option.slug === this.initialSpace) ? this.initialSpace : '',
            dateFormat: dateFormat(),
            timeFormat: timeFormat(),
            // Built timezone options once per visit
            timezones: Object.freeze((zones.includes(timezone) ? zones : [timezone, ...zones]).map((zone) => ({
                value: zone,
                label: `${zone} (${timezoneOffset(zone)})`,
            }))),
            event: {
                title: '',
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
                entry_type: 'rsvp',
                show_guest_count: 'yes',
                capacity: null,
                show_spots_left: 'no',
                start_display: 'relative',
            },
            uploading: false,
            saving: false,
            startDisplayHelp: false,
            loading: !!(this.eventSlug || this.cloneSlug),
            loadedEvent: null,
        };
    },
    computed: {
        // A clone has no eventSlug, so without the loading check its blank form would render under the skeleton.
        editable() {
            return !this.loading && (!this.eventSlug || !!this.loadedEvent);
        },
        venueMaxLength() {
            return portalVars().venue_max_length;
        },
        // The `event` field of the composer's POST feeds (FeedHandler builds the event from it), or an edit's PATCH.
        payload() {
            const event = this.event;

            return {
                title: event.title,
                start_at: `${event.start_date} ${event.start_time}:00`,
                end_at: `${event.end_date} ${event.end_time}:00`,
                timezone: event.timezone,
                venue_type: event.venue_type,
                venue: event.venue,
                address: event.address,
                online_meeting_url: event.online_meeting_url,
                cover_url: event.cover_url,
                entry_type: event.entry_type,
                show_guest_count: event.show_guest_count,
                capacity: event.capacity || 0,
                show_spots_left: event.show_spots_left,
                start_display: event.start_display,
            };
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
        venueTypes() {
            return [
                { value: 'offline', label: this.$t('In Person'), icon: MapLocation },
                { value: 'online', label: this.$t('Online'), icon: VideoCamera },
            ];
        },
        startDisplays() {
            return [
                {
                    value: 'relative',
                    label: this.$t('Time Left'),
                    hint: this.$t('A short line, like "Starts in 3 days".'),
                    details: this.$t('A short line with the time left, worked out when the page opens. It reads in the largest unit, like "Starts in 2 days" or "Starts in 5 hours".'),
                },
                {
                    value: 'countdown',
                    label: this.$t('Countdown'),
                    hint: this.$t('Days, hours, minutes and seconds, ticking down to the start.'),
                    details: this.$t('Days, hours, minutes and seconds, ticking down every second while the page is open. Anything over a day stays in days, so six weeks reads 42 days.'),
                },
            ];
        },
        entryTypes() {
            return [
                { value: 'rsvp', label: this.$t('RSVP'), hint: this.$t('Logged-in members say whether they are going.') },
                { value: 'open', label: this.$t('Open'), hint: this.$t('Guests register with their name and email, no login needed.') },
                { value: 'none', label: this.$t('None'), hint: this.$t('Nobody registers. Anyone who can see the event sees all of it, meeting link included.') },
            ];
        },
    },
    watch: {
        payload: {
            immediate: true,
            handler(payload) {
                this.onChange(payload);
            },
        },
    },
    mounted() {
        setPageTitle(this.eventSlug ? this.$t('Edit Event') : this.$t('Create Event'));

        if (this.eventSlug || this.cloneSlug) {
            this.loadEvent();
            return;
        }

        this.$refs.titleInput.focus();
    },
    methods: {
        // Checked before fcom's composer is touched, since opening it moves the focus to its title; the server still validates.
        submit() {
            const error = this.validateEvent();

            if (error) {
                ElNotification({ type: 'error', title: this.$t('Error'), message: error });
                return;
            }

            if (this.eventSlug) {
                this.updateEvent();
                return;
            }

            this.submitPost((posting) => { this.saving = posting; });
        },
        // Mapped as the admin form's initForm() maps it.
        async loadEvent() {
            let event = null;
            let feed = null;

            try {
                ({ event } = await eventsApi.get('events/by-slug', { slug: this.eventSlug || this.cloneSlug }));

                if (this.cloneSlug && event.feed_slug) {
                    ({ feed } = await communityApi.get(`feeds/${event.feed_slug}/by-slug`, { context: 'edit' }));
                }

                const meta = event.meta || {};
                const start = dayjs(event.start_at_local);
                const end = dayjs(event.end_at_local);

                this.event = {
                    title: event.title || '',
                    start_date: start.format('YYYY-MM-DD'),
                    start_time: start.format('HH:mm'),
                    end_date: end.format('YYYY-MM-DD'),
                    end_time: end.format('HH:mm'),
                    timezone: event.timezone || this.event.timezone,
                    venue_type: event.venue_type || 'offline',
                    venue: meta.venue || '',
                    address: meta.address || '',
                    online_meeting_url: meta.online_meeting_url || '',
                    cover_url: meta.cover_url || '',
                    entry_type: meta.entry_type || '',
                    show_guest_count: meta.show_guest_count || 'yes',
                    capacity: meta.capacity || null,
                    show_spots_left: meta.show_spots_left || 'no',
                    start_display: meta.start_display || 'relative',
                };
                if (this.eventSlug) {
                    this.loadedEvent = event;
                }
            } catch (error) {
                this.showError(error);

                // A failed clone still leaves a blank create form, which needs its editors.
                if (this.eventSlug) {
                    return;
                }
            } finally {
                this.loading = false;
            }

            if (feed) {
                this.placeLike(feed);
            }

            // The shell mounts fcom's editor into the description spot, so the form must be rendered first.
            await this.$nextTick();
            this.onLoaded(event, feed);
            this.$refs.titleInput.focus();
        },
        // A clone posts where its source was posted, when the member may still post there.
        placeLike(feed) {
            const space = feed.space ? feed.space.slug : '';

            this.placement = !space && this.globalPosts ? 'global' : 'space';
            this.space = this.spaces.some((option) => option.slug === space) ? space : '';
            this.onPlacement(this.placement);
            this.onSpace(this.space);
        },
        async updateEvent() {
            this.saving = true;

            try {
                const response = await eventsApi.patch(`events/${this.loadedEvent.id}`, {
                    ...this.payload,
                    // Required by the PATCH but not on this page, so they go back as loaded.
                    calendar_id: this.loadedEvent.calendar_id,
                    status: this.loadedEvent.status,
                    description_md: this.getDescription(),
                });

                ElNotification({ type: 'success', message: response.message });
                this.router.back();
            } catch (error) {
                this.showError(error);
            } finally {
                this.saving = false;
            }
        },
        // Returns the first problem's message, or '' when the event can be posted.
        validateEvent() {
            if (!this.eventSlug && this.placement === 'space' && !this.space) {
                return this.$t('Choose a space for this event.');
            }

            if (!this.event.title.trim()) {
                return this.$t('Event title is required.');
            }

            if (!dayjs(this.payload.end_at).isAfter(this.payload.start_at)) {
                return this.$t('End must be after the start.');
            }

            if (this.event.venue_type === 'online' && !this.event.online_meeting_url.trim()) {
                return this.$t('An online event needs a meeting link.');
            }

            return '';
        },
        syncEndDate() {
            if (dayjs(this.event.end_date).isBefore(this.event.start_date)) {
                this.event.end_date = this.event.start_date;
            }
        },
        // The same error toast fcom raises through its own $handleError.
        showError(error) {
            ElNotification({ type: 'error', title: this.$t('Error'), message: errorMessage(error) });
        },
        goBack() {
            this.router.back();
        },
        async uploadCover(changeEvent) {
            const file = changeEvent.target.files[0];
            changeEvent.target.value = '';

            if (!file) {
                return;
            }

            this.uploading = true;

            try {
                this.event.cover_url = await uploadMedia(file);
            } catch (error) {
                this.showError(error);
            } finally {
                this.uploading = false;
            }
        },
    },
};
</script>
