<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-venues">
            <div class="fe-page__header">
                <h1 class="fe-page__title">{{ $t('Venues') }}</h1>

                <div v-if="canWrite" class="fe-page__actions">
                    <el-button type="primary" @click="openCreate">
                        <el-icon><Plus /></el-icon>
                        <span>{{ $t('Add Venue') }}</span>
                    </el-button>
                </div>
            </div>

            <div class="fe-card">
                <SearchInput
                    v-if="!isOnboarding"
                    v-model="query.search"
                    class="fe-venues__search"
                    :placeholder="$t('Search venues')"
                    type="search_on_enter"
                    @search="search"
                />

                <Skeleton v-if="loading && !fetched" type="team_settings" :rows="4" />

                <ErrorState v-else-if="error" :message="error" @retry="fetchVenues" />

                <template v-else-if="venues.length">
                    <div
                        class="fe-venues__table"
                        :class="{ 'fe-is-loading': loading }"
                        role="table"
                        :aria-label="$t('Venues')"
                        :aria-busy="loading"
                    >
                        <div class="fe-venues__row fe-venues__row--head" role="row">
                            <span class="fe-venues__name" role="columnheader">{{ $t('Venue name') }}</span>
                            <span class="fe-venues__address" role="columnheader">{{ $t('Full address') }}</span>
                            <span v-if="canWrite" class="fe-venues__actions" role="columnheader">{{ $t('More') }}</span>
                        </div>

                        <div v-for="row in rows" :key="row.id" class="fe-venues__row" role="row">
                            <div class="fe-venues__name" role="cell">
                                <span class="fe-venues__name-text">{{ row.name }}</span>

                                <a
                                    v-if="row.mapUrl"
                                    class="fe-venues__map"
                                    :href="row.mapUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :aria-label="$t('Open map for %s', row.name)"
                                    :title="$t('Open map')"
                                >
                                    <el-icon aria-hidden="true"><MapLocation /></el-icon>
                                </a>
                            </div>

                            <p class="fe-venues__address" role="cell">{{ row.location }}</p>

                            <div v-if="canWrite" class="fe-venues__actions" role="cell">
                                <ActionPopover :items="row.actions" :subject="row.name" placement="bottom-end" />
                            </div>
                        </div>
                    </div>

                    <Pagination :pagination="pagination" hide_on_single @fetch="fetchVenues" />
                </template>

                <EmptyState
                    v-else-if="query.search"
                    :title="$t('No venues found')"
                    :message="$t('No venues match your search.')"
                />

                <EmptyState
                    v-else
                    :title="$t('No venues yet')"
                    :message="$t('Save a venue once, then pick it for any in-person event.')"
                >
                    <template v-if="canWrite" #action>
                        <el-button type="primary" @click="openCreate">{{ $t('Add Venue') }}</el-button>
                    </template>
                </EmptyState>
            </div>

            <VenueFormModal v-model="formOpen" :venue="activeVenue" @saved="onSaved" />
        </div>
    </transition>
</template>

<script type="text/javascript">
    import { MapLocation, Plus } from '@element-plus/icons-vue';
    import ActionPopover from '@/components/ActionPopover.vue';
    import EmptyState from '@/components/EmptyState.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Pagination from '@/components/Pagination.vue';
    import SearchInput from '@/components/SearchInput.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import VenueFormModal from './parts/VenueFormModal.vue';
    import { fullAddress, mapUrl } from '@/utils/venue';

    export default {
        name: 'Venues',
        components: {
            MapLocation,
            Plus,
            ActionPopover,
            EmptyState,
            ErrorState,
            Pagination,
            SearchInput,
            Skeleton,
            VenueFormModal,
        },
        data() {
            return {
                loading: false,
                fetched: false,
                deleting: false,
                error: '',
                venues: [],
                formOpen: false,
                activeVenue: null,
                query: {
                    search: '',
                },
                pagination: {
                    total: 0,
                    per_page: 100,
                    current_page: 1,
                },
            };
        },
        computed: {
            canWrite() {
                return this.$userCan('write_venues');
            },
            isOnboarding() {
                return this.fetched && !this.loading && !this.error && !this.venues.length && !this.query.search;
            },
            rows() {
                return this.venues.map(venue => {
                    const row = {
                        ...venue,
                        location: fullAddress(venue),
                        mapUrl: mapUrl(venue),
                    };

                    row.actions = [
                        { label: this.$t('Edit'), action: () => this.openEdit(row) },
                        { label: this.$t('Delete'), type: 'danger', action: () => this.confirmDelete(row) },
                    ];

                    return row;
                });
            },
        },
        methods: {
            fetchVenues() {
                this.$requestServer({
                    path: 'venues',
                    payload: {
                        search: this.query.search,
                        page: this.pagination.current_page,
                        per_page: this.pagination.per_page,
                    },
                    onSuccess: response => {
                        this.fetched = true;
                        this.venues = response.venues.data;
                        this.pagination.total = response.venues.total;
                    },
                    onError: () => {
                        this.venues = [];
                    },
                });
            },
            search() {
                this.pagination.current_page = 1;
                this.fetchVenues();
            },
            openCreate() {
                this.activeVenue = null;
                this.formOpen = true;
            },
            openEdit(row) {
                this.activeVenue = row;
                this.formOpen = true;
            },
            onSaved(venue) {
                const index = this.venues.findIndex(item => item.id === venue.id);

                if (index !== -1) {
                    this.venues.splice(index, 1, venue);
                } else {
                    this.fetchVenues();
                }
            },
            confirmDelete(row) {
                this.$confirm(
                    this.$t('%s will be deleted. Events that use it keep its name and address.', row.name),
                    this.$t('Delete venue'),
                    {
                        type: 'warning',
                        confirmButtonText: this.$t('Delete'),
                        cancelButtonText: this.$t('Cancel'),
                    }
                )
                    .then(() => this.deleteVenue(row))
                    .catch(() => {});
            },
            deleteVenue(row) {
                this.$requestServer({
                    method: 'delete',
                    path: `venues/${row.id}`,
                    loadingState: 'deleting',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: () => {
                        if (this.venues.length === 1 && this.pagination.current_page > 1) {
                            this.pagination.current_page -= 1;
                        }

                        this.fetchVenues();
                    },
                });
            },
        },
        mounted() {
            this.$changeTitle(this.$t('Venues'));
            this.fetchVenues();
        },
    };
</script>
