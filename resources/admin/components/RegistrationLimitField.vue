<template>
    <el-form-item :label="label" label-position="top" :for="id">
        <div class="fe-registration-limit">
            <el-select
                :id="id"
                :model-value="type"
                class="fe-registration-limit__type"
                @update:model-value="setType"
            >
                <el-option v-for="option in types" :key="option.value" :label="option.label" :value="option.value" />
            </el-select>

            <template v-if="isRelative">
                <el-input-number
                    :model-value="value"
                    class="fe-registration-limit__value"
                    align="left"
                    :min="0"
                    step-strictly
                    :controls="false"
                    :aria-label="$t('Amount')"
                    @update:model-value="setValue"
                />

                <el-select
                    :model-value="unit"
                    class="fe-registration-limit__unit"
                    :aria-label="$t('Unit')"
                    @update:model-value="setUnit"
                >
                    <el-option v-for="option in units" :key="option.value" :label="option.label" :value="option.value" />
                </el-select>
            </template>

            <el-date-picker
                v-else-if="type === 'fixed'"
                :model-value="at"
                class="fe-registration-limit__date"
                popper-class="fe-date-popper"
                type="datetime"
                :format="`${dateFormat} ${timeFormat}`"
                value-format="YYYY-MM-DD HH:mm:ss"
                :placeholder="$t('Pick a date and time')"
                :aria-label="$t('Date and time')"
                @update:model-value="setAt"
            />
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
        // {type, value, unit, at}; null is the first type. A fixed `at` is on the event's clock.
        modelValue: {
            type: Object,
            default: null
        },
        kind: {
            type: String,
            required: true,
            validator: kind => ['opens', 'closes'].includes(kind)
        },
        // The event's start and end on its own clock, 'YYYY-MM-DD HH:mm'.
        start: {
            type: String,
            required: true
        },
        end: {
            type: String,
            required: true
        },
        dateFormat: {
            type: String,
            default: 'MMM D, YYYY'
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
            value: this.modelValue?.value ?? null,
            unit: this.modelValue?.unit || (this.kind === 'opens' ? 'days' : 'hours'),
            at: this.modelValue?.at || null
        };
    },
    computed: {
        isOpens() {
            return this.kind === 'opens';
        },
        label() {
            return this.isOpens ? this.$t('Registration opens') : this.$t('Registration closes');
        },
        types() {
            return this.isOpens
                ? [
                    { value: 'immediately', label: this.$t('Opens immediately') },
                    { value: 'start', label: this.$t('Before the event starts') },
                    { value: 'fixed', label: this.$t('On a fixed date') }
                ]
                : [
                    { value: 'event_end', label: this.$t('Until the event ends') },
                    { value: 'start', label: this.$t('Before the event starts') },
                    { value: 'end', label: this.$t('Before the event ends') },
                    { value: 'fixed', label: this.$t('On a fixed date') }
                ];
        },
        type() {
            return this.modelValue?.type || this.types[0].value;
        },
        isRelative() {
            return ['start', 'end'].includes(this.type);
        },
        units() {
            return [
                { value: 'minutes', label: this.$t('Minutes') },
                { value: 'hours', label: this.$t('Hours') },
                { value: 'days', label: this.$t('Days') }
            ];
        },
        hint() {
            if (this.type === 'immediately') {
                return this.$t('Opens as soon as the event is published');
            }

            if (this.type === 'event_end') {
                return this.$t('Stays open until the event ends');
            }

            if (this.type === 'fixed') {
                return this.$t('Stays on this date if the event moves');
            }

            if (this.value === null || this.value === undefined) {
                return '';
            }

            // Plain wall-clock arithmetic, as the server counts it; the browser's own DST must not shift the hours.
            const moment = dayjs.utc(this.type === 'end' ? this.end : this.start).subtract(this.value, this.unit);

            return `${this.isOpens ? this.$t('Opens') : this.$t('Closes')} ${moment.format(`ddd, MMM D, YYYY · ${this.timeFormat}`)}`;
        }
    },
    watch: {
        // The edit form loads its event after this mounts.
        modelValue(limit) {
            if (!limit) {
                return;
            }

            this.value = limit.value ?? this.value;
            this.unit = limit.unit || this.unit;
            this.at = limit.at || this.at;
        }
    },
    methods: {
        emit(type) {
            const relative = ['start', 'end'].includes(type);

            this.$emit('update:modelValue', {
                type,
                value: relative ? this.value : null,
                unit: relative ? this.unit : null,
                at: type === 'fixed' ? this.at : null
            });
        },
        setType(type) {
            if (['start', 'end'].includes(type) && (this.value === null || this.value === undefined)) {
                this.value = 1;
            }

            this.emit(type);
        },
        setValue(value) {
            this.value = value;
            this.emit(this.type);
        },
        setUnit(unit) {
            this.unit = unit;
            this.emit(this.type);
        },
        setAt(at) {
            this.at = at;
            this.emit(this.type);
        }
    }
};
</script>
