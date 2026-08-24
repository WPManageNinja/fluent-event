<template>
    <div class="fe-settings-page">
        <div class="fe-settings-page__header">
            <h1 class="fe-settings-page__title">{{ $t('General') }}</h1>
        </div>

        <Skeleton v-if="loading" type="general_settings" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchSettings" />

        <el-form v-else class="fe-form" label-position="top" @submit.prevent="save">
            <el-form-item :label="$t('Default events view')">
                <el-radio-group v-model="settings.default_events_view" :disabled="!canWrite">
                    <el-radio value="timeline">{{ $t('Timeline') }}</el-radio>
                    <el-radio value="calendar">{{ $t('Calendar') }}</el-radio>
                </el-radio-group>
            </el-form-item>

            <el-form-item>
                <template #label>
                    <span class="fe-form__label-row">
                        {{ $t('Date format') }}

                        <button
                            type="button"
                            class="fe-form__label-info"
                            :aria-label="$t('How date formats work')"
                            :title="$t('How date formats work')"
                            @click="openDateTimeHelp('date')"
                        >
                            <el-icon><InfoFilled /></el-icon>
                        </button>
                    </span>
                </template>

                <el-input
                    v-model="settings.date_format"
                    placeholder="F j, Y"
                    :disabled="!canWrite"
                />

                <span class="fe-form__hint">
                    {{ $t('Leave empty to follow the WordPress date format.') }}
                </span>
            </el-form-item>

            <el-form-item>
                <template #label>
                    <span class="fe-form__label-row">
                        {{ $t('Time format') }}

                        <button
                            type="button"
                            class="fe-form__label-info"
                            :aria-label="$t('How time formats work')"
                            :title="$t('How time formats work')"
                            @click="openDateTimeHelp('time')"
                        >
                            <el-icon><InfoFilled /></el-icon>
                        </button>
                    </span>
                </template>

                <el-input
                    v-model="settings.time_format"
                    placeholder="g:i a"
                    :disabled="!canWrite"
                />

                <span class="fe-form__hint">
                    {{ $t('Leave empty to follow the WordPress time format.') }}
                </span>
            </el-form-item>

            <el-form-item :label="$t('Enable dark mode')">
                <el-switch
                    v-model="settings.dark_mode"
                    active-value="yes"
                    inactive-value="no"
                    :disabled="!canWrite"
                    :aria-label="$t('Enable dark mode')"
                />

                <span class="fe-form__hint">
                    {{ $t('Turning this off removes the dark theme toggle and keeps everyone on the light theme.') }}
                </span>
            </el-form-item>

            <el-form-item :label="$t('Remove data on uninstall')">
                <el-switch
                    v-model="settings.remove_data_on_uninstall"
                    active-value="yes"
                    inactive-value="no"
                    :disabled="!canWrite"
                    :aria-label="$t('Remove data on uninstall')"
                />

                <span class="fe-form__hint">
                    {{ $t('When on, deleting the plugin permanently removes all of its data — calendars, events, guests and settings. This cannot be undone.') }}
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

        <DateTimeHelperModal v-model="dateTimeHelpOpen" :type="dateTimeHelpType" />
    </div>
</template>

<script type="text/javascript">
    import { InfoFilled } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import DateTimeHelperModal from '@/components/DateTimeHelperModal.vue';

    export default {
        name: 'SettingsGeneral',
        components: {
            InfoFilled,
            ErrorState,
            Skeleton,
            DateTimeHelperModal,
        },
        data() {
            return {
                loading: false,
                saving: false,
                dateTimeHelpOpen: false,
                dateTimeHelpType: 'both',
                error: '',
                settings: {
                    default_events_view: 'timeline',
                    date_format: '',
                    time_format: '',
                    dark_mode: 'yes',
                    remove_data_on_uninstall: 'no',
                },
            };
        },
        computed: {
            canWrite() {
                return this.$userCan('write_settings');
            },
        },
        methods: {
            openDateTimeHelp(type) {
                this.dateTimeHelpType = type;
                this.dateTimeHelpOpen = true;
            },
            fetchSettings() {
                if (this.loading) return;
                this.loading = true;
                this.$get('settings/general')
                    .then(response => {
                        this.error = '';
                        this.settings = response.settings;
                    })
                    .catch(error => {
                        this.error = error?.message || '';
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
            save() {
                if (this.saving) return;
                this.saving = true;
                this.$post('settings/general', this.settings)
                    .then(response => {
                        this.settings = response.settings;
                        this.$notifySuccess(response.message);
                    })
                    .catch(error => {
                        this.$handleError(error);
                    })
                    .finally(() => {
                        this.saving = false;
                    });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('General Settings'));
            this.fetchSettings();
        },
    };
</script>
