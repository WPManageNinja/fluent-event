<template>
    <div>
        <section class="fe-settings-page">
            <div class="fe-settings-page__header">
                <h1 class="fe-settings-page__title">{{ $t('General') }}</h1>
            </div>

            <Skeleton v-if="generalLoading" type="general_settings" />

            <ErrorState v-else-if="generalError" :message="generalError" @retry="fetchGeneral" />

            <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveGeneral">
                <el-form-item :label="$t('Default events view')">
                    <el-radio-group v-model="general.default_events_view" :disabled="!canWrite">
                        <el-radio value="cards">{{ $t('Cards') }}</el-radio>
                        <el-radio value="timeline">{{ $t('Timeline') }}</el-radio>
                        <el-radio value="calendar">{{ $t('Calendar') }}</el-radio>
                    </el-radio-group>
                </el-form-item>

                <div v-if="canWrite" class="fe-form__actions">
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
                            :disabled="!canWrite"
                        />
                    </el-form-item>

                    <el-form-item :label="$t('From email address')" required>
                        <el-input
                            v-model="email.from_email"
                            type="email"
                            placeholder="events@example.com"
                            :disabled="!canWrite"
                        />
                    </el-form-item>
                </div>

                <div class="fe-form__pair">
                    <el-form-item :label="$t('Reply-to name (optional)')">
                        <el-input v-model="email.reply_to_name" :disabled="!canWrite" />
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
                            :disabled="!canWrite"
                        />
                    </el-form-item>
                </div>

                <el-form-item>
                    <template #label>
                        <span id="fe-email-footer-label">{{ $t('Email footer for event related emails') }}</span>
                    </template>

                    <WpEditor v-model="email.email_footer" aria-labelledby="fe-email-footer-label" />

                    <span class="fe-form__hint">
                        {{ $t('Shown at the bottom of every event email. You may include your business name, address, or why the recipient got this email.') }}
                    </span>
                </el-form-item>

                <el-form-item :label="$t('Send guest reminders before the event')">
                    <el-input-number
                        v-model="email.reminder_hours"
                        :min="1"
                        :max="720"
                        :disabled="!canWrite"
                        :aria-label="$t('Hours before the event')"
                    />

                    <span class="fe-form__suffix">{{ $t('hours') }}</span>

                    <span class="fe-form__hint">
                        {{ $t('Guests get a reminder email this many hours before an event starts.') }}
                    </span>
                </el-form-item>

                <div v-if="canWrite" class="fe-form__actions">
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
    import { InfoFilled } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import WpEditor from '@/components/WpEditor.vue';

    export default {
        name: 'SettingsGeneral',
        components: {
            InfoFilled,
            ErrorState,
            Skeleton,
            WpEditor,
        },
        data() {
            return {
                generalLoading: false,
                generalSaving: false,
                generalError: '',
                general: {
                    default_events_view: 'timeline',
                },
                emailLoading: false,
                emailSaving: false,
                emailError: '',
                email: {
                    from_name: '',
                    from_email: '',
                    reply_to_name: '',
                    reply_to_email: '',
                    email_footer: '',
                    reminder_hours: 24,
                },
            };
        },
        computed: {
            canWrite() {
                return this.$userCan('write_settings');
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
                    },
                });
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
