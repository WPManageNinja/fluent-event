<template>
    <div class="fe-settings-page">
        <div class="fe-settings-page__header">
            <h1 class="fe-settings-page__title">{{ $t('General') }}</h1>
            <p class="fe-settings-page__subtitle">
                {{ $t('General plugin settings will live here.') }}
            </p>
        </div>

        <div class="fe-settings-page__content">
            <el-empty :description="$t('Nothing to configure yet.')" />
        </div>
    </div>
</template>

<script type="text/javascript">
    /**
     * Placeholder — the four v1 general settings land in chunk 21.
     * See .claude/docs/phase/v1/screens/settings.md
     */
    export default {
        name: 'SettingsGeneral',
        mounted() {
            this.$changeTitle(this.$t('General Settings'));
        },
    };
</script>
