<template>
    <div class="fe-calendar-tab">
        <div class="fe-calendar-tab__main">
            <slot />
        </div>

        <aside class="fe-calendar-tab__aside">
            <section
                v-if="$userCan(['read_events', 'write_events'], { match_type: 'any' })"
                class="fe-calendar-tab__panel"
            >
                <h2 class="fe-calendar-tab__panel-title">{{ $t('Coming Up') }}</h2>

                <template v-if="upcomingEvents.length">
                    <EventListItem
                        v-for="event in upcomingEvents"
                        :key="`upcoming-${event.id}`"
                        :event="event"
                        :meta="$formatDateTime(event.start_at_site)"
                    />
                </template>

                <p v-else class="fe-calendar-tab__panel-empty">
                    {{ $t('No upcoming events.') }}
                </p>
            </section>

            <section class="fe-calendar-tab__panel">
                <h2 class="fe-calendar-tab__panel-title">{{ $t('Details') }}</h2>

                <dl class="fe-calendar-tab__facts">
                    <dt>{{ $t('Status') }}</dt>
                    <dd class="capitalize">{{ $calendarStatusLabel(calendar.status) }}</dd>

                    <dt>{{ $t('Created') }}</dt>
                    <dd>{{ $formatDate(calendar.created_at) }}</dd>

                    <template v-if="calendar.creator_name">
                        <dt>{{ $t('Created by') }}</dt>
                        <dd>{{ calendar.creator_name }}</dd>
                    </template>
                </dl>
            </section>
        </aside>
    </div>
</template>

<script type="text/javascript">
    import EventListItem from '@/modules/dashboard/parts/EventListItem.vue';

    export default {
        name: 'CalendarTabLayout',
        components: {
            EventListItem,
        },
        props: {
            calendar: {
                type: Object,
                required: true,
            },
            upcomingEvents: {
                type: Array,
                default: () => [],
            },
        },
    };
</script>
