<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendar-detail">
            <el-skeleton v-if="loading" :rows="6" animated />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendar" />

            <template v-else>
                <div class="fe-page__header">
                    <div>
                        <nav class="fe-page__breadcrumb" :aria-label="$t('Breadcrumb')">
                            <router-link
                                class="fe-page__breadcrumb-link"
                                :to="{ name: 'calendars' }"
                            >
                                {{ $t('Calendars') }}
                            </router-link>

                            <el-icon class="fe-page__breadcrumb-separator" aria-hidden="true">
                                <ArrowRight />
                            </el-icon>
                        </nav>

                        <h1 class="fe-page__title">{{ calendar.title }}</h1>
                    </div>

                    <div class="fe-page__actions">
                        <el-button type="secondary" @click="goToEdit">
                            {{ $t('Edit Calendar') }}
                        </el-button>
                    </div>
                </div>

                <Tabs v-model="tab" :tabs="tabs">
                    <template #overview>
                        <div class="fe-card">
                            <div v-if="calendar.description" class="fe-calendar-detail__description" v-html="calendar.description"></div>

                            <el-empty v-else :description="$t('No description yet.')" />
                        </div>
                    </template>

                    <template #settings>
                        <CalendarDangerZone :calendar="calendar" @deleted="goToList" />
                    </template>
                </Tabs>
            </template>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { ArrowRight } from '@element-plus/icons-vue';
    import Tabs from '@/components/Tabs.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import CalendarDangerZone from './parts/CalendarDangerZone.vue';

    export default {
        name: 'CalendarDetail',
        components: {
            ArrowRight,
            Tabs,
            ErrorState,
            CalendarDangerZone,
        },
        data() {
            return {
                loading: true,
                error: '',
                tab: 'overview',
                calendar: {},
            };
        },
        computed: {
            tabs() {
                return [
                    { value: 'overview', label: this.$t('Overview') },
                    { value: 'settings', label: this.$t('Settings') },
                ];
            },
        },
        methods: {
            fetchCalendar() {
                this.loading = true;
                this.$get(`calendars/${this.$route.params.id}`)
                    .then(response => {
                        this.error = '';
                        this.calendar = response.calendar;
                        this.$changeTitle(this.calendar.title);
                    })
                    .catch(error => {
                        this.error = error?.message || '';
                        this.calendar = {};
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
            goToEdit() {
                this.$router.push({
                    name: 'calendar_edit',
                    params: { id: this.$route.params.id },
                });
            },
            goToList() {
                this.$router.push({ name: 'calendars' });
            },
        },
        mounted() {
            this.fetchCalendar();
        },
    };
</script>
