<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page">
            <ErrorState
                code="403"
                :title="$t('Unauthorized')"
                :message="$t('You do not have permission to access this page.')"
            >
                <template #action>
                    <el-button type="primary" @click="goHome">
                        {{ $t('Back to Dashboard') }}
                    </el-button>
                </template>
            </ErrorState>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import ErrorState from '@/components/ErrorState.vue';

    export default {
        name: 'Unauthorized',
        components: {
            ErrorState,
        },
        methods: {
            goHome() {
                this.$router.push({ name: 'dashboard' });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Unauthorized'));
        },
    };
</script>
