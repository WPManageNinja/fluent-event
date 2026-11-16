<template>
    <div class="fe-start-preview">
        <span v-if="value === 'relative'" class="fe-start-preview__status">{{ $t('Starts in 2 days') }}</span>
        <span v-else class="fe-start-preview__countdown">
            <span v-for="unit in units" :key="unit.label" class="fe-start-preview__unit">
                <span class="fe-start-preview__value">{{ unit.value }}</span>
                <span class="fe-start-preview__label">{{ unit.label }}</span>
            </span>
        </span>
    </div>
</template>

<script>
// How a Start Time Display choice looks on the event page, drawn for the event forms' help.
export default {
    name: 'StartDisplayPreview',
    props: {
        value: { type: String, required: true },
    },
    computed: {
        units() {
            return [
                { value: '02', label: this.$t('Days') },
                { value: '04', label: this.$t('Hours') },
                { value: '58', label: this.$t('Minutes') },
                { value: '50', label: this.$t('Seconds') },
            ];
        },
    },
};
</script>
