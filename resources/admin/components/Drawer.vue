<template>
    <el-drawer
        :model-value="modelValue"
        :direction="elDirection"
        :size="size"
        :title="title"
        :with-header="withHeader"
        :show-close="showClose"
        :close-on-click-modal="closeOnClickModal"
        :append-to-body="appendToBody"
        :destroy-on-close="destroyOnClose"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <template v-if="withHeader" #header="{ close, titleId, titleClass }">
            <slot name="header" :close="close" :title-id="titleId" :title-class="titleClass">
                <h4 v-if="title" :id="titleId" :class="titleClass">{{ title }}</h4>
            </slot>
        </template>

        <slot />

        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </el-drawer>
</template>

<script type="text/babel">
export default {
    name: 'Drawer',
    props: {
        modelValue: {
            required: true,
            type: Boolean
        },
        title: {
            required: false,
            type: String,
            default: ''
        },
        size: {
            required: false,
            type: String,
            default: '460px'
        },
        direction: {
            required: false,
            type: String,
            default: 'right',
            validator: value => ['left', 'right', 'top', 'bottom'].includes(value)
        },
        withHeader: {
            required: false,
            type: Boolean,
            default: true
        },
        showClose: {
            required: false,
            type: Boolean,
            default: true
        },
        closeOnClickModal: {
            required: false,
            type: Boolean,
            default: true
        },
        appendToBody: {
            required: false,
            type: Boolean,
            default: true
        },
        destroyOnClose: {
            required: false,
            type: Boolean,
            default: false
        }
    },
    emits: ['update:modelValue'],
    computed: {
        elDirection() {
            return {
                left: 'ltr',
                right: 'rtl',
                top: 'ttb',
                bottom: 'btt'
            }[this.direction];
        }
    }
}
</script>
