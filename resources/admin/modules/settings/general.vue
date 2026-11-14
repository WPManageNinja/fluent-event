<template>
    <div>
        <section class="fe-settings-page">
            <div class="fe-settings-page__header">
                <h1 class="fe-settings-page__title">{{ $t('General') }}</h1>
            </div>

            <Skeleton v-if="generalLoading" type="general_settings" />

            <ErrorState v-else-if="generalError" :message="generalError" @retry="fetchGeneral" />

            <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveGeneral">
                <el-form-item :label="$t('Default Events List View')">
                    <el-radio-group
                        v-model="general.default_events_view"
                        class="fe-view-picker"
                        :disabled="!$userCan('write_settings')"
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
                        :disabled="!$userCan('write_settings')"
                    >
                        {{ $t('Disable View Change Option') }}
                    </el-checkbox>

                    <span class="fe-form__hint">
                        {{ general.disable_event_view_switch === 'yes'
                            ? $t('The selected view is used for everyone. They cannot switch to a different view.')
                            : $t('Users can switch to the view they prefer.') }}
                    </span>
                </el-form-item>

                <div v-if="$userCan('write_settings')" class="fe-form__actions">
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

        <!-- Pages and the event slug change site URLs, so the server keeps them to site admins. -->
        <section v-if="isSiteAdmin" class="fe-settings-page">
            <div class="fe-settings-page__header">
                <h2 class="fe-settings-page__title">{{ $t('Public Pages') }}</h2>
            </div>

            <Skeleton v-if="generalLoading" type="general_settings" />

            <ErrorState v-else-if="generalError" :message="generalError" @retry="fetchGeneral" />

            <el-form v-else class="fe-form" label-position="top" @submit.prevent="savePages">
                <el-form-item v-for="field in pageFields" :key="field.key" :label="field.label">
                    <div class="fe-page-picker">
                        <!-- An unset page is 0, which the select would print; null shows the placeholder. -->
                        <el-select
                            :model-value="general[field.key] || null"
                            class="fe-page-picker__select"
                            :placeholder="$t('Select a page')"
                            :aria-label="field.label"
                            filterable
                            clearable
                            remote
                            :remote-method="search => searchPages(search, field.key)"
                            :loading="pagesSearching"
                            @update:model-value="value => pickPage(field.key, value)"
                            @visible-change="visible => visible && searchPages('', field.key)"
                        >
                            <el-option
                                v-for="option in pageOptionsFor(field.key)"
                                :key="option.id"
                                :label="option.title"
                                :value="option.id"
                            />
                        </el-select>

                        <el-button
                            class="fe-page-picker__add"
                            :aria-label="$t('Create a new page')"
                            :title="$t('Create a new page')"
                            @click="openAddPage(field.key)"
                        >
                            <el-icon><Plus /></el-icon>
                        </el-button>
                    </div>

                    <span v-if="general[field.key]" class="fe-form__hint fe-page-picker__help">
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

                <el-form-item :label="$t('Event Slug')">
                    <div class="fe-page-picker">
                        <el-input v-model="general.event_slug" :aria-label="$t('Event Slug')" />
                    </div>

                    <span class="fe-form__hint">
                        {{ $t('Each event opens at /%s/event-name/.', general.event_slug || 'event') }}
                    </span>
                </el-form-item>

                <div class="fe-form__actions">
                    <el-button
                        type="primary"
                        native-type="submit"
                        :loading="pagesSaving"
                        :disabled="pagesSaving"
                    >
                        {{ $t('Save Settings') }}
                    </el-button>
                </div>
            </el-form>

            <el-dialog
                v-model="addPageVisible"
                :title="$t('Add Page')"
                width="500px"
                append-to-body
                @opened="$refs.newPageTitle.focus()"
                @closed="newPageTitle = ''"
            >
                <el-form label-position="top" @submit.prevent="createPage">
                    <el-form-item :label="$t('Page Title')">
                        <el-input
                            ref="newPageTitle"
                            v-model="newPageTitle"
                            :placeholder="$t('Enter Page Title')"
                        />
                    </el-form-item>
                </el-form>

                <template #footer>
                    <el-button :disabled="pageCreating" @click="addPageVisible = false">
                        {{ $t('Cancel') }}
                    </el-button>

                    <el-button
                        type="primary"
                        :loading="pageCreating"
                        :disabled="pageCreating"
                        @click="createPage"
                    >
                        {{ $t('Add') }}
                    </el-button>
                </template>
            </el-dialog>
        </section>

        <section class="fe-settings-page">
            <div class="fe-settings-page__header">
                <h2 class="fe-settings-page__title">{{ $t('Email') }}</h2>
            </div>

            <Skeleton v-if="emailLoading" type="general_settings" />

            <ErrorState v-else-if="emailError" :message="emailError" @retry="fetchEmail" />

            <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveEmail">
                <div class="fe-form__pair">
                    <el-form-item :label="$t('From Name')" required>
                        <el-input
                            v-model="email.from_name"
                            :placeholder="$t('e.g. your business name')"
                            :disabled="!$userCan('write_settings')"
                        />
                    </el-form-item>

                    <el-form-item :label="$t('From Email Address')" required>
                        <el-input
                            v-model="email.from_email"
                            type="email"
                            placeholder="events@example.com"
                            :disabled="!$userCan('write_settings')"
                        />
                    </el-form-item>
                </div>

                <div class="fe-form__pair">
                    <el-form-item :label="$t('Reply-To Name (Optional)')">
                        <el-input v-model="email.reply_to_name" :disabled="!$userCan('write_settings')" />
                    </el-form-item>

                    <el-form-item>
                        <template #label>
                            <span class="fe-form__label-row">
                                {{ $t('Reply-To Email (Optional)') }}

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
                            :disabled="!$userCan('write_settings')"
                        />
                    </el-form-item>
                </div>

                <el-form-item>
                    <template #label>
                        <span id="fe-email-footer-label">{{ $t('Email Footer for Event Related Emails') }}</span>
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

                <div v-if="$userCan('write_settings')" class="fe-form__actions">
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
                pagesSaving: false,
                generalError: '',
                general: {
                    default_events_view: 'timeline',
                    disable_event_view_switch: 'no',
                    events_page_id: 0,
                    event_slug: 'event',
                },
                selectedPages: {
                    events_page_id: null,
                },
                pageOptions: [],
                pagesSearching: false,
                addPageVisible: false,
                addPageKey: '',
                newPageTitle: '',
                pageCreating: false,
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
            isSiteAdmin() {
                return !!window.fluentEventAdmin.me?.is_admin;
            },
            viewOptions() {
                return [
                    { value: 'cards', label: this.$t('Cards') },
                    { value: 'timeline', label: this.$t('Timeline') },
                    { value: 'calendar', label: this.$t('Calendar') },
                ];
            },
            pageFields() {
                return [
                    { key: 'events_page_id', label: this.$t('All Events Page'), shortcode: 'fluent_events' },
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
            searchPages(search, key) {
                this.$requestServer({
                    path: 'settings/pages',
                    payload: { search, key },
                    loadingState: 'pagesSearching',
                    errorState: null,
                    errorNotify: true,
                    onSuccess: response => {
                        this.pageOptions = response.pages;
                    },
                });
            },
            // Preview and the missing-block warning follow the pick, before it is saved.
            pickPage(key, id) {
                this.general[key] = id || 0;
                this.selectedPages[key] = this.pageOptionsFor(key).find(option => option.id === id) || null;
            },
            openAddPage(key) {
                this.addPageKey = key;
                this.addPageVisible = true;
            },
            createPage() {
                if (this.pageCreating) {
                    return;
                }

                const key = this.addPageKey;

                this.$requestServer({
                    method: 'post',
                    path: 'settings/create-page',
                    payload: { key, title: this.newPageTitle },
                    loadingState: 'pageCreating',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.general[key] = response.settings[key];
                        this.selectedPages = response.pages;
                        this.addPageVisible = false;
                    },
                });
            },
            copyShortcode(shortcode) {
                navigator.clipboard.writeText(`[${shortcode}]`)
                    .then(() => this.$notifySuccess(this.$t('Shortcode copied.')))
                    .catch(() => this.$notifyError(this.$t('The shortcode could not be copied.')));
            },
            saveGeneral() {
                this.saveGeneralFields(['default_events_view', 'disable_event_view_switch'], 'generalSaving');
            },
            savePages() {
                this.saveGeneralFields(['events_page_id', 'event_slug'], 'pagesSaving');
            },
            // Each box saves only its own fields and takes back only those, so the other box keeps unsaved edits.
            saveGeneralFields(keys, loadingState) {
                this.$requestServer({
                    method: 'post',
                    path: 'settings/general',
                    payload: Object.fromEntries(keys.map(key => [key, this.general[key]])),
                    loadingState,
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        keys.forEach(key => {
                            this.general[key] = response.settings[key];
                        });
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
