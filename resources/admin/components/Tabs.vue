<template>
    <div class="fe-tabs">
        <div class="fe-tabs__nav" role="tablist">
            <button
                v-for="tab in tabs"
                :id="`fe-tab-${tab.value}`"
                :key="tab.value"
                type="button"
                role="tab"
                class="fe-tabs__tab"
                :class="{ 'fe-tabs__tab--active': tab.value === modelValue }"
                :aria-selected="tab.value === modelValue"
                :aria-controls="`fe-tabpanel-${tab.value}`"
                @click="select(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div
            :id="`fe-tabpanel-${modelValue}`"
            class="fe-tabs__panel"
            role="tabpanel"
            :aria-labelledby="`fe-tab-${modelValue}`"
        >
            <slot :name="modelValue" />
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'Tabs',
    props: {
        modelValue: {
            required: true,
            type: String
        },
        tabs: {
            required: true,
            type: Array
        }
    },
    emits: ['update:modelValue', 'change'],
    methods: {
        select(value) {
            if (value === this.modelValue) return;
            this.$emit('update:modelValue', value);
            this.$emit('change', value);
        }
    }
}
</script>
