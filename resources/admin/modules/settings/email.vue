<template>
    <div class="fe-settings-page">
        <div class="fe-settings-page__header">
            <h1 class="fe-settings-page__title">{{ $t('Email') }}</h1>
        </div>

        <Skeleton v-if="loading" type="general_settings" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchSettings" />

        <el-form v-else class="fe-form" label-position="top" @submit.prevent="save">
            <div class="fe-form__pair">
                <el-form-item :label="$t('From name')" required>
                    <el-input
                        v-model="settings.from_name"
                        :placeholder="$t('e.g. your business name')"
                        :disabled="!canWrite"
                    />
                </el-form-item>

                <el-form-item :label="$t('From email address')" required>
                    <el-input
                        v-model="settings.from_email"
                        type="email"
                        placeholder="events@example.com"
                        :disabled="!canWrite"
                    />
                </el-form-item>
            </div>

            <div class="fe-form__pair">
                <el-form-item :label="$t('Reply-to name (optional)')">
                    <el-input v-model="settings.reply_to_name" :disabled="!canWrite" />
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
                        v-model="settings.reply_to_email"
                        type="email"
                        :disabled="!canWrite"
                    />
                </el-form-item>
            </div>

            <el-form-item :label="$t('Email footer for event related emails')">
                <WpEditor v-model="settings.email_footer" />

                <span class="fe-form__hint">
                    {{ $t('Shown at the bottom of every event email. You may include your business name, address, or why the recipient got this email.') }}
                </span>
            </el-form-item>

            <el-form-item :label="$t('Send guest reminders before the event')">
                <el-input-number
                    v-model="settings.reminder_hours"
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
                    :loading="saving"
                    :disabled="saving"
                >
                    {{ $t('Save Settings') }}
                </el-button>
            </div>
        </el-form>
    </div>
</template>

<script type="text/javascript">
    import { InfoFilled } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import WpEditor from '@/components/WpEditor.vue';

    export default {
        name: 'SettingsEmail',
        components: {
            InfoFilled,
            ErrorState,
            Skeleton,
            WpEditor,
        },
        data() {
            return {
                loading: false,
                saving: false,
                error: '',
                settings: {
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
            fetchSettings() {
                this.$requestServer({
                    path: 'settings/email',
                    onSuccess: response => {
                        this.settings = response.settings;
                    },
                });
            },
            save() {
                this.$requestServer({
                    method: 'post',
                    path: 'settings/email',
                    payload: this.settings,
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.settings = response.settings;
                    },
                });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Email Settings'));
            this.fetchSettings();
        },
    };
</script>
