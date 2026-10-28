<template>
    <section class="fe-settings-page">
        <div class="fe-settings-page__header">
            <h1 class="fe-settings-page__title">{{ $t('Guest Emails') }}</h1>
        </div>

        <Skeleton v-if="loading" type="general_settings" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchSettings" />

        <el-form v-else class="fe-form" label-position="top" @submit.prevent="saveSettings">
            <section class="fe-form__section">
                <div class="fe-form__section-rail">
                    <h2 class="fe-form__section-title">{{ $t('Registration') }}</h2>
                    <p class="fe-form__section-desc">{{ $t('Sent right after a guest registers, is waitlisted or cancels.') }}</p>
                </div>

                <div class="fe-form__section-fields">
                    <div class="fe-form__toggle">
                        <div class="fe-form__toggle-text">
                            <span class="fe-form__toggle-title">{{ $t('Confirmation email') }}</span>
                            <span class="fe-form__hint">
                                {{ confirmation.enabled
                                    ? $t('Email guests a confirmation when they register for an event.')
                                    : $t('Guests get no email when they register.') }}
                            </span>
                        </div>

                        <el-switch
                            v-model="confirmation.enabled"
                            :disabled="!$userCan('write_settings')"
                            :aria-label="$t('Send a confirmation email when a guest registers')"
                        />
                    </div>

                    <div class="fe-form__toggle">
                        <div class="fe-form__toggle-text">
                            <span class="fe-form__toggle-title">{{ $t('Cancellation email') }}</span>
                            <span class="fe-form__hint">
                                {{ cancellation.enabled
                                    ? $t('Email guests when they cancel or an admin cancels their registration.')
                                    : $t('Guests get no email when their registration is cancelled.') }}
                            </span>
                        </div>

                        <el-switch
                            v-model="cancellation.enabled"
                            :disabled="!$userCan('write_settings')"
                            :aria-label="$t('Send an email when a registration is cancelled')"
                        />
                    </div>

                    <div class="fe-form__toggle">
                        <div class="fe-form__toggle-text">
                            <span class="fe-form__toggle-title">{{ $t('Waitlist email') }}</span>
                            <span class="fe-form__hint">
                                {{ waitlist.enabled
                                    ? $t('Email guests when they join the waitlist of a full event or an admin moves them to it.')
                                    : $t('Guests get no email when they are waitlisted.') }}
                            </span>
                        </div>

                        <el-switch
                            v-model="waitlist.enabled"
                            :disabled="!$userCan('write_settings')"
                            :aria-label="$t('Send an email when a guest is waitlisted')"
                        />
                    </div>
                </div>
            </section>

            <section class="fe-form__section">
                <div class="fe-form__section-rail">
                    <h2 class="fe-form__section-title">{{ $t('Reminders') }}</h2>
                    <p class="fe-form__section-desc">{{ $t('Sent to registered guests before the event starts.') }}</p>
                </div>

                <div class="fe-form__section-fields">
                    <div class="fe-form__toggle">
                        <div class="fe-form__toggle-text">
                            <span class="fe-form__toggle-title">{{ $t('Reminder emails') }}</span>
                            <span class="fe-form__hint">
                                {{ reminder.enabled
                                    ? $t('Email guests a reminder before an event starts.')
                                    : $t('No guest will get a reminder before an event starts.') }}
                            </span>
                        </div>

                        <el-switch
                            v-model="reminder.enabled"
                            :disabled="!$userCan('write_settings')"
                            :aria-label="$t('Send reminder emails to guests')"
                        />
                    </div>

                    <div v-if="reminder.enabled" class="fe-form__panel">
                        <div class="fe-form__panel-head">
                            <span class="fe-form__panel-title">{{ $t('Schedule') }}</span>
                            <span class="fe-form__count">{{ $t('%d of %d', times.length, maxTimes) }}</span>
                        </div>

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
                                <span>{{ $t('Add reminder') }}</span>
                            </el-button>
                        </div>
                    </div>
                </div>
            </section>

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
                waitlist: { enabled: true },
                reminder: { enabled: true },
                times: [{ value: 24, unit: 'hours' }],
                maxTimes: 5,
            };
        },
        computed: {
            timeUnits() {
                return [
                    { value: 'minutes', label: this.$t('Minutes before') },
                    { value: 'hours', label: this.$t('Hours before') },
                    { value: 'days', label: this.$t('Days before') },
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
                this.waitlist = { enabled: settings.registration_waitlist.enabled };
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
                        registration_waitlist: {
                            enabled: this.waitlist.enabled,
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
