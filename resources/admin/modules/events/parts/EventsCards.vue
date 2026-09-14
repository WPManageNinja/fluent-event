<template>
    <div class="fe-events-cards">
        <el-empty v-if="!events.length" :description="$t('No events yet.')" />

        <template v-else>
            <div class="fe-events-cards__grid">
                <EventCard
                    v-for="event in events"
                    :key="event.id"
                    class="fe-event-card--tile"
                    :event="event"
                    :show-calendar="showCalendar"
                    show-date
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
        name: 'EventsCards',
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
    };
</script>
