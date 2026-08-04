<template>
    <el-result icon="warning" :title="heading" :sub-title="description">
        <template #extra>
            <el-button type="primary" @click="$emit('retry')">
                {{ $t('Try Again') }}
            </el-button>
        </template>
    </el-result>
</template>

<script type="text/babel">
export default {
    name: 'ErrorState',
    props: {
        title: {
            required: false,
            type: String,
            default: ''
        },
        message: {
            required: false,
            type: String,
            default: ''
        }
    },
    emits: ['retry'],
    computed: {
        heading() {
            return this.title || this.$t('Something went wrong');
        },
        description() {
            return this.message || this.$t('We could not load this. Please try again.');
        }
    }
}
</script>
