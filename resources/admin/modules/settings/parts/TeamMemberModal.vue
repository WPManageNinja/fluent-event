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
                        :disabled="key !== allDataKey && hasAllData"
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
        },
        watch: {
            permissions(value, previous) {
                const has = value.includes(this.allDataKey);
                const had = previous.includes(this.allDataKey);

                if (has && !had) {
                    this.permissions = Object.keys(this.permissionSets);
                } else if (!has && had) {
                    this.permissions = [];
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
                if(this.loadingUsers) return;
                this.loadingUsers = true;
                this.$get('users', {
                    search,
                    role_in: 'administrator,editor,author,contributor',
                })
                    .then(response => {
                        this.users = response.users;
                    })
                    .catch(error => {
                        this.$handleError(error);
                    })
                    .finally(() => {
                        this.loadingUsers = false;
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

                if(this.saving) return;
                this.saving = true;
                this.$post('settings/team', {
                    user_id: this.userId,
                    permissions: this.hasAllData ? [this.allDataKey] : this.permissions,
                })
                    .then(response => {
                        this.$notifySuccess(response.message);
                        this.$emit('saved', response.members);
                    })
                    .catch(error => {
                        this.$handleError(error);
                    })
                    .finally(() => {
                        this.saving = false;
                    });
            },
            close() {
                this.$emit('update:modelValue', false);
            },
        },
    };
</script>
