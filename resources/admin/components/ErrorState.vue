<template>
    <el-result
        class="fe-error-state"
        role="alert"
        icon="warning"
        :title="heading"
        :sub-title="description"
    >
        <template v-if="code" #title>
            <p class="fe-error-state__code">{{ code }}</p>
            <p>{{ heading }}</p>
        </template>

        <template #extra>
            <slot name="action">
                <el-button type="primary" @click="$emit('retry')">
                    {{ $t('Try Again') }}
                </el-button>
            </slot>
        </template>
    </el-result>
</template>

<script type="text/javascript">
    export default {
        name: 'ErrorState',
        props: {
            title: {
                required: false,
                type: String,
                default: '',
            },
            message: {
                required: false,
                type: String,
                default: '',
            },
            code: {
                required: false,
                type: [String, Number],
                default: '',
            },
        },
        emits: ['retry'],
        computed: {
            heading() {
                return this.title || this.$t('Something went wrong');
            },
            description() {
                return this.message || this.$t('We could not load this. Please try again.');
            },
        },
    };
</script>
