<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendars">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Calendars') }}</h1>

                <div class="fe-page__actions">
                    <el-button
                        :aria-label="$t('Refresh')"
                        :title="$t('Refresh')"
                        @click="fetchCalendars"
                    >
                        <el-icon><Refresh /></el-icon>
                    </el-button>

                    <el-button type="primary" @click="goToCreate">
                        {{ $t('Create Calendar') }}
                    </el-button>
                </div>
            </div>

            <div v-if="!isOnboarding" class="fe-calendars__toolbar">
                <SearchInput
                    v-model="query.search"
                    class="fe-calendars__search"
                    :placeholder="$t('Search calendars')"
                    type="search_on_enter"
                    @search="fetchCalendars"
                />

                <ActionPopover :width="220" placement="bottom-end" @show="syncFilters">
                    <template #trigger>
                        <el-button :aria-label="$t('Filter')" :title="$t('Filter')">
                            <el-icon><Filter /></el-icon>
                        </el-button>
                    </template>

                    <template #default="{ hide }">
                        <div class="fe-calendars__filters">
                            <el-form label-position="top">
                                <el-form-item :label="$t('Status')">
                                    <el-radio-group
                                        v-model="pendingFilters.status"
                                        class="fe-calendars__filter"
                                    >
                                        <el-radio
                                            v-for="option in statuses"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </el-radio>
                                    </el-radio-group>
                                </el-form-item>

                                <el-form-item :label="$t('Visibility')">
                                    <el-radio-group
                                        v-model="pendingFilters.visibility"
                                        class="fe-calendars__filter"
                                    >
                                        <el-radio
                                            v-for="option in visibilities"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </el-radio>
                                    </el-radio-group>
                                </el-form-item>
                            </el-form>

                            <el-button type="primary" size="small" @click="applyFilters(hide)">
                                {{ $t('Apply') }}
                            </el-button>
                        </div>
                    </template>
                </ActionPopover>
            </div>

            <div v-if="activeFilters.length" class="fe-calendars__chips">
                <el-tag
                    v-for="filter in activeFilters"
                    :key="filter.key"
                    type="info"
                    size="small"
                    closable
                    @close="removeFilter(filter.key)"
                >
                    {{ $t('%s: %s', filter.label, filter.value) }}
                </el-tag>
            </div>

            <Skeleton v-if="loading" type="calendar-grid" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendars" />

            <template v-else-if="calendars.length">
                <el-row :gutter="20">
                    <el-col
                        v-for="calendar in calendars"
                        :key="calendar.id"
                        :xs="24"
                        :sm="12"
                        :md="8"
                    >
                        <CalendarCard :calendar="calendar" />
                    </el-col>
                </el-row>
            </template>

            <el-empty
                v-else-if="isFiltered"
                :description="$t('No calendars match your search.')"
            />

            <div v-else class="fe-calendars__onboarding">
                <h2 class="fe-calendars__onboarding-title">
                    {{ $t('Create your first calendar') }}
                </h2>
                <p class="fe-calendars__onboarding-text">
                    {{ $t('A calendar holds your events. You need one before you can add an event.') }}
                </p>
                <el-button type="primary" @click="goToCreate">
                    {{ $t('Create Calendar') }}
                </el-button>
            </div>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { Filter, Refresh } from '@element-plus/icons-vue';
    import SearchInput from '@/components/SearchInput.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import ActionPopover from '@/components/ActionPopover.vue';
    import CalendarCard from './parts/CalendarCard.vue';

    export default {
        name: 'Calendars',
        components: {
            Filter,
            Refresh,
            SearchInput,
            ErrorState,
            Skeleton,
            ActionPopover,
            CalendarCard,
        },
        data() {
            return {
                loading: false,
                error: '',
                calendars: [],
                query: {
                    search: '',
                    status: '',
                    visibility: '',
                },
                pendingFilters: {
                    status: '',
                    visibility: '',
                },
            };
        },
        computed: {
            statuses() {
                return [
                    { value: '', label: this.$t('All') },
                    { value: 'active', label: this.$t('Active') },
                    { value: 'draft', label: this.$t('Draft') },
                    { value: 'archived', label: this.$t('Archived') },
                ];
            },
            visibilities() {
                return [
                    { value: '', label: this.$t('All') },
                    { value: 'public', label: this.$t('Public') },
                    { value: 'private', label: this.$t('Private') },
                    { value: 'unlisted', label: this.$t('Unlisted') },
                ];
            },
            activeFilters() {
                const groups = {
                    status: { label: this.$t('Status'), options: this.statuses },
                    visibility: { label: this.$t('Visibility'), options: this.visibilities },
                };

                // To render the filter chips (badge)
                return Object.keys(groups)
                    .filter(key => this.query[key])
                    .map(key => ({
                        key,
                        label: groups[key].label,
                        value: groups[key].options.find(
                            option => option.value === this.query[key]
                        ).label,
                    }));
            },
            isFiltered() {
                return !!this.query.search || !!this.activeFilters.length;
            },
            isOnboarding() {
                return !this.loading && !this.error && !this.calendars.length && !this.isFiltered;
            },
        },
        methods: {
            fetchCalendars() {
                this.$requestServer({
                    path: 'calendars',
                    payload: {
                        search: this.query.search,
                        status: this.query.status,
                        visibility: this.query.visibility,
                    },
                    onSuccess: response => {
                        this.calendars = response.calendars;
                    },
                    onError: () => {
                        this.calendars = [];
                    },
                });
            },
            syncFilters() {
                this.pendingFilters = {
                    status: this.query.status,
                    visibility: this.query.visibility,
                };
            },
            applyFilters(hide) {
                Object.assign(this.query, this.pendingFilters);
                hide();
                this.fetchCalendars();
            },
            removeFilter(key) {
                this.query[key] = '';
                this.pendingFilters[key] = '';
                this.fetchCalendars();
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
