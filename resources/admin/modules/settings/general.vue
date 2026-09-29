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
                    <el-radio value="cards">{{ $t('Cards') }}</el-radio>
                    <el-radio value="timeline">{{ $t('Timeline') }}</el-radio>
                    <el-radio value="calendar">{{ $t('Calendar') }}</el-radio>
                </el-radio-group>
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
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';

    export default {
        name: 'SettingsGeneral',
        components: {
            ErrorState,
            Skeleton,
        },
        data() {
            return {
                loading: false,
                saving: false,
                error: '',
                settings: {
                    default_events_view: 'timeline',
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
                    path: 'settings/general',
                    onSuccess: response => {
                        this.settings = response.settings;
                    },
                });
            },
            save() {
                this.$requestServer({
                    method: 'post',
                    path: 'settings/general',
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
            this.$changeTitle(this.$t('General Settings'));
            this.fetchSettings();
        },
    };
</script>
