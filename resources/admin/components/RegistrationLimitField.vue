<template>
    <el-form-item :label="label" label-position="top" :for="id">
        <div class="fe-registration-limit">
            <el-input-number
                :id="id"
                :model-value="value"
                class="fe-registration-limit__value"
                align="left"
                :min="0"
                step-strictly
                :controls="false"
                :placeholder="$t('Not set')"
                @update:model-value="setValue"
            />

            <el-select
                :model-value="unit"
                class="fe-registration-limit__unit"
                :aria-label="$t('Time before the event starts')"
                @update:model-value="setUnit"
            >
                <el-option v-for="option in units" :key="option.value" :label="option.label" :value="option.value" />
            </el-select>
        </div>

        <span v-if="hint" class="fe-registration-limit__hint">{{ hint }}</span>
    </el-form-item>
</template>

<script type="text/babel">
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';

dayjs.extend(utc);

export default {
    name: 'RegistrationLimitField',
    props: {
        modelValue: {
            type: Object,
            default: null
        },
        kind: {
            type: String,
            required: true,
            validator: kind => ['opens', 'closes'].includes(kind)
        },
        // The event's start on its own clock, 'YYYY-MM-DD HH:mm'.
        start: {
            type: String,
            required: true
        },
        timeFormat: {
            type: String,
            default: 'h:mm A'
        },
        id: {
            type: String,
            required: true
        }
    },
    emits: ['update:modelValue'],
    data() {
        return {
            unit: this.modelValue?.unit || (this.kind === 'opens' ? 'days' : 'hours')
        };
    },
    computed: {
        isOpens() {
            return this.kind === 'opens';
        },
        label() {
            return this.isOpens ? this.$t('Opens') : this.$t('Closes');
        },
        units() {
            return [
                { value: 'minutes', label: this.$t('Minutes before') },
                { value: 'hours', label: this.$t('Hours before') },
                { value: 'days', label: this.$t('Days before') }
            ];
        },
        value() {
            return this.modelValue ? this.modelValue.value : null;
        },
        hint() {
            if (this.value === null || this.value === undefined) {
                return '';
            }

            // Plain wall-clock arithmetic, as the server counts it; the browser's own DST must not shift the hours.
            return dayjs.utc(this.start).subtract(this.value, this.unit).format(`ddd, MMM D, YYYY · ${this.timeFormat}`);
        }
    },
    watch: {
        // The edit form loads its event after this mounts.
        'modelValue.unit'(unit) {
            if (unit) {
                this.unit = unit;
            }
        }
    },
    methods: {
        update(value, unit) {
            this.$emit('update:modelValue', value === null || value === undefined ? null : { value, unit });
        },
        setValue(value) {
            this.update(value, this.unit);
        },
        setUnit(unit) {
            this.unit = unit;
            this.update(this.value, unit);
        }
    }
};
</script>
