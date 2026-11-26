<template>
    <div ref="root" class="fe-switch" role="group" :aria-label="label">
        <span ref="indicator" class="fe-switch__indicator" aria-hidden="true"></span>

        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="fe-switch__option"
            :class="{ 'fe-switch__option--active': option.value === modelValue }"
            :aria-pressed="option.value === modelValue"
            :aria-label="option.icon && !showLabels ? option.label : null"
            :title="option.icon && !showLabels ? option.label : null"
            @click="select(option.value)"
        >
            <el-icon v-if="option.icon" aria-hidden="true">
                <component :is="option.icon" />
            </el-icon>

            <template v-if="!option.icon || showLabels">{{ option.label }}</template>
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
        },
        // Icons render alone unless this is set.
        showLabels: {
            type: Boolean,
            default: false
        }
    },
    emits: ['update:modelValue', 'change'],
    watch: {
        modelValue() {
            this.$nextTick(this.moveIndicator);
        },
        options() {
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
            const { root, indicator } = this.$refs;
            const active = root && root.querySelector('.fe-switch__option--active');

            if (!active || !indicator) return;

            // The RTL stylesheet flips the indicator's `left: 0` to `right: 0`, but the shift below is
            // measured from the left. Anchored on the left, the indicator rests at 0; anchored on the right,
            // it rests one width in from the right edge. offsetLeft ignores the transform.
            const width = active.offsetWidth;
            const rest = indicator.offsetLeft === 0 ? 0 : root.clientWidth - width;

            root.style.setProperty('--fe-switch-x', `${active.offsetLeft - rest}px`);
            root.style.setProperty('--fe-switch-w', `${width}px`);
        }
    },
    mounted() {
        this.moveIndicator();

        this.resizeObserver = new ResizeObserver(this.moveIndicator);
        this.resizeObserver.observe(this.$refs.root);
    },
    beforeUnmount() {
        this.resizeObserver?.disconnect();
    }
}
</script>
