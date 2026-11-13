<template>
    <el-dialog
        :model-value="modelValue"
        :title="title"
        width="600px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
        @open="resetForm"
    >
        <el-form class="fe-form" label-position="top" @submit.prevent="save">
            <el-form-item v-if="!member" :label="$t('Select member')">
                <el-select
                    v-model="userId"
                    size="large"
                    filterable
                    remote
                    reserve-keyword
                    :remote-method="searchUsers"
                    :loading="loadingUsers"
                    :placeholder="$t('Search by name or email')"
                >
                    <el-option
                        v-for="user in users"
                        :key="user.id"
                        :label="`${user.name} (${user.email})`"
                        :value="user.id"
                    >
                        <div class="fe-team-modal__user-option">
                            <img
                                class="fe-team-modal__user-avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <span class="fe-team-modal__user-name">{{ user.name }}</span>
                            <span class="fe-team-modal__user-email">{{ user.email }}</span>
                        </div>
                    </el-option>
                </el-select>
            </el-form-item>

            <div v-else class="fe-team-modal__member">
                <img
                    class="fe-team-modal__avatar"
                    :src="member.avatar"
                    :alt="member.name"
                />
                <div class="fe-team-modal__identity">
                    <span class="fe-team-modal__name">{{ member.name }}</span>
                    <span class="fe-team-modal__email">{{ member.email }}</span>
                </div>
            </div>

            <el-form-item :label="$t('Access permissions for this user')">
                <el-checkbox-group v-model="permissions" class="fe-team-modal__permissions">
                    <el-checkbox
                        v-for="(label, key) in permissionSets"
                        :key="key"
                        :value="key"
                        :disabled="(key !== allDataKey && hasAllData) || impliedReads.includes(key)"
                    >
                        {{ label }}
                    </el-checkbox>
                </el-checkbox-group>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button :disabled="saving" @click="close">
                {{ $t('Cancel') }}
            </el-button>

            <el-button type="primary" :loading="saving" :disabled="saving" @click="save">
                {{ member ? $t('Update Permissions') : $t('Add Member') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    export default {
        name: 'TeamMemberModal',
        props: {
            modelValue: {
                type: Boolean,
                default: false,
            },
            member: {
                type: Object,
                default: null,
            },
            permissionSets: {
                type: Object,
                default: () => ({}),
            },
        },
        emits: ['update:modelValue', 'saved'],
        data() {
            return {
                userId: null,
                permissions: [],
                users: [],
                loadingUsers: false,
                saving: false,
                allDataKey: 'manage_all_data',
            };
        },
        computed: {
            title() {
                return this.member
                    ? this.$t('Edit Team Member')
                    : this.$t('Add Team Member');
            },
            hasAllData() {
                return this.permissions.includes(this.allDataKey);
            },
            // write_x implies read_x
            impliedReads() {
                return this.permissions
                    .filter(key => key.startsWith('write_'))
                    .map(key => key.replace('write_', 'read_'))
                    .filter(key => key in this.permissionSets);
            },
        },
        watch: {
            permissions(value, previous) {
                const has = value.includes(this.allDataKey);
                const had = previous.includes(this.allDataKey);

                if (has && !had) {
                    this.permissions = Object.keys(this.permissionSets);
                    return;
                }

                if (!has && had) {
                    this.permissions = [];
                    return;
                }

                // Unchecking a write releases its read, same as clearing full access.
                const released = previous
                    .filter(key => key.startsWith('write_') && !value.includes(key))
                    .map(key => key.replace('write_', 'read_'));

                const next = value.filter(key => !released.includes(key));
                const missing = this.impliedReads.filter(key => !next.includes(key));

                if (next.length !== value.length || missing.length) {
                    this.permissions = [...next, ...missing];
                }
            },
        },
        methods: {
            resetForm() {
                this.userId = this.member ? this.member.id : null;
                this.permissions = this.member ? [...this.member.permissions] : [];
                this.users = [];

                if (!this.member) this.searchUsers('');
            },
            searchUsers(search) {
                this.$requestServer({
                    path: 'users',
                    payload: {
                        search,
                        role_in: 'administrator,editor,author,contributor',
                    },
                    loadingState: 'loadingUsers',
                    errorState: null,
                    errorNotify: true,
                    onSuccess: response => {
                        this.users = response.users;
                    },
                });
            },
            save() {
                if (!this.userId) {
                    this.$notifyWarning(this.$t('Pick a user first.'));
                    return;
                }

                if (!this.permissions.length) {
                    this.$notifyWarning(this.$t('Select at least one permission.'));
                    return;
                }

                this.$requestServer({
                    method: 'post',
                    path: 'settings/team',
                    payload: {
                        user_id: this.userId,
                        permissions: this.hasAllData ? [this.allDataKey] : this.permissions,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.$emit('saved', response.members);
                    },
                });
            },
            close() {
                this.$emit('update:modelValue', false);
            },
        },
    };
</script>
