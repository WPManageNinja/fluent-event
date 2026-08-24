<template>
    <div class="fe-danger-zone">
        <h2 class="fe-danger-zone__title">{{ $t('Danger Zone') }}</h2>

        <div class="fe-danger-zone__body">
            <h3 class="fe-danger-zone__heading">{{ $t('Delete this event') }}</h3>

            <p class="fe-danger-zone__text">
                {{ $t('Deleting an event is permanent. The event and all of its data — guests, tickets and settings — are removed for good and cannot be undone.') }}
            </p>

            <label class="fe-danger-zone__label" for="fe-delete-confirm">
                Type <i>{{ $t(event.title) }}</i> to confirm.
            </label>

            <el-input
                id="fe-delete-confirm"
                v-model="typedTitle"
                class="fe-danger-zone__input"
                :placeholder="event.title"
                :disabled="deleting"
            />

            <el-button
                type="danger"
                :disabled="!canDelete"
                :loading="deleting"
                @click="promptDelete"
            >
                {{ $t('Delete Event') }}
            </el-button>
        </div>
    </div>
</template>

<script type="text/javascript">
    export default {
        name: 'EventDangerZone',
        props: {
            event: {
                type: Object,
                required: true,
            },
        },
        emits: ['deleted'],
        data() {
            return {
                typedTitle: '',
                deleting: false,
            };
        },
        computed: {
            canDelete() {
                return this.typedTitle.trim() === this.event.title && !this.deleting;
            },
        },
        methods: {
            promptDelete() {
                if (!this.canDelete) return;
                this.$confirm(
                    this.$t('This event and all of its data will be permanently deleted.'),
                    this.$t('Delete Event'),
                    {
                        type: 'warning',
                        confirmButtonText: this.$t('Delete'),
                        cancelButtonText: this.$t('Cancel'),
                    }
                )
                    .then(this.deleteEvent)
                    .catch(() => {});
            },
            deleteEvent() {
                if(this.deleting) return;
                this.deleting = true;
                return this.$del(`events/${this.event.id}`)
                    .then(response => {
                        this.$notifySuccess(response.message);
                        this.$emit('deleted');
                    })
                    .catch(this.$handleError)
                    .finally(() => {
                        this.deleting = false;
                    });
            },
        },
    };
</script>
