<template>
    <div ref="root" class="fe-switch" role="group" :aria-label="label">
        <span class="fe-switch__indicator" aria-hidden="true"></span>

        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="fe-switch__option"
            :class="{ 'fe-switch__option--active': option.value === modelValue }"
            :aria-pressed="option.value === modelValue"
            :aria-label="option.icon ? option.label : null"
            :title="option.icon ? option.label : null"
            @click="select(option.value)"
        >
            <el-icon v-if="option.icon" aria-hidden="true">
                <component :is="option.icon" />
            </el-icon>

            <template v-else>{{ option.label }}</template>
        </button>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'SegmentedSwitch',
    props: {
        modelValue: {
            required: true,
            type: [String, Number]
        },
        options: {
            required: true,
            type: Array
        },
        label: {
            required: false,
            type: String,
            default: ''
        }
    },
    emits: ['update:modelValue', 'change'],
    watch: {
        modelValue() {
            this.$nextTick(this.moveIndicator);
        }
    },
    methods: {
        select(value) {
            if (value === this.modelValue) return;

            this.$emit('update:modelValue', value);
            this.$emit('change', value);
        },
        moveIndicator() {
            const root = this.$refs.root;
            const active = root && root.querySelector('.fe-switch__option--active');

            if (!active) return;

            root.style.setProperty('--fe-switch-x', `${active.offsetLeft}px`);
            root.style.setProperty('--fe-switch-w', `${active.offsetWidth}px`);
        }
    },
    mounted() {
        this.moveIndicator();
    }
}
</script>
