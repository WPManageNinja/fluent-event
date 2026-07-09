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
                {{ message }}
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    export default {
        name: 'Dashboard',
        data() {
            return {
                message: 'Welcome to the dashboard.',
            };
        },
        computed: {
            title() {
                return this.$t('dashboard');
            },
        },
        methods: {
            /**
             * Fetch the dashboard greeting from `GET /dashboard` and bind the
             * `message` property. Kept as a named method (rather than an
             * inline `mounted` fetch) so it can be re-invoked after actions
             * that should refresh the view.
             *
             * @returns {void}
             */
            get() {
                this.$get('dashboard').then(response => {
                    this.message = response.message;
                });
            },
        },
        mounted() {
            this.get();
        },
    };
</script>
