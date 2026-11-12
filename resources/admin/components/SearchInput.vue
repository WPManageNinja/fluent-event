<template>
    <el-input
        v-model="term"
        :placeholder="placeholder"
        :aria-label="placeholder"
        :clearable="clearable"
        @clear="commit"
        @keyup.enter="onEnter"
    />
</template>

<script type="text/babel">
export default {
    name: 'SearchInput',
    props: {
        modelValue: {
            required: false,
            type: String,
            default: ''
        },
        placeholder: {
            required: false,
            type: String,
            default: ''
        },
        type: {
            required: false,
            type: String,
            default: 'search_on_enter',
            validator: value => ['search_on_enter', 'debounce'].includes(value)
        },
        debounce_time: {
            required: false,
            type: Number,
            default: 500
        },
        clearable: {
            required: false,
            type: Boolean,
            default: true
        }
    },
    emits: ['update:modelValue', 'search'],
    data() {
        return {
            term: '',
            timer: null
        };
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(value) {
                this.term = value || '';
            }
        },
        term() {
            if (this.type !== 'debounce') {
                return;
            }

            clearTimeout(this.timer);
            this.timer = setTimeout(this.commit, this.debounce_time);
        }
    },
    beforeUnmount() {
        clearTimeout(this.timer);
    },
    methods: {
        onEnter() {
            if (this.type === 'search_on_enter') {
                this.commit();
            }
        },
        commit() {
            clearTimeout(this.timer);

            if (this.term === this.modelValue) {
                return;
            }

            this.$emit('update:modelValue', this.term);
            this.$emit('search', this.term);
        }
    }
}
</script>
