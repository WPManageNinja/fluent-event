<template>
    <section class="fe-settings-page">
        <div class="fe-settings-page__header">
            <h1 class="fe-settings-page__title">{{ $t('Guest Emails') }}</h1>
        </div>

        <Skeleton v-if="loading" type="general_settings" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchSettings" />

        <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveSettings">
            <el-form-item :label="$t('Registration confirmation')">
                <el-switch
                    v-model="confirmation.enabled"
                    :disabled="!$userCan('write_settings')"
                    :aria-label="$t('Send a confirmation email when a guest registers')"
                />

                <span class="fe-form__hint">
                    {{ confirmation.enabled
                        ? $t('Email guests a confirmation when they register for an event.')
                        : $t('Guests get no email when they register.') }}
                </span>
            </el-form-item>

            <el-form-item :label="$t('Registration cancellation')">
                <el-switch
                    v-model="cancellation.enabled"
                    :disabled="!$userCan('write_settings')"
                    :aria-label="$t('Send an email when a registration is cancelled')"
                />

                <span class="fe-form__hint">
                    {{ cancellation.enabled
                        ? $t('Email guests when they cancel or an admin cancels their registration.')
                        : $t('Guests get no email when their registration is cancelled.') }}
                </span>
            </el-form-item>

            <el-form-item :label="$t('Before Event Start Reminders')">
                <el-switch
                    v-model="reminder.enabled"
                    :disabled="!$userCan('write_settings')"
                    :aria-label="$t('Send reminder emails to guests')"
                />

                <span class="fe-form__hint">
                    {{ reminder.enabled
                        ? $t('Email guests a reminder before an event starts.')
                        : $t('No guest will get a reminder before an event starts.') }}
                </span>
            </el-form-item>

            <el-form-item v-if="reminder.enabled" :label="$t('Send guest reminders before the event')">
                <div class="fe-form__times">
                    <div v-for="(time, index) in times" :key="index" class="fe-form__time">
                        <el-input-number
                            v-model="time.value"
                            class="fe-form__number"
                            :controls="false"
                            :disabled="!$userCan('write_settings')"
                            :aria-label="$t('Reminder time')"
                        />

                        <el-select
                            v-model="time.unit"
                            class="fe-form__unit"
                            :disabled="!$userCan('write_settings')"
                            :aria-label="$t('Reminder unit')"
                        >
                            <el-option
                                v-for="unit in timeUnits"
                                :key="unit.value"
                                :label="unit.label"
                                :value="unit.value"
                            />
                        </el-select>

                        <el-button
                            v-if="$userCan('write_settings') && times.length > 1"
                            link
                            :aria-label="$t('Remove reminder')"
                            :title="$t('Remove reminder')"
                            @click="removeTime(index)"
                        >
                            <el-icon><Close /></el-icon>
                        </el-button>
                    </div>

                    <el-button v-if="$userCan('write_settings') && times.length < maxTimes" link @click="addTime">
                        <el-icon><Plus /></el-icon>
                        <span>{{ $t('Add Another Reminder') }}</span>
                    </el-button>
                </div>
            </el-form-item>

            <div v-if="$userCan('write_settings')" class="fe-form__actions">
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
    </section>
</template>

<script type="text/javascript">
    import { Close, Plus } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';

    export default {
        name: 'SettingsEmailNotifications',
        components: {
            Close,
            Plus,
            ErrorState,
            Skeleton,
        },
        data() {
            return {
                loading: false,
                saving: false,
                error: '',
                confirmation: { enabled: true },
                cancellation: { enabled: true },
                reminder: { enabled: true },
                times: [{ value: 24, unit: 'hours' }],
                maxTimes: 5,
            };
        },
        computed: {
            timeUnits() {
                return [
                    { value: 'minutes', label: this.$t('Minutes Before') },
                    { value: 'hours', label: this.$t('Hours Before') },
                    { value: 'days', label: this.$t('Days Before') },
                ];
            },
        },
        methods: {
            addTime() {
                this.times.push({ value: 15, unit: 'minutes' });
            },
            removeTime(index) {
                this.times.splice(index, 1);
            },
            setSettings(settings) {
                this.confirmation = { enabled: settings.registration_confirmation.enabled };
                this.cancellation = { enabled: settings.registration_cancellation.enabled };
                this.reminder = { enabled: settings.reminder_to_guest.enabled };
                this.times = settings.reminder_to_guest.times.map(time => ({ ...time }));
            },
            fetchSettings() {
                this.$requestServer({
                    path: 'settings/email-notifications',
                    loadingState: 'loading',
                    errorState: 'error',
                    onSuccess: response => {
                        this.setSettings(response.settings);
                    },
                });
            },
            saveSettings() {
                this.$requestServer({
                    method: 'post',
                    path: 'settings/email-notifications',
                    payload: {
                        registration_confirmation: {
                            enabled: this.confirmation.enabled,
                        },
                        registration_cancellation: {
                            enabled: this.cancellation.enabled,
                        },
                        reminder_to_guest: {
                            enabled: this.reminder.enabled,
                            times: this.times,
                        },
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.setSettings(response.settings);
                        window.fluentEventAdmin.email_notifications = response.settings;
                    },
                });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Email Notifications'));
            this.fetchSettings();
        },
    };
</script>
