<template>
    <div class="fe-danger-zone">
        <h2 class="fe-danger-zone__title">{{ $t('Danger Zone') }}</h2>

        <div class="fe-danger-zone__body">
            <h3 class="fe-danger-zone__heading">{{ $t('Delete this event') }}</h3>

            <p class="fe-danger-zone__text">
                {{ $t('Deleting an event is permanent. The event and all of its data — guests, tickets and settings — are removed for good and cannot be undone.') }}
            </p>

            <label class="fe-danger-zone__label" for="fe-delete-confirm">
                {{ confirmPrompt[0] }}<i>{{ event.title }}</i>{{ confirmPrompt[1] }}
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
            /**
             * The confirm prompt as [before, after] around the title, so the
             * sentence stays one translatable string with a %s slot instead of
             * two English fragments glued around the <i>.
             */
            confirmPrompt() {
                const [before = '', after = ''] = this.$t('Type %s to confirm.').split('%s');

                return [before, after];
            },
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
                this.$requestServer({
                    method: 'delete',
                    path: `events/${this.event.id}`,
                    loadingState: 'deleting',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: () => {
                        this.$emit('deleted');
                    },
                });
            },
        },
    };
</script>
