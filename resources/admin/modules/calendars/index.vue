<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendars">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Calendars') }}</h1>

                <!-- <div class="fe-page__actions">
                    <el-button
                        v-if="$userCan('write_calendars')"
                        type="primary"
                        @click="goToCreate"
                    >
                        <el-icon><Plus /></el-icon>
                        <span>{{ $t('Create Calendar') }}</span>
                    </el-button>
                </div> -->
            </div>

            <div v-if="!isOnboarding" class="fe-calendars__toolbar">
                <SegmentedSwitch
                    v-model="query.status"
                    class="fe-switch--compact"
                    :options="statuses"
                    :label="$t('Filter calendars by status')"
                    @change="fetchCalendars"
                />

                <SearchInput
                    v-model="query.search"
                    class="fe-calendars__search"
                    :placeholder="$t('Search calendars')"
                    type="search_on_enter"
                    @search="fetchCalendars"
                />
            </div>

            <Skeleton v-if="loading && !fetched" type="calendar-grid" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendars" />

            <div
                v-else-if="calendars.length"
                class="fe-calendars__grid"
                :class="{ 'fe-is-loading': loading }"
                :aria-busy="loading"
            >
                <CalendarCard
                    v-for="calendar in calendars"
                    :key="calendar.id"
                    :calendar="calendar"
                />
            </div>

            <EmptyState
                v-else-if="isFiltered"
                :title="$t('No calendars found')"
                :message="$t('No calendars match your search.')"
            />

            <EmptyState
                v-else
                :title="$t('No calendars yet')"
                :message="$t('A space gets its calendar when its first event is posted in FluentCommunity.')"
            >
                <!-- <template #action>
                    <el-button
                        v-if="$userCan('write_calendars')"
                        type="primary"
                        @click="goToCreate"
                    >
                        {{ $t('Create Calendar') }}
                    </el-button>
                </template> -->
            </EmptyState>
        </div>
    </transition>
</template>

<script type="text/javascript">
    // import { Plus } from '@element-plus/icons-vue';
    import SearchInput from '@/components/SearchInput.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import EmptyState from '@/components/EmptyState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import CalendarCard from './parts/CalendarCard.vue';

    export default {
        name: 'Calendars',
        components: {
            // Plus,
            SearchInput,
            ErrorState,
            EmptyState,
            Skeleton,
            SegmentedSwitch,
            CalendarCard,
        },
        data() {
            return {
                loading: false,
                fetched: false,
                error: '',
                calendars: [],
                counts: {},
                query: {
                    search: '',
                    status: '',
                },
            };
        },
        computed: {
            statuses() {
                return [
                    { value: '', label: this.$t('All') },
                    ...this.$calendarStatuses(),
                ].map(({ value, label }) => ({
                    value,
                    label: this.$t('%s (%d)', label, this.counts[value || 'all'] || 0),
                }));
            },
            isFiltered() {
                return !!this.query.search || !!this.query.status;
            },
            isOnboarding() {
                return this.fetched && !this.loading && !this.error && !this.calendars.length && !this.isFiltered;
            },
        },
        methods: {
            fetchCalendars() {
                this.$requestServer({
                    path: 'calendars',
                    payload: {
                        search: this.query.search,
                        status: this.query.status,
                    },
                    onSuccess: response => {
                        this.fetched = true;
                        this.calendars = response.calendars;
                        this.counts = response.counts;
                    },
                    onError: () => {
                        this.calendars = [];
                    },
                });
            },
            // goToCreate() {
            //     this.$router.push({ name: 'calendar_create' });
            // },
        },
        mounted() {
            this.$changeTitle(this.$t('Calendars'));
            this.fetchCalendars();
        },
    };
</script>
