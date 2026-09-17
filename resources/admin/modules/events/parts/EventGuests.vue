<template>
    <div class="fe-guests">
        <div class="fe-guests__toolbar">
            <div class="fe-guests__toolbar-row">
                <SearchInput
                    v-model="query.search"
                    class="fe-guests__search"
                    :placeholder="$t('Search guests')"
                    type="search_on_enter"
                    @search="refetch"
                />

                <el-button
                    v-if="$userCan('write_guests')"
                    type="primary"
                    @click="openCreate"
                >
                    {{ $t('Add Guest') }}
                </el-button>
            </div>

            <SegmentedSwitch
                v-model="query.status"
                class="fe-switch--compact"
                :options="stages"
                :label="$t('Filter guests by stage')"
                @change="refetch"
            />
        </div>

        <Skeleton v-if="loading && !fetched" type="guests" />

        <ErrorState v-else-if="error" :message="error" @retry="fetchGuests" />

        <el-empty v-else-if="!rows.length" :description="$t('No guests yet.')" />

        <template v-else>
            <div class="fe-guests__table" :class="{ 'fe-is-loading': loading }">
                <div class="fe-guests__row fe-guests__row--head">
                    <span class="fe-guests__serial">#</span>
                    <span class="fe-guests__avatar" aria-hidden="true"></span>
                    <span class="fe-guests__name">{{ $t('Name') }}</span>
                    <span class="fe-guests__email">{{ $t('Email') }}</span>
                    <span class="fe-guests__source">{{ $t('Source') }}</span>
                    <span class="fe-guests__stage">{{ $t('Status') }}</span>
                    <span class="fe-guests__actions">{{ $t('More') }}</span>
                </div>

                <div v-for="row in rows" :key="row.id" class="fe-guests__row">
                    <span class="fe-guests__serial">{{ row.serial }}</span>

                    <img
                        v-if="row.avatar"
                        class="fe-guests__avatar"
                        :src="row.avatar"
                        :alt="row.name"
                    />

                    <span v-else class="fe-guests__avatar fe-guests__avatar--empty" aria-hidden="true">
                        {{ row.initial }}
                    </span>

                    <p class="fe-guests__name">{{ row.name }}</p>

                    <p class="fe-guests__email">{{ row.email }}</p>

                    <p class="fe-guests__source">{{ row.sourceLabel }}</p>

                    <div class="fe-guests__stage">
                        <el-tag
                            v-if="!$userCan('write_guests')"
                            class="fe-guests__tag"
                            :type="row.tagType"
                            size="small"
                        >
                            {{ row.statusLabel }}
                        </el-tag>

                        <ActionPopover v-else :width="160" placement="bottom-end">
                            <template #trigger>
                                <el-tag
                                    class="fe-guests__tag"
                                    :type="row.tagType"
                                    size="small"
                                    :aria-label="$t('Change status of %s', row.name)"
                                >
                                    {{ row.statusLabel }}
                                    <el-icon class="fe-guests__tag-arrow" aria-hidden="true">
                                        <ArrowDown />
                                    </el-icon>
                                </el-tag>
                            </template>

                            <template #default="{ hide }">
                                <ul class="fe-action-popover__menu" role="menu">
                                    <li
                                        v-for="option in statuses"
                                        :key="option.value"
                                        class="fe-action-popover__item"
                                        :class="{
                                            'fe-guests__status-option--active': option.value === row.status
                                        }"
                                        role="menuitem"
                                        tabindex="0"
                                        @click="changeStatus(row, option.value, hide)"
                                        @keydown.enter.prevent="changeStatus(row, option.value, hide)"
                                    >
                                        {{ option.label }}
                                    </li>
                                </ul>
                            </template>
                        </ActionPopover>

                        <span v-if="row.checkedInAt" class="fe-guests__time">
                            {{ row.checkedInAt }}
                        </span>
                    </div>

                    <div class="fe-guests__actions">
                        <ActionPopover :items="actionsFor(row)" placement="bottom-end" />
                    </div>
                </div>
            </div>

            <Pagination :pagination="pagination" hide_on_single @fetch="fetchGuests" />
        </template>

        <GuestFormModal
            v-model="formOpen"
            :event-id="eventId"
            :guest="activeGuest"
            :statuses="statuses"
            @saved="onGuestSaved"
        />

        <GuestDetailsModal v-model="detailsOpen" :guest="activeGuest" />
    </div>
</template>

<script type="text/javascript">
    import { ArrowDown } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import SearchInput from '@/components/SearchInput.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import ActionPopover from '@/components/ActionPopover.vue';
    import Pagination from '@/components/Pagination.vue';
    import GuestFormModal from './GuestFormModal.vue';
    import GuestDetailsModal from './GuestDetailsModal.vue';

    export default {
        name: 'EventGuests',
        components: {
            ArrowDown,
            ErrorState,
            Skeleton,
            SearchInput,
            SegmentedSwitch,
            ActionPopover,
            Pagination,
            GuestFormModal,
            GuestDetailsModal,
        },
        props: {
            eventId: {
                type: [Number, String],
                required: true,
            },
        },
        data() {
            return {
                loading: false,
                fetched: false,
                error: '',
                guests: [],
                counts: {},
                activeGuest: null,
                formOpen: false,
                detailsOpen: false,
                mutating: false,
                query: {
                    search: '',
                    status: '',
                },
                pagination: {
                    total: 0,
                    per_page: 20,
                    current_page: 1,
                },
            };
        },
        computed: {
            statusLabels() {
                return {
                    going: this.$t('Going'),
                    not_going: this.$t('Not Going'),
                    checked_in: this.$t('Checked In'),
                    cancelled: this.$t('Cancelled'),
                };
            },
            statuses() {
                return Object.keys(this.statusLabels).map(status => ({
                    value: status,
                    label: this.statusLabels[status],
                }));
            },
            stages() {
                const stages = [
                    { value: '', label: this.$t('All'), count: this.counts.all },
                ];

                this.statuses.forEach(status => {
                    stages.push({
                        value: status.value,
                        label: status.label,
                        count: this.counts[status.value],
                    });
                });

                return stages.map(stage => ({
                    value: stage.value,
                    label: this.$t('%s (%d)', stage.label, stage.count || 0),
                }));
            },
            rows() {
                const offset = (this.pagination.current_page - 1) * this.pagination.per_page;

                return this.guests.map((guest, index) => {
                    const checkedIn = guest.status === 'checked_in' && guest.checked_in_at;

                    return {
                        ...guest,
                        serial: offset + index + 1,
                        checkedInAt: checkedIn
                            ? this.$t('at: %s', this.$formatDateTime(guest.checked_in_at))
                            : '',
                        initial: (guest.name || guest.email || '?').charAt(0).toUpperCase(),
                        sourceLabel: guest.source ? this.$titleCase(guest.source) : '—',
                        statusLabel: this.statusLabels[guest.status] || guest.status,
                        tagType: {
                            going: 'success',
                            not_going: 'info',
                            checked_in: 'primary',
                            cancelled: 'danger',
                        }[guest.status] || 'info',
                    };
                });
            },
        },
        methods: {
            actionsFor(row) {
                const actions = [
                    { label: this.$t('View'), action: () => this.openDetails(row) },
                ];

                if (this.$userCan('write_guests')) {
                    actions.push(
                        { label: this.$t('Edit'), action: () => this.openEdit(row) },
                        { label: this.$t('Delete'), type: 'danger', action: () => this.promptDelete(row) },
                    );
                }

                return actions;
            },
            openCreate() {
                this.activeGuest = null;
                this.formOpen = true;
            },
            openEdit(row) {
                this.activeGuest = row;
                this.formOpen = true;
            },
            openDetails(row) {
                this.activeGuest = row;
                this.detailsOpen = true;
            },
            changeStatus(row, status, hide) {
                hide();
                if (status === row.status) return;

                this.$requestServer({
                    method: 'patch',
                    path: `events/${this.eventId}/guests/${row.id}`,
                    payload: {
                        name: row.name,
                        email: row.email,
                        status,
                    },
                    loadingState: 'mutating',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.onGuestSaved(response.guest);
                    },
                });
            },
            onGuestSaved(guest) {
                const index = this.guests.findIndex(item => item.id === guest.id);

                if (index !== -1) {
                    this.guests.splice(index, 1, guest);
                } else {
                    this.guests.unshift(guest);
                }
            },
            promptDelete(row) {
                this.$confirm(
                    this.$t('%s will be removed from this event. This cannot be undone.', row.name),
                    this.$t('Delete Guest'),
                    {
                        type: 'warning',
                        confirmButtonText: this.$t('Delete'),
                        cancelButtonText: this.$t('Cancel'),
                    }
                )
                    .then(() => this.deleteGuest(row))
                    .catch(() => {});
            },
            deleteGuest(row) {
                this.$requestServer({
                    method: 'delete',
                    path: `events/${this.eventId}/guests/${row.id}`,
                    loadingState: 'mutating',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: () => {
                        this.goToLastPageIfEmptied();
                        this.fetchGuests();
                    },
                });
            },
            goToLastPageIfEmptied() {
                if (this.guests.length === 1 && this.pagination.current_page > 1) {
                    this.pagination.current_page -= 1;
                }
            },
            fetchGuests() {
                this.$requestServer({
                    path: `events/${this.eventId}/guests`,
                    payload: {
                        search: this.query.search,
                        status: this.query.status,
                        page: this.pagination.current_page,
                        per_page: this.pagination.per_page,
                    },
                    onSuccess: response => {
                        this.fetched = true;
                        this.guests = response.guests.data;
                        this.counts = response.counts;
                        this.pagination.total = response.guests.total;
                        this.pagination.per_page = +response.guests.per_page;
                    },
                    onError: () => {
                        this.guests = [];
                    },
                });
            },
            refetch() {
                this.pagination.current_page = 1;
                this.fetchGuests();
            },
        },
        mounted() {
            this.fetchGuests();
        },
    };
</script>
