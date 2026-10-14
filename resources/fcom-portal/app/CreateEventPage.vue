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

        <div class="fe-create-event__form">
            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Cover image') }}</h2>
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
                        :label="$t('Event name')"
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
                    <div v-if="!editorReady" v-loading="true" class="fe-create-event__editor-loading"></div>

                    <WpEditor
                        v-else
                        ref="descriptionEditor"
                        v-model="event.description"
                        :height="280"
                        quick-tags
                        toolbar="formatselect,bold,italic,bullist,numlist,link,blockquote,alignleft,aligncenter,alignright,underline,strikethrough,forecolor,removeformat,outdent,indent,undo,redo"
                        aria-labelledby="fe-create-event-description-label"
                    >
                        <template #tools>
                            <el-button class="fe-create-event__editor-tool" :loading="imageUploading" @click="$refs.imageInput.click()">
                                <el-icon v-if="!imageUploading" aria-hidden="true"><Picture/></el-icon>
                                <span>{{ $t('Add image') }}</span>
                            </el-button>

                            <input
                                ref="imageInput"
                                class="fe-create-event__file"
                                type="file"
                                accept="image/*"
                                tabindex="-1"
                                aria-hidden="true"
                                @change="uploadImage"
                            />
                        </template>
                    </WpEditor>
                </div>
            </section>

            <section class="fe-create-event__section">
                <div>
                    <h2 class="fe-create-event__section-title">{{ $t('Date and time') }}</h2>
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
                    <h2 class="fe-create-event__section-title">{{ $t('Venue type') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('A venue for in-person events, a link for online ones.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <SegmentedSwitch
                        v-model="event.venue_type"
                        class="fe-create-event__venue-switch"
                        :options="venueTypes"
                        :label="$t('Venue type')"
                        show-labels
                    />

                    <el-form-item
                        v-if="event.venue_type === 'online'"
                        :label="$t('Meeting link')"
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
                        <el-form-item :label="$t('Venue name')" label-position="top" for="fe-create-event-venue">
                            <el-input
                                id="fe-create-event-venue"
                                v-model="event.venue"
                                maxlength="255"
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
                    <h2 class="fe-create-event__section-title">{{ $t('Registration') }}</h2>
                    <p class="fe-create-event__section-desc">{{ $t('How guests join and what they see.') }}</p>
                </div>

                <div class="fe-create-event__section-fields">
                    <el-form-item :label="$t('Entry type')" label-position="top">
                        <el-radio-group
                            v-model="event.entry_type"
                            class="fe-create-event__choices"
                            :aria-label="$t('Entry type')"
                        >
                            <el-radio v-for="option in entryTypes" :key="option.value" :value="option.value" border>
                                <span class="fe-create-event__choice-title">{{ option.label }}</span>
                                <span class="fe-create-event__hint">{{ option.hint }}</span>
                            </el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <div v-if="event.entry_type === 'rsvp'" class="fe-create-event__switch-row">
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
                </div>
            </section>
        </div>

        <!-- After fcom's composer, which the shell renders below this page, so Create comes last. -->
        <Teleport to="#fe-create-event-actions">
            <div class="fe-create-event__actions">
                <el-button
                    class="fcom_primary_button"
                    :loading="saving"
                    :disabled="saving || uploading || imageUploading"
                    @click="submit"
                >
                    {{ $t('Create Event') }}
                </el-button>
            </div>
        </Teleport>
    </div>
</template>

<script>
import dayjs from 'dayjs';
import { ElNotification } from 'element-plus';
import { ArrowLeft, Delete, MapLocation, Picture, VideoCamera } from '@element-plus/icons-vue';
import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
import WpEditor from '@/components/WpEditor.vue';
import { communityApi, errorMessage, eventsApi, timeFormat, timezoneOffset } from '../utils';

// Module scope, not data(): leaving the page and coming back reuses the first load instead of re-running WordPress's scripts.
let wpEditorLoad = null;

export default {
    name: 'CreateEventPage',
    components: { ArrowLeft, Delete, Picture, SegmentedSwitch, WpEditor },
    props: {
        spaceSlug: { type: String, required: true },
        router: { type: Object, required: true },
        onChange: { type: Function, required: true },
        submitPost: { type: Function, required: true },
    },
    data() {
        const start = dayjs().add(1, 'hour').startOf('hour');
        const end = start.add(1, 'hour');
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        const zones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];

        return {
            timeFormat: timeFormat(),
            // Built timezone options once per visit
            timezones: Object.freeze((zones.includes(timezone) ? zones : [timezone, ...zones]).map((zone) => ({
                value: zone,
                label: `${zone} (${timezoneOffset(zone)})`,
            }))),
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
                entry_type: 'rsvp',
                show_guest_count: 'yes',
            },
            uploading: false,
            imageUploading: false,
            editorReady: false,
            saving: false,
        };
    },
    computed: {
        // The `event` field of the composer's POST feeds; FeedHandler builds the event from it.
        payload() {
            const event = this.event;

            return {
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
                show_guest_count: event.show_guest_count,
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
                { value: 'offline', label: this.$t('In person'), icon: MapLocation },
                { value: 'online', label: this.$t('Online'), icon: VideoCamera },
            ];
        },
        entryTypes() {
            return [
                { value: 'rsvp', label: this.$t('RSVP'), hint: this.$t('Logged-in members say whether they are going.') },
                { value: 'open', label: this.$t('Open'), hint: this.$t('Nobody registers. Anyone who can see the event sees all of it, meeting link included.') },
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
        this.$refs.titleInput.focus();

        this.loadWpEditor().then(() => {
            this.editorReady = true;
        });
    },
    methods: {
        // The WordPress editor is heavy, so it loads only when this page opens. A failed
        // load resolves anyway; the editor component then falls back to a plain textarea.
        loadWpEditor() {
            if (!wpEditorLoad) {
                wpEditorLoad = eventsApi.get('community/editor-assets')
                    .then(({ html }) => this.insertMarkup(html))
                    .catch(() => {});
            }

            return wpEditorLoad;
        },
        // Markup first, so the link dialog exists when wplink initialises; then each script in order.
        // Anything already on the page by id, jQuery say, is left alone.
        async insertMarkup(html) {
            const template = document.createElement('template');
            template.innerHTML = html;

            const nodes = [...template.content.children].filter((node) => !node.id || !document.getElementById(node.id));

            nodes.filter((node) => node.tagName !== 'SCRIPT').forEach((node) => document.body.appendChild(node));

            for (const node of nodes.filter((node) => node.tagName === 'SCRIPT')) {
                const script = document.createElement('script');

                [...node.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
                script.text = node.text;

                await new Promise((resolve) => {
                    script.onload = script.onerror = resolve;
                    document.body.appendChild(script);

                    if (!script.src) {
                        resolve();
                    }
                });
            }
        },
        submit() {
            this.submitPost((posting) => { this.saving = posting; });
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
            this.router.push({ name: 'fe_space_events', params: { space: this.spaceSlug } });
        },
        async uploadImage(changeEvent) {
            const file = changeEvent.target.files[0];
            changeEvent.target.value = '';

            if (!file) {
                return;
            }

            const data = new FormData();
            data.append('file', file);

            this.imageUploading = true;

            try {
                const image = document.createElement('img');
                image.src = (await communityApi.post('feeds/media-upload', data)).media.url;
                image.alt = '';

                this.$refs.descriptionEditor.insertContent(image.outerHTML);
            } catch (error) {
                this.showError(error);
            } finally {
                this.imageUploading = false;
            }
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

            try {
                this.event.cover_url = (await communityApi.post('feeds/media-upload', data)).media.url;
            } catch (error) {
                this.showError(error);
            } finally {
                this.uploading = false;
            }
        },
    },
};
</script>
