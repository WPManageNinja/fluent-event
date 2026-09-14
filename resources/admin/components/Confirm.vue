<template>
    <el-popover
        width="170"
        @hide="cancel"
        v-model:visible="visible"
        :placement="placement"
        trigger="click"
    >
        <p v-html="message"></p>

        <div class="action-buttons">
            <el-button
                link
                size="small"
                @click="cancel($event)">
                {{ $t('Cancel') }}
            </el-button>

            <el-button
                type="primary"
                size="small"
                @click="confirm($event)">
                {{ $t('Confirm') }}
            </el-button>
        </div>

        <template #reference>
            <slot name="reference">
                <el-button v-bind="$attrs" size="small" type="danger">
                    <el-icon><delete /></el-icon>
                </el-button>
            </slot>
        </template>
    </el-popover>
</template>

<script>
import { Delete } from '@element-plus/icons-vue';
export default {
    name: 'Confirm',
    components: { Delete },
    inheritAttrs: false,
    props: {
        placement: {
            type: String,
            default: 'top-end'
        },
        message: {
            type: String,
            default: 'Are you sure?'
        }
    },
    data() {
        return {
            visible: false
        }
    },
    methods: {
        /** Close the popover. */
        hide() {
            this.visible = false;
        },
        /**
         * User clicked "confirm" — emit `yes` with the original click event
         * and close the popover.
         * @param {Event} e
         */
        confirm(e) {
            this.hide();
            this.$emit('yes', e);
        },
        /**
         * User clicked "cancel" — emit `no` with the original click event
         * and close the popover.
         * @param {Event} e
         */
        cancel(e) {
            this.hide();
            this.$emit('no', e);
        }
    }
};
</script>
