<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-settings">
            <aside class="fe-settings__sidebar">
                <nav class="fe-settings__nav" :aria-label="$t('Settings')">
                    <router-link
                        v-for="item in menuItems"
                        :key="item.name"
                        class="fe-settings__nav-link"
                        :class="{ 'fe-settings__nav-link--active': isActive(item.name) }"
                        :to="{ name: item.name }"
                    >
                        <el-icon class="fe-settings__nav-icon">
                            <component :is="item.icon" />
                        </el-icon>
                        <span class="fe-settings__nav-text">{{ item.label }}</span>
                    </router-link>
                </nav>
            </aside>

            <div class="fe-settings__body">
                <router-view />
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { Setting, User } from '@element-plus/icons-vue';

    export default {
        name: 'SettingsLayout',
        computed: {
            menuItems() {
                const items = [
                    {
                        name: 'settings_general',
                        label: this.$t('General'),
                        icon: Setting,
                    },
                ];

                if (this.$userCan('manage_all_data')) {
                    items.push({
                        name: 'settings_team',
                        label: this.$t('Team'),
                        icon: User,
                    });
                }

                return items;
            },
        },
        methods: {
            isActive(name) {
                return this.$route.name === name;
            },
        },
    };
</script>
