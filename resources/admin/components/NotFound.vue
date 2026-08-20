<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page">
            <ErrorState
                code="404"
                :title="$t('Page not found')"
                :message="$t('The page you are looking for does not exist.')"
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
        name: 'NotFound',
        components: {
            ErrorState,
        },
        methods: {
            goHome() {
                this.$router.push({ name: 'dashboard' });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Page Not Found'));
        },
    };
</script>
