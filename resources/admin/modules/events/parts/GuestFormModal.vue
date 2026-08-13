<template>
    <el-dialog
        :model-value="modelValue"
        :title="title"
        width="460px"
        append-to-body
        @update:model-value="close"
        @open="resetForm"
    >
        <el-form class="fe-form" label-position="top" @submit.prevent="save">
            <el-form-item v-if="!isEditing">
                <SegmentedSwitch
                    v-model="mode"
                    :options="modes"
                    :label="$t('Choose how to add the guest')"
                    @change="resetIdentity"
                />
            </el-form-item>

            <el-form-item v-if="isPickingUser" :label="$t('WordPress user *')">
                <el-select
                    v-model="selectedUserId"
                    filterable
                    remote
                    reserve-keyword
                    :remote-method="searchUsers"
                    :loading="loadingUsers"
                    :placeholder="$t('Search by name or email')"
                    @change="applyUser"
                >
                    <el-option
                        v-for="user in users"
                        :key="user.id"
                        :label="`${user.name} (${user.email})`"
                        :value="user.id"
                    />
                </el-select>
            </el-form-item>

            <template v-if="!isPickingUser">
                <el-form-item :label="$t('Name *')">
                    <el-input v-model="form.name" :placeholder="$t('e.g. Jane Cooper')" />
                </el-form-item>

                <el-form-item :label="$t('Email *')">
                    <el-input
                        v-model="form.email"
                        type="email"
                        :placeholder="$t('e.g. jane@example.com')"
                    />
                </el-form-item>
            </template>

            <el-form-item :label="$t('Status')">
                <el-select v-model="form.status" :aria-label="$t('Status')">
                    <el-option
                        v-for="option in statuses"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button :disabled="saving" @click="close(false)">
                {{ $t('Cancel') }}
            </el-button>

            <el-button type="primary" :loading="saving" :disabled="saving" @click="save">
                {{ submitLabel }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';

    export default {
        name: 'GuestFormModal',
        components: {
            SegmentedSwitch,
        },
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            eventId: {
                type: [Number, String],
                required: true,
            },
            statuses: {
                type: Array,
                required: true,
            },
            guest: {
                type: Object,
                default: null,
            },
        },
        emits: ['update:modelValue', 'saved'],
        data() {
            return {
                saving: false,
                mode: 'manual',
                users: [],
                selectedUserId: null,
                loadingUsers: false,
                form: {
                    name: '',
                    email: '',
                    status: '',
                },
            };
        },
        computed: {
            isEditing() {
                return !!this.guest;
            },
            isPickingUser() {
                return !this.isEditing && this.mode === 'wp_user';
            },
            modes() {
                return [
                    { value: 'manual', label: this.$t('Enter manually') },
                    { value: 'wp_user', label: this.$t('WordPress user') },
                ];
            },
            title() {
                return this.isEditing ? this.$t('Edit Guest') : this.$t('Add Guest');
            },
            submitLabel() {
                return this.isEditing ? this.$t('Update Guest') : this.$t('Add Guest');
            },
        },
        methods: {
            resetForm() {
                this.mode = 'manual';
                this.selectedUserId = null;
                this.form = {
                    name: this.guest?.name || '',
                    email: this.guest?.email || '',
                    status: this.guest?.status || this.statuses[0].value,
                };
            },
            searchUsers(search) {
                this.loadingUsers = true;
                this.$get('users', { search })
                    .then(response => {
                        this.users = response.users;
                    })
                    .catch(this.$handleError)
                    .finally(() => {
                        this.loadingUsers = false;
                    });
            },
            applyUser(userId) {
                const user = this.users.find(item => item.id === userId);
                if (!user) return;

                this.form.name = user.name;
                this.form.email = user.email;
            },
            // Switching modes should not carry the other mode's identity over.
            resetIdentity() {
                this.selectedUserId = null;
                this.form.name = '';
                this.form.email = '';

                if (this.isPickingUser) {
                    this.searchUsers('');
                }
            },
            close(open = false) {
                this.$emit('update:modelValue', open);
            },
            save() {
                if (this.saving) return;
                this.saving = true;

                const base = `events/${this.eventId}/guests`;
                const request = this.isEditing
                    ? this.$patch(`${base}/${this.guest.id}`, this.form)
                    : this.$post(base, this.form);

                request
                    .then(response => {
                        // Close before the toast — the overlay would dim it otherwise.
                        this.close(false);
                        this.$notifySuccess(response.message);
                        this.$emit('saved');
                    })
                    .catch(this.$handleError)
                    .finally(() => {
                        this.saving = false;
                    });
            },
        },
    };
</script>
