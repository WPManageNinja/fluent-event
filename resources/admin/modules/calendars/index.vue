<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendars">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Calendars') }}</h1>

                <div class="fe-page__actions">
                    <el-button
                        type="primary"
                        @click="goToCreate"
                    >
                        <el-icon><Plus /></el-icon>
                        <span>{{ $t('Create Calendar') }}</span>
                    </el-button>
                </div>
            </div>

            <div v-if="!isOnboarding" class="fe-calendars__toolbar">
                <SegmentedSwitch
                    v-model="query.status"
                    class="fe-switch--compact"
                    :options="statuses"
                    :label="$t('Filter calendars by status')"
                    @change="fetchCalendars"
                />

                <div class="fe-calendars__toolbar-end">
                    <SearchInput
                        v-model="query.search"
                        class="fe-calendars__search"
                        :placeholder="$t('Search calendars')"
                        type="search_on_enter"
                        @search="fetchCalendars"
                    />

                    <SegmentedSwitch
                        v-model="view"
                        class="fe-switch--compact"
                        :options="views"
                        :label="$t('Switch between list and card view')"
                        @change="rememberView"
                    />
                </div>
            </div>

            <Skeleton v-if="loading && !fetched" type="calendar-list" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendars" />

            <div
                v-else-if="calendars.length"
                :class="{ 'fe-is-loading': loading }"
                :aria-busy="loading"
            >
                <section v-for="group in groups" :key="group.key" class="fe-calendars__group">
                    <div class="fe-calendars__group-head">
                        <img
                            v-if="group.key === 'community'"
                            class="fe-calendars__group-logo"
                            :src="appVars.fcom_logo"
                            width="16"
                            height="16"
                            alt=""
                        />
                        <h2 class="fe-calendars__group-title">{{ group.title }}</h2>
                        <span class="fe-calendars__group-count">{{ group.calendars.length }}</span>
                    </div>

                    <ul class="fe-calendars__list" :class="{ 'fe-calendars__list--cards': view === 'cards' }">
                        <CalendarRow
                            v-for="calendar in group.calendars"
                            :key="calendar.id"
                            :calendar="calendar"
                            @updated="fetchCalendars"
                        />
                    </ul>
                </section>
            </div>

            <EmptyState
                v-else-if="isFiltered"
                :title="$t('No calendars found')"
                :message="$t('No calendars match your search.')"
            />

            <EmptyState
                v-else
                :title="$t('Create your first calendar')"
                :message="$t('A calendar holds your events. You need one before you can add an event.')"
            >
                <template #action>
                    <el-button
                        type="primary"
                        @click="goToCreate"
                    >
                        {{ $t('Create Calendar') }}
                    </el-button>
                </template>
            </EmptyState>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { Grid, List, Plus } from '@element-plus/icons-vue';
    import SearchInput from '@/components/SearchInput.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import EmptyState from '@/components/EmptyState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import Storage from '@/utils/Storage';
    import CalendarRow from './parts/CalendarRow.vue';

    export default {
        name: 'Calendars',
        components: {
            Plus,
            SearchInput,
            ErrorState,
            EmptyState,
            Skeleton,
            SegmentedSwitch,
            CalendarRow,
        },
        data() {
            return {
                loading: false,
                fetched: false,
                error: '',
                calendars: [],
                counts: {},
                view: Storage.get('calendars_view') === 'cards' ? 'cards' : 'list',
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
            views() {
                return [
                    { value: 'list', label: this.$t('List'), icon: List },
                    { value: 'cards', label: this.$t('Cards'), icon: Grid },
                ];
            },
            groups() {
                return [
                    {
                        key: 'site',
                        title: this.$t('Site calendars'),
                        calendars: this.calendars.filter(calendar => !calendar.meta?.fcom_space_id && !calendar.meta?.fcom_global),
                    },
                    {
                        key: 'community',
                        title: this.$t('FluentCommunity Spaces'),
                        calendars: this.calendars.filter(calendar => calendar.meta?.fcom_space_id || calendar.meta?.fcom_global),
                    },
                ].filter(group => group.calendars.length);
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
            rememberView(view) {
                Storage.set('calendars_view', view);
            },
            goToCreate() {
                this.$router.push({ name: 'calendar_create' });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Calendars'));
            this.fetchCalendars();
        },
    };
</script>
