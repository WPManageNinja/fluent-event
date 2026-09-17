<template>
    <div class="fe-settings-page">
        <div class="fe-settings-page__header">
            <div>
                <h1 class="fe-settings-page__title">{{ $t('Team') }}</h1>
            </div>

            <el-button
                v-if="$userCan('manage_all_data')"
                type="primary"
                @click="openAdd"
            >
                {{ $t('Add Member') }}
            </el-button>
        </div>

        <Skeleton v-if="loading" type="team_settings" :rows="3" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchTeam" />

        <ul v-else-if="members.length" class="fe-team">
            <li v-for="member in members" :key="member.id" class="fe-team__member">
                <img
                    class="fe-team__avatar"
                    :src="member.avatar"
                    :alt="member.name"
                />

                <div class="fe-team__identity">
                    <span class="fe-team__name" :title="member.name">{{ member.name }}</span>
                    <span class="fe-team__email" :title="member.email">{{ member.email }}</span>
                </div>

                <div class="fe-team__permissions">
                    <el-tag v-if="member.is_admin" type="warning" size="small">
                        {{ $t('Administrator') }}
                    </el-tag>

                    <el-tag
                        v-for="permission in member.permissions"
                        :key="permission"
                        type="info"
                        size="small"
                    >
                        {{ permissionLabel(permission) }}
                    </el-tag>
                </div>

                <div v-if="$userCan('manage_all_data')" class="fe-team__actions">
                    <el-button
                        size="small"
                        :aria-label="$t('Edit permissions')"
                        :title="$t('Edit permissions')"
                        @click="openEdit(member)"
                    >
                        <el-icon><EditPen /></el-icon>
                    </el-button>

                    <el-button
                        size="small"
                        type="danger"
                        plain
                        :aria-label="$t('Remove member')"
                        :title="$t('Remove member')"
                        @click="removeMember(member)"
                    >
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </div>
            </li>
        </ul>

        <el-empty
            v-else
            :description="$t('No team members yet. Add one to share access.')"
        />

        <TeamMemberModal
            v-model="showModal"
            :member="editingMember"
            :permission-sets="permissionSets"
            @saved="onSaved"
        />
    </div>
</template>

<script type="text/javascript">
    import { Delete, EditPen } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import TeamMemberModal from './parts/TeamMemberModal.vue';

    export default {
        name: 'SettingsTeam',
        components: {
            Delete,
            EditPen,
            ErrorState,
            Skeleton,
            TeamMemberModal,
        },
        data() {
            return {
                loading: false,
                error: '',
                members: [],
                permissionSets: {},
                showModal: false,
                editingMember: null,
            };
        },
        methods: {
            fetchTeam() {
                this.$requestServer({
                    path: 'settings/team',
                    onSuccess: response => {
                        this.members = response.members;
                        this.permissionSets = response.permission_sets;
                    },
                    onError: () => {
                        this.members = [];
                    },
                });
            },
            permissionLabel(key) {
                return this.permissionSets[key] || key;
            },
            openAdd() {
                this.editingMember = null;
                this.showModal = true;
            },
            openEdit(member) {
                this.editingMember = member;
                this.showModal = true;
            },
            onSaved(members) {
                this.members = members;
                this.showModal = false;
            },
            removeMember(member) {
                this.$confirm(
                    this.$t('%s will lose all access to this plugin. Continue?', member.name),
                    this.$t('Remove team member'),
                    {
                        type: 'warning',
                        confirmButtonText: this.$t('Remove'),
                        cancelButtonText: this.$t('Cancel'),
                    }
                )
                    .then(() => {
                        this.$requestServer({
                            method: 'delete',
                            path: `settings/team/${member.id}`,
                            loadingState: null,
                            errorState: null,
                            successNotify: true,
                            errorNotify: true,
                            onSuccess: response => {
                                this.members = response.members;
                            },
                        });
                    })
                    .catch(() => {});
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Team Settings'));
            this.fetchTeam();
        },
    };
</script>
