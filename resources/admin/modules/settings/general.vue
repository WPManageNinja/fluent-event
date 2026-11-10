<template>
    <div>
        <section class="fe-settings-page">
            <div class="fe-settings-page__header">
                <h1 class="fe-settings-page__title">{{ $t('General') }}</h1>
            </div>

            <Skeleton v-if="generalLoading" type="general_settings" />

            <ErrorState v-else-if="generalError" :message="generalError" @retry="fetchGeneral" />

            <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveGeneral">
                <el-form-item :label="$t('Default events list view')">
                    <el-radio-group
                        v-model="general.default_events_view"
                        class="fe-view-picker"
                    >
                        <el-radio
                            v-for="option in viewOptions"
                            :key="option.value"
                            :value="option.value"
                            class="fe-view-picker__option"
                        >
                            <Icon :name="option.value" class="fe-view-picker__preview" />
                            <span class="fe-view-picker__label">{{ option.label }}</span>
                        </el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item>
                    <el-checkbox
                        v-model="general.disable_event_view_switch"
                        true-value="yes"
                        false-value="no"
                    >
                        {{ $t('Disable view change option') }}
                    </el-checkbox>

                    <span class="fe-form__hint">
                        {{ general.disable_event_view_switch === 'yes'
                            ? $t('The selected view is used for everyone. They cannot switch to a different view.')
                            : $t('Users can switch to the view they prefer.') }}
                    </span>
                </el-form-item>

                <el-form-item v-for="field in pageFields" :key="field.key" :label="field.label">
                    <div class="fe-page-picker">
                        <el-select
                            v-model="general[field.key]"
                            class="fe-page-picker__select"
                            :placeholder="$t('Select a page')"
                            :aria-label="field.label"
                            filterable
                            clearable
                            remote
                            :remote-method="searchPages"
                            :loading="pagesSearching"
                            @visible-change="visible => visible && searchPages('')"
                        >
                            <el-option
                                v-for="option in pageOptionsFor(field.key)"
                                :key="option.id"
                                :label="option.title"
                                :value="option.id"
                            />
                        </el-select>

                        <el-button
                            :aria-label="$t('Create a new page')"
                            :title="$t('Create a new page')"
                            :loading="pageCreating && creatingKey === field.key"
                            :disabled="pageCreating"
                            @click="createPage(field.key)"
                        >
                            <el-icon><Plus /></el-icon>
                        </el-button>
                    </div>

                    <span class="fe-form__hint fe-page-picker__help">
                        {{ $t('Add this shortcode to the page:') }}
                        <code class="fe-page-picker__code">[{{ field.shortcode }}]</code>
                        <el-button
                            link
                            class="fe-page-picker__copy"
                            :aria-label="$t('Copy shortcode')"
                            :title="$t('Copy shortcode')"
                            @click="copyShortcode(field.shortcode)"
                        >
                            <el-icon><DocumentCopy /></el-icon>
                        </el-button>
                        <a
                            v-if="selectedPages[field.key]"
                            :href="selectedPages[field.key].link"
                            target="_blank"
                            rel="noopener"
                        >{{ $t('Preview') }}</a>
                    </span>

                    <span
                        v-if="selectedPages[field.key] && !selectedPages[field.key].has_shortcode"
                        class="fe-form__hint fe-page-picker__warning"
                        role="status"
                    >
                        <el-icon aria-hidden="true"><WarningFilled /></el-icon>
                        {{ $t('This page doesn’t contain the shortcode or block yet.') }}
                    </span>
                </el-form-item>

                <div class="fe-form__actions">
                    <el-button
                        type="primary"
                        native-type="submit"
                        :loading="generalSaving"
                        :disabled="generalSaving"
                    >
                        {{ $t('Save Settings') }}
                    </el-button>
                </div>
            </el-form>
        </section>

        <section class="fe-settings-page">
            <div class="fe-settings-page__header">
                <h2 class="fe-settings-page__title">{{ $t('Email') }}</h2>
            </div>

            <Skeleton v-if="emailLoading" type="general_settings" />

            <ErrorState v-else-if="emailError" :message="emailError" @retry="fetchEmail" />

            <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveEmail">
                <div class="fe-form__pair">
                    <el-form-item :label="$t('From name')" required>
                        <el-input
                            v-model="email.from_name"
                            :placeholder="$t('e.g. your business name')"
                        />
                    </el-form-item>

                    <el-form-item :label="$t('From email address')" required>
                        <el-input
                            v-model="email.from_email"
                            type="email"
                            placeholder="events@example.com"
                        />
                    </el-form-item>
                </div>

                <div class="fe-form__pair">
                    <el-form-item :label="$t('Reply-to name (optional)')">
                        <el-input v-model="email.reply_to_name" />
                    </el-form-item>

                    <el-form-item>
                        <template #label>
                            <span class="fe-form__label-row">
                                {{ $t('Reply-to email (optional)') }}

                                <el-tooltip
                                    :content="$t('Replies to event emails go to this address instead of the from address.')"
                                    placement="top"
                                >
                                    <button
                                        type="button"
                                        class="fe-form__label-info"
                                        :aria-label="$t('What the reply-to email is for')"
                                    >
                                        <el-icon><InfoFilled /></el-icon>
                                    </button>
                                </el-tooltip>
                            </span>
                        </template>

                        <el-input
                            v-model="email.reply_to_email"
                            type="email"
                        />
                    </el-form-item>
                </div>

                <el-form-item>
                    <template #label>
                        <span id="fe-email-footer-label">{{ $t('Email footer for event related emails') }}</span>
                    </template>

                    <WpEditor
                        v-model="email.email_footer"
                        media-buttons
                        quick-tags
                        aria-labelledby="fe-email-footer-label"
                    />

                    <span class="fe-form__hint">
                        {{ $t('Shown at the bottom of every event email. You may include your business name, address, or why the recipient got this email.') }}
                    </span>
                </el-form-item>

                <div class="fe-form__actions">
                    <el-button
                        type="primary"
                        native-type="submit"
                        :loading="emailSaving"
                        :disabled="emailSaving"
                    >
                        {{ $t('Save Settings') }}
                    </el-button>
                </div>
            </el-form>
        </section>
    </div>
</template>

<script type="text/javascript">
    import { DocumentCopy, InfoFilled, Plus, WarningFilled } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import WpEditor from '@/components/WpEditor.vue';
    import Icon from '@/components/Icon.vue';

    export default {
        name: 'SettingsGeneral',
        components: {
            DocumentCopy,
            InfoFilled,
            Plus,
            WarningFilled,
            ErrorState,
            Skeleton,
            WpEditor,
            Icon,
        },
        data() {
            return {
                generalLoading: true,
                generalSaving: false,
                generalError: '',
                general: {
                    default_events_view: 'timeline',
                    disable_event_view_switch: 'no',
                    events_page_id: 0,
                    event_page_id: 0,
                },
                selectedPages: {
                    events_page_id: null,
                    event_page_id: null,
                },
                pageOptions: [],
                pagesSearching: false,
                pageCreating: false,
                creatingKey: '',
                emailLoading: true,
                emailSaving: false,
                emailError: '',
                email: {
                    from_name: '',
                    from_email: '',
                    reply_to_name: '',
                    reply_to_email: '',
                    email_footer: '',
                },
            };
        },
        computed: {
            viewOptions() {
                return [
                    { value: 'cards', label: this.$t('Cards') },
                    { value: 'timeline', label: this.$t('Timeline') },
                    { value: 'calendar', label: this.$t('Calendar') },
                ];
            },
            pageFields() {
                return [
                    { key: 'events_page_id', label: this.$t('Events page'), shortcode: 'fluent_events' },
                    { key: 'event_page_id', label: this.$t('Event details page'), shortcode: 'fluent_event' },
                ];
            },
        },
        methods: {
            fetchGeneral() {
                this.$requestServer({
                    path: 'settings/general',
                    loadingState: 'generalLoading',
                    errorState: 'generalError',
                    onSuccess: response => {
                        this.general = response.settings;
                        this.selectedPages = response.pages;
                    },
                });
            },
            // The search results, with the page already chosen kept in so the select can name it.
            pageOptionsFor(key) {
                const selected = this.selectedPages[key];

                return selected && !this.pageOptions.some(option => option.id === selected.id)
                    ? [selected, ...this.pageOptions]
                    : this.pageOptions;
            },
            searchPages(search) {
                this.$requestServer({
                    path: 'settings/pages',
                    payload: { search },
                    loadingState: 'pagesSearching',
                    errorState: null,
                    errorNotify: true,
                    onSuccess: response => {
                        this.pageOptions = response.pages;
                    },
                });
            },
            createPage(key) {
                this.creatingKey = key;
                this.$requestServer({
                    method: 'post',
                    path: 'settings/create-page',
                    payload: { key },
                    loadingState: 'pageCreating',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.general[key] = response.settings[key];
                        this.selectedPages = response.pages;
                    },
                });
            },
            copyShortcode(shortcode) {
                navigator.clipboard.writeText(`[${shortcode}]`)
                    .then(() => this.$notifySuccess(this.$t('Shortcode copied.')))
                    .catch(() => this.$notifyError(this.$t('The shortcode could not be copied.')));
            },
            saveGeneral() {
                this.$requestServer({
                    method: 'post',
                    path: 'settings/general',
                    payload: this.general,
                    loadingState: 'generalSaving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.general = response.settings;
                        this.selectedPages = response.pages;
                        window.fluentEventAdmin.general_settings = response.settings;
                    },
                });
            },
            fetchEmail() {
                this.$requestServer({
                    path: 'settings/email',
                    loadingState: 'emailLoading',
                    errorState: 'emailError',
                    onSuccess: response => {
                        this.email = response.settings;
                    },
                });
            },
            saveEmail() {
                this.$requestServer({
                    method: 'post',
                    path: 'settings/email',
                    payload: this.email,
                    loadingState: 'emailSaving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.email = response.settings;
                    },
                });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('General Settings'));
            this.fetchGeneral();
            this.fetchEmail();
        },
    };
</script>
