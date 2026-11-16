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
import { countdownParts, padPart, startCountdown } from '@/utils/countdown';

// A sample start 2 days, 4 hours, 58 minutes and 50 seconds away.
const SAMPLE_MS = (((2 * 24 + 4) * 60 + 58) * 60 + 50) * 1000;

// How a Start Time Display choice looks on the event page, drawn for the event forms' help.
export default {
    name: 'StartDisplayPreview',
    props: {
        value: { type: String, required: true },
    },
    data() {
        return {
            parts: countdownParts(SAMPLE_MS, 0),
        };
    },
    computed: {
        units() {
            const labels = { days: this.$t('Days'), hours: this.$t('Hours'), minutes: this.$t('Minutes'), seconds: this.$t('Seconds') };

            return Object.keys(labels).map((key) => ({ value: padPart(this.parts[key]), label: labels[key] }));
        },
    },
    // Ticks like the event page's, to show what the countdown does.
    mounted() {
        if (this.value === 'countdown') {
            this.stopCountdown = startCountdown(Date.now() + SAMPLE_MS, (parts) => {
                this.parts = parts;
            });
        }
    },
    beforeUnmount() {
        if (this.stopCountdown) {
            this.stopCountdown();
        }
    },
};
</script>
