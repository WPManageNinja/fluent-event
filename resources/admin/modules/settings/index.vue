<template>
    <transition name="fade" mode="out-in" appear>
        <div>
            <div class="page-header">
                <el-row>
                    <el-col :span="12">
                        <strong>{{ title }}</strong>
                    </el-col>
                </el-row>
            </div>

            <div class="page-content">
                {{ $t('The four v1 settings land in chunk 21.') }}
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    /**
     * Placeholder so the Settings topbar entry has a route to resolve.
     * Replaced by the real form in chunk 21 — see
     * .claude/docs/phase/v1/screens/settings.md
     */
    export default {
        name: 'Settings',
        computed: {
            title() {
                return this.$t('Settings');
            },
        },
    };
</script>
