<template>
    <router-link
        :to="{ name: 'event_detail', params: { id: event.id } }"
        class="fe-dashboard__event"
    >
        <span
            class="fe-color-swatch fe-dashboard__event-swatch"
            :style="{ background: calendarColor }"
            aria-hidden="true"
        ></span>

        <span class="fe-dashboard__event-info">
            <span class="fe-dashboard__event-title">{{ event.title }}</span>
            <span class="fe-dashboard__event-meta">{{ meta }}</span>
        </span>

        <span v-if="count" class="fe-badge fe-dashboard__event-count">{{ count }}</span>
    </router-link>
</template>

<script type="text/javascript">
    export default {
        name: 'EventListItem',
        props: {
            event: {
                type: Object,
                required: true,
            },
            meta: {
                type: String,
                default: '',
            },
            count: {
                type: String,
                default: '',
            },
        },
        computed: {
            calendarColor() {
                return this.event.calendar?.meta?.theme_color
                    || 'var(--fe-secondary-border, #9ca3af)';
            },
        },
    };
</script>
