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
                {{ $t('The combined events timeline lands in chunk 14d.') }}
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    /**
     * Placeholder so the Events topbar entry has a route to resolve.
     * Replaced by the real timeline in chunk 14d — see
     * .claude/docs/phase/v1/screens/events.md
     */
    export default {
        name: 'Events',
        computed: {
            title() {
                return this.$t('Events');
            },
        },
    };
</script>
