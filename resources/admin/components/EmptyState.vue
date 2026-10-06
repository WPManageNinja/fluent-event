<template>
    <div class="fe-empty-state" role="status">
        <h2 class="fe-empty-state__title">{{ title }}</h2>

        <p v-if="message" class="fe-empty-state__message">{{ message }}</p>

        <div v-if="$slots.action" class="fe-empty-state__action">
            <slot name="action" />
        </div>
    </div>
</template>

<script type="text/javascript">
    export default {
        name: 'EmptyState',
        props: {
            title: {
                required: true,
                type: String,
            },
            message: {
                required: false,
                type: String,
                default: '',
            },
        },
    };
</script>
