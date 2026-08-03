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
                {{ $t('The event create form lands in a later chunk.') }}
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    /**
     * Placeholder so the "Add New Event" submenu has a route to resolve.
     */
    export default {
        name: 'EventCreate',
        computed: {
            title() {
                return this.$t('Add New Event');
            },
        },
    };
</script>
