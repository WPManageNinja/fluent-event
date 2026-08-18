<template>
    <el-dialog
        :model-value="modelValue"
        :title="title"
        width="560px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="fe-format-help">
            <p class="fe-format-help__intro">
                {{ $t('Formats use WordPress tokens — each letter below stands for a part of the date or time. Any other character appears as typed.') }}
            </p>

            <table class="fe-format-help__table">
                <thead>
                    <tr>
                        <th>{{ $t('Token') }}</th>
                        <th>{{ $t('Meaning') }}</th>
                        <th>{{ $t('Output') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="token in tokens" :key="token.token">
                        <td><code class="fe-format-help__token">{{ token.token }}</code></td>
                        <td>{{ token.label }}</td>
                        <td>{{ token.sample }}</td>
                    </tr>
                </tbody>
            </table>

            <h4 class="fe-format-help__section-title">{{ $t('Examples') }}</h4>

            <table class="fe-format-help__table">
                <tbody>
                    <tr v-for="example in examples" :key="example.format">
                        <td><code class="fe-format-help__token">{{ example.format }}</code></td>
                        <td>{{ example.sample }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </el-dialog>
</template>

<script type="text/javascript">
    export default {
        name: 'DateTimeHelperModal',
        props: {
            modelValue: {
                type: Boolean,
                default: false,
            },
            type: {
                type: String,
                default: 'both',
                validator: value => ['date', 'time', 'both'].includes(value),
            },
        },
        emits: ['update:modelValue'],
        computed: {
            title() {
                if (this.type === 'date') return this.$t('Date Formats');
                if (this.type === 'time') return this.$t('Time Formats');
                return this.$t('Date and Time Formats');
            },
            // Samples show a fixed reference moment: Mon, August 4, 2025 3:05:09 pm
            tokens() {
                const date = [
                    { token: 'j', label: this.$t('Day of the month'), sample: '4' },
                    { token: 'd', label: this.$t('Day of the month, two digits'), sample: '04' },
                    { token: 'D', label: this.$t('Weekday, short'), sample: 'Mon' },
                    { token: 'l', label: this.$t('Weekday, full'), sample: 'Monday' },
                    { token: 'n', label: this.$t('Month number'), sample: '8' },
                    { token: 'm', label: this.$t('Month number, two digits'), sample: '08' },
                    { token: 'M', label: this.$t('Month name, short'), sample: 'Aug' },
                    { token: 'F', label: this.$t('Month name, full'), sample: 'August' },
                    { token: 'y', label: this.$t('Year, two digits'), sample: '25' },
                    { token: 'Y', label: this.$t('Year, full'), sample: '2025' },
                ];

                const time = [
                    { token: 'g', label: this.$t('Hour, 12-hour'), sample: '3' },
                    { token: 'h', label: this.$t('Hour, 12-hour, two digits'), sample: '03' },
                    { token: 'G', label: this.$t('Hour, 24-hour'), sample: '15' },
                    { token: 'H', label: this.$t('Hour, 24-hour, two digits'), sample: '15' },
                    { token: 'i', label: this.$t('Minutes'), sample: '05' },
                    { token: 's', label: this.$t('Seconds'), sample: '09' },
                    { token: 'a', label: this.$t('am or pm'), sample: 'pm' },
                    { token: 'A', label: this.$t('AM or PM'), sample: 'PM' },
                ];

                if (this.type === 'date') return date;
                if (this.type === 'time') return time;
                return [...date, ...time];
            },
            examples() {
                const date = [
                    { format: 'F j, Y', sample: 'August 4, 2025' },
                    { format: 'd/m/Y', sample: '04/08/2025' },
                    { format: 'M j, Y', sample: 'Aug 4, 2025' },
                    { format: 'l, F j', sample: 'Monday, August 4' },
                ];

                const time = [
                    { format: 'g:i a', sample: '3:05 pm' },
                    { format: 'H:i', sample: '15:05' },
                    { format: 'g:i A', sample: '3:05 PM' },
                    { format: 'H:i:s', sample: '15:05:09' },
                ];

                if (this.type === 'date') return date;
                if (this.type === 'time') return time;
                return [...date, ...time];
            },
        },
    };
</script>
