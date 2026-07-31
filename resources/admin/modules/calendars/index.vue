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
                {{ $t('The three-column calendar grid lands in chunk 9.') }}
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    /**
     * Placeholder so the Calendars topbar entry has a route to resolve.
     * Replaced by the real grid in chunk 9 — see
     * .claude/docs/phase/v1/screens/calendars.md
     */
    export default {
        name: 'Calendars',
        computed: {
            title() {
                return this.$t('Calendars');
            },
        },
    };
</script>
