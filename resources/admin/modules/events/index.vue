<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-events">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Events') }}</h1>

                <div class="fe-page__actions">
                    <SegmentedSwitch
                        v-model="period"
                        :options="periods"
                        :label="$t('Filter events by period')"
                        @change="rememberPeriod"
                    />

                    <!-- <el-button
                        v-if="$userCan('write_events')"
                        type="primary"
                        @click="goToCreate"
                    >
                        {{ $t('Add Event') }}
                    </el-button> -->
                </div>
            </div>

            <EventsView :period="period" show-calendar />
        </div>
    </transition>
</template>

<script type="text/javascript">
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import Storage from '@/utils/Storage';
    import EventsView from './parts/EventsView.vue';

    export default {
        name: 'Events',
        components: {
            SegmentedSwitch,
            EventsView,
        },
        data() {
            const savedPeriod = Storage.get('events_period');

            return {
                period: ['', 'upcoming', 'past'].includes(savedPeriod) ? savedPeriod : 'upcoming',
            };
        },
        computed: {
            periods() {
                return [
                    { value: '', label: this.$t('All') },
                    { value: 'upcoming', label: this.$t('Upcoming') },
                    { value: 'past', label: this.$t('Past') },
                ];
            },
        },
        methods: {
            rememberPeriod(period) {
                Storage.set('events_period', period);
            },
            // goToCreate() {
            //     this.$router.push({ name: 'event_create' });
            // },
        },
        mounted() {
            this.$changeTitle(this.$t('Events'));
        },
    };
</script>
