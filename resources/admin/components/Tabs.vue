<template>
    <div class="fe-tabs">
        <div ref="nav" class="fe-tabs__nav" role="tablist">
            <button
                v-for="(tab, index) in tabs"
                :id="`fe-tab-${tab.value}`"
                :key="tab.value"
                type="button"
                role="tab"
                class="fe-tabs__tab"
                :class="{ 'fe-tabs__tab--active': tab.value === modelValue }"
                :aria-selected="tab.value === modelValue"
                :aria-controls="isRendered(tab.value) ? `fe-tabpanel-${tab.value}` : null"
                :tabindex="tab.value === modelValue ? 0 : -1"
                @click="select(tab.value)"
                @keydown="handleKeydown($event, index)"
            >
                {{ tab.label }}
            </button>
        </div>

        <template v-for="tab in tabs" :key="tab.value">
            <div
                v-if="isRendered(tab.value)"
                v-show="tab.value === modelValue"
                :id="`fe-tabpanel-${tab.value}`"
                class="fe-tabs__panel"
                role="tabpanel"
                :aria-labelledby="`fe-tab-${tab.value}`"
            >
                <slot :name="tab.value" />
            </div>
        </template>
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
        },
        unmountOnHide: {
            type: Boolean,
            default: false
        }
    },
    emits: ['update:modelValue', 'change'],
    data() {
        return {
            visited: [this.modelValue]
        };
    },
    watch: {
        modelValue(value) {
            if (!this.visited.includes(value)) {
                this.visited.push(value);
            }
        }
    },
    methods: {
        isRendered(value) {
            if (this.unmountOnHide) {
                return value === this.modelValue;
            }
            return this.visited.includes(value);
        },
        select(value) {
            if (value === this.modelValue) return;
            this.$emit('update:modelValue', value);
            this.$emit('change', value);
        },
        handleKeydown(event, index) {
            const total = this.tabs.length;
            let next = null;

            if (event.key === 'ArrowLeft') {
                next = (index - 1 + total) % total;
            } else if (event.key === 'ArrowRight') {
                next = (index + 1) % total;
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = total - 1;
            }

            if (next === null) return;

            event.preventDefault();
            this.select(this.tabs[next].value);
            this.$nextTick(() => this.$refs.nav.children[next].focus());
        }
    }
}
</script>
