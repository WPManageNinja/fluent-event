<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-calendars">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Calendars') }}</h1>

                <div class="fe-page__actions">
                    <el-button type="primary" @click="goToCreate">
                        {{ $t('Create Calendar') }}
                    </el-button>
                </div>
            </div>

            <div v-if="!isOnboarding" class="fe-calendars__toolbar">
                <el-input
                    v-model="query.search"
                    class="fe-calendars__search"
                    :placeholder="$t('Search calendars')"
                    clearable
                    @clear="refetch"
                    @keyup.enter="refetch"
                />

                <el-popover
                    v-model:visible="filterOpen"
                    :width="220"
                    placement="bottom-end"
                    trigger="click"
                    @show="syncFilters"
                >
                    <template #reference>
                        <el-button :aria-label="$t('Filter')" :title="$t('Filter')">
                            <el-icon><Filter /></el-icon>
                        </el-button>
                    </template>

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

                    <el-button type="primary" size="small" @click="applyFilters">
                        {{ $t('Apply') }}
                    </el-button>
                </el-popover>
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

            <el-row v-if="loading" :gutter="20">
                <el-col v-for="i in 6" :key="i" :xs="24" :sm="12" :md="8">
                    <div class="fe-calendars__card">
                        <el-skeleton :rows="2" animated />
                    </div>
                </el-col>
            </el-row>

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

                <Pagination :pagination="pagination" hide_on_single @fetch="fetchCalendars" />
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
    import { Filter } from '@element-plus/icons-vue';
    import Pagination from '@/components/Pagination.vue';
    import CalendarCard from './parts/CalendarCard.vue';

    export default {
        name: 'Calendars',
        components: {
            Filter,
            Pagination,
            CalendarCard,
        },
        data() {
            return {
                loading: true,
                filterOpen: false,
                calendars: [],
                pagination: {
                    total: 0,
                    per_page: 12,
                    current_page: 1,
                },
                query: {
                    search: '',
                    status: '',
                    visibility: '',
                },
                appliedSearch: '',
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
                return !!this.appliedSearch || !!this.activeFilters.length;
            },
            isOnboarding() {
                return !this.loading && !this.calendars.length && !this.isFiltered;
            },
        },
        methods: {
            fetchCalendars() {
                this.loading = true;
                this.appliedSearch = this.query.search;
                this.$get('calendars', {
                    page: this.pagination.current_page,
                    per_page: this.pagination.per_page,
                    search: this.query.search,
                    status: this.query.status,
                    visibility: this.query.visibility,
                })
                    .then(response => {
                        this.calendars = response.calendars.data;
                        this.pagination.total = response.calendars.total;
                        // The server clamps per_page, so take its word for it —
                        // otherwise the pager counts pages for a size that was
                        // never served.
                        this.pagination.per_page = +response.calendars.per_page;
                    })
                    .catch(this.$handleError)
                    .finally(() => {
                        this.loading = false;
                    });
            },
            refetch() {
                this.pagination.current_page = 1;
                this.fetchCalendars();
            },
            syncFilters() {
                this.pendingFilters = {
                    status: this.query.status,
                    visibility: this.query.visibility,
                };
            },
            applyFilters() {
                Object.assign(this.query, this.pendingFilters);
                this.filterOpen = false;
                this.refetch();
            },
            removeFilter(key) {
                this.query[key] = '';
                this.pendingFilters[key] = '';
                this.refetch();
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
