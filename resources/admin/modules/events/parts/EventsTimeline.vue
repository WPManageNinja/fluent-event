<template>
    <div class="fe-events-timeline">
        <el-empty v-if="!rows.length" :description="$t('No events yet.')" />

        <template v-else>
            <div
                v-for="row in rows"
                :key="row.event.id"
                class="fe-events-timeline__row"
            >
                <div class="fe-events-timeline__gutter">
                    <span class="fe-events-timeline__date">{{ row.date }}</span>
                    <span class="fe-events-timeline__weekday">{{ row.weekday }}</span>
                </div>

                <span class="fe-events-timeline__dot" aria-hidden="true"></span>

                <EventCard
                    :event="row.event"
                    :show-calendar="showCalendar"
                    @open="$emit('open', $event)"
                />
            </div>

            <Pagination
                :pagination="pagination"
                hide_on_single
                @fetch="$emit('fetch')"
            />
        </template>
    </div>
</template>

<script type="text/javascript">
    import Pagination from '@/components/Pagination.vue';
    import EventCard from './EventCard.vue';

    export default {
        name: 'EventsTimeline',
        components: {
            Pagination,
            EventCard,
        },
        props: {
            events: {
                type: Array,
                required: true,
            },
            pagination: {
                type: Object,
                required: true,
            },
            showCalendar: {
                type: Boolean,
                default: false,
            },
        },
        emits: ['open', 'fetch'],
        computed: {
            rows() {
                return this.events.map(event => ({
                    event,
                    date: this.$formatDate(event.start_at_local),
                    weekday: this.$formatDate(event.start_at_local, 'dddd'),
                }));
            },
        },
    };
</script>
