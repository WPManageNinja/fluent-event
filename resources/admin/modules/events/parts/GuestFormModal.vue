<template>
    <el-dialog
        :model-value="modelValue"
        :title="title"
        width="460px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
        @open="resetForm"
    >
        <el-form class="fe-form" label-position="top" @submit.prevent="save">
            <el-form-item>
                <SegmentedSwitch
                    v-model="mode"
                    :options="modes"
                    :label="$t('Choose how to identify the guest')"
                    @change="onModeChange"
                />
            </el-form-item>

            <el-form-item v-if="mode === 'wp_user'" :label="$t('WordPress user *')">
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

            <el-form-item :label="$t('Name *')">
                <el-input v-model="form.name" :placeholder="$t('e.g. Jane Cooper')" />
            </el-form-item>

            <el-form-item :label="$t('Email *')">
                <el-input
                    v-model="form.email"
                    type="email"
                    :disabled="mode === 'wp_user'"
                    :placeholder="$t('e.g. jane@example.com')"
                />
            </el-form-item>

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
            <el-button :disabled="saving" @click="close">
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
                this.selectedUserId = this.guest?.user_id || null;
                this.mode = this.selectedUserId ? 'wp_user' : 'manual';
                this.form = {
                    name: this.guest?.name || '',
                    email: this.guest?.email || '',
                    status: this.guest?.status || this.statuses[0].value,
                };

                this.users = this.selectedUserId
                    ? [{ id: this.selectedUserId, name: this.form.name, email: this.form.email }]
                    : [];
            },
            searchUsers(search) {
                this.$requestServer({
                    path: 'users',
                    payload: { search },
                    loadingState: 'loadingUsers',
                    errorState: null,
                    errorNotify: true,
                    onSuccess: response => {
                        this.users = response.users;
                    },
                });
            },
            applyUser(userId) {
                const user = this.users.find(item => item.id === userId);
                if (!user) return;

                this.form.name = user.name;
                this.form.email = user.email;
            },
            onModeChange() {
                if (this.mode === 'wp_user' && !this.users.length) {
                    this.searchUsers('');
                }
            },
            close() {
                this.$emit('update:modelValue', false);
            },
            save() {
                const base = `events/${this.eventId}/guests`;

                this.$requestServer({
                    method: this.isEditing ? 'patch' : 'post',
                    path: this.isEditing ? `${base}/${this.guest.id}` : base,
                    payload: {
                        ...this.form,
                        user_id: this.mode === 'wp_user' ? this.selectedUserId : null,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: () => {
                        this.close();
                        this.$emit('saved');
                    },
                });
            },
        },
    };
</script>
