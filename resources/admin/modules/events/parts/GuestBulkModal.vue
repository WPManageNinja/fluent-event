<template>
    <el-dialog
        :model-value="modelValue"
        :title="title"
        width="440px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
        @open="sendEmail = false"
    >
        <p>{{ description }}</p>

        <el-checkbox v-if="emailLabel" v-model="sendEmail">{{ emailLabel }}</el-checkbox>

        <template #footer>
            <el-button :disabled="saving" @click="close">
                {{ $t('Cancel') }}
            </el-button>

            <el-button :type="isDelete ? 'danger' : 'primary'" :loading="saving" :disabled="saving" @click="apply">
                {{ isDelete ? $t('Delete') : $t('Change status') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    export default {
        name: 'GuestBulkModal',
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            eventId: {
                type: [Number, String],
                required: true,
            },
            guestIds: {
                type: Array,
                required: true,
            },
            // { action: 'change_status', status, label } or { action: 'delete' }.
            bulk: {
                type: Object,
                default: null,
            },
        },
        emits: ['update:modelValue', 'done'],
        data() {
            return {
                saving: false,
                sendEmail: false,
            };
        },
        computed: {
            isDelete() {
                return this.bulk?.action === 'delete';
            },
            title() {
                return this.isDelete
                    ? this.$_n('Delete %d Guest', 'Delete %d Guests', this.guestIds.length)
                    : this.$_n('Change Status of %d Guest', 'Change Status of %d Guests', this.guestIds.length);
            },
            description() {
                return this.isDelete
                    ? this.$t('The selected guests will be removed from this event. This cannot be undone.')
                    : this.$t('The selected guests will be moved to %s.', this.bulk?.label);
            },
            emailLabel() {
                const notifications = window.fluentEventAdmin?.email_notifications || {};
                const status = this.bulk?.status;

                if (this.isDelete) {
                    return '';
                }

                if (status === 'going' && notifications.registration_confirmation?.enabled) {
                    return this.$t('Send Confirmation Emails');
                }

                if (['not_going', 'cancelled'].includes(status) && notifications.registration_cancellation?.enabled) {
                    return this.$t('Send Cancellation Emails');
                }

                if (status === 'waitlisted' && notifications.registration_waitlist?.enabled) {
                    return this.$t('Send Waitlist Emails');
                }

                return '';
            },
        },
        methods: {
            close() {
                this.$emit('update:modelValue', false);
            },
            apply() {
                this.$requestServer({
                    method: 'post',
                    path: `events/${this.eventId}/guests/bulk-action`,
                    payload: {
                        action: this.bulk.action,
                        status: this.bulk.status,
                        guest_ids: this.guestIds,
                        send_email: !!this.emailLabel && this.sendEmail,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.close();
                        this.$emit('done', response);
                    },
                });
            },
        },
    };
</script>
