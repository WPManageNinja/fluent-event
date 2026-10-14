<template>
    <button
        type="button"
        class="fe-events-grid__event"
        :style="{ '--fe-event-color': color }"
        :title="event.title"
        :aria-label="$t('View %s', event.title)"
        @click="$emit('open', event)"
    >
        <span class="fe-events-grid__event-time">{{ time }}</span>
        <span class="fe-events-grid__event-title">{{ event.title }}</span>
    </button>
</template>

<script type="text/javascript">
    export default {
        name: 'EventsGridEntry',
        props: {
            event: {
                type: Object,
                required: true,
            },
            time: {
                type: String,
                default: '',
            },
            color: {
                type: String,
                default: null,
            },
        },
        emits: ['open'],
    };
</script>
