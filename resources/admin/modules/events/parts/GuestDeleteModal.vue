<template>
    <el-dialog
        :model-value="modelValue"
        :title="$t('Delete Guest')"
        width="420px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
        @open="sendEmail = false"
    >
        <p>{{ $t('%s will be removed from this event. This cannot be undone.', guest?.name) }}</p>

        <el-checkbox v-if="canSendEmail" v-model="sendEmail">
            {{ $t('Send Cancellation Email') }}
        </el-checkbox>

        <template #footer>
            <el-button :disabled="deleting" @click="close">
                {{ $t('Cancel') }}
            </el-button>

            <el-button type="danger" :loading="deleting" :disabled="deleting" @click="deleteGuest">
                {{ $t('Delete') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    export default {
        name: 'GuestDeleteModal',
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            eventId: {
                type: [Number, String],
                required: true,
            },
            guest: {
                type: Object,
                default: null,
            },
        },
        emits: ['update:modelValue', 'deleted'],
        data() {
            return {
                deleting: false,
                sendEmail: false,
            };
        },
        computed: {
            canSendEmail() {
                return ['going', 'checked_in'].includes(this.guest?.status)
                    && !!window.fluentEventAdmin?.email_notifications?.registration_cancellation?.enabled;
            },
        },
        methods: {
            close() {
                this.$emit('update:modelValue', false);
            },
            deleteGuest() {
                this.$requestServer({
                    method: 'delete',
                    path: `events/${this.eventId}/guests/${this.guest.id}`,
                    payload: {
                        send_email: this.canSendEmail && this.sendEmail,
                    },
                    loadingState: 'deleting',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: () => {
                        this.close();
                        this.$emit('deleted', this.guest);
                    },
                });
            },
        },
    };
</script>
