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

        <el-empty v-else-if="!rows.length" :description="emptyMessage" />

        <template v-else>
            <div
                class="fe-guests__table"
                :class="{ 'fe-is-loading': loading }"
                role="table"
                :aria-label="$t('Guests')"
                :aria-busy="loading"
            >
                <div class="fe-guests__row fe-guests__row--head" role="row">
                    <span class="fe-guests__select" role="columnheader">
                        <el-checkbox
                            :model-value="allSelected"
                            :indeterminate="!allSelected && selectedIds.length > 0"
                            :aria-label="$t('Select all guests on this page')"
                            @change="toggleAll"
                        />
                    </span>

                    <!-- Takes the column titles' place, so selecting never shifts the table. -->
                    <div v-if="selectedIds.length" class="fe-guests__bulk" role="columnheader">
                        <span class="fe-guests__bulk-count" role="status">
                            {{ $_n('%d guest selected', '%d guests selected', selectedIds.length) }}
                        </span>

                        <el-button
                            class="fe-guests__clear"
                            link
                            :aria-label="$t('Clear selection')"
                            :title="$t('Clear selection')"
                            @click="selectedIds = []"
                        >
                            <el-icon aria-hidden="true"><Close /></el-icon>
                        </el-button>

                        <div class="fe-guests__bulk-actions">
                            <ActionPopover :width="160" placement="bottom-start">
                                <template #trigger="{ visible }">
                                    <el-button
                                        class="fe-guests__bulk-action"
                                        size="small"
                                        type="primary"
                                        aria-haspopup="menu"
                                        :aria-expanded="visible"
                                    >
                                        {{ $t('Change status') }}
                                    </el-button>
                                </template>

                                <template #default="{ hide }">
                                    <ul class="fe-action-popover__menu" role="menu">
                                        <li
                                            v-for="option in statuses"
                                            :key="option.value"
                                            class="fe-action-popover__item"
                                            role="menuitem"
                                            tabindex="0"
                                            @click="openBulk({ action: 'change_status', status: option.value, label: option.label }, hide)"
                                            @keydown.enter.prevent="openBulk({ action: 'change_status', status: option.value, label: option.label }, hide)"
                                        >
                                            {{ option.label }}
                                        </li>
                                    </ul>
                                </template>
                            </ActionPopover>

                            <el-button class="fe-guests__bulk-action" size="small" type="danger" @click="openBulk({ action: 'delete' })">
                                {{ $t('Delete') }}
                            </el-button>
                        </div>
                    </div>

                    <template v-else>
                        <span class="fe-guests__serial" role="columnheader">#</span>
                        <span class="fe-guests__avatar" aria-hidden="true"></span>
                        <span class="fe-guests__name" role="columnheader">{{ $t('Name') }}</span>
                        <span class="fe-guests__email" role="columnheader">{{ $t('Email') }}</span>
                        <span class="fe-guests__source" role="columnheader">{{ $t('Source') }}</span>
                        <span class="fe-guests__stage" role="columnheader">{{ $t('Status') }}</span>
                        <span class="fe-guests__actions" role="columnheader">{{ $t('More') }}</span>
                    </template>
                </div>

                <div v-for="row in rows" :key="row.id" class="fe-guests__row" role="row">
                    <span class="fe-guests__select" role="cell">
                        <el-checkbox
                            :model-value="selectedIds.includes(row.id)"
                            :aria-label="$t('Select %s', row.name)"
                            @change="toggleGuest(row.id)"
                        />
                    </span>
                    <span class="fe-guests__serial" role="cell">{{ row.serial }}</span>

                    <!-- Hidden like its header column: a row may only own cells, and the name cell follows. -->
                    <img
                        v-if="row.avatar"
                        class="fe-guests__avatar"
                        :src="row.avatar"
                        :alt="row.name"
                        aria-hidden="true"
                    />

                    <span v-else class="fe-guests__avatar fe-guests__avatar--empty" aria-hidden="true">
                        {{ row.initial }}
                    </span>

                    <p class="fe-guests__name" role="cell">{{ row.name }}</p>

                    <p class="fe-guests__email" role="cell">{{ row.email }}</p>

                    <p class="fe-guests__source" role="cell">{{ row.sourceLabel }}</p>

                    <div class="fe-guests__stage" role="cell">
                        <ActionPopover :width="160" placement="bottom-end">
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
                                        class="fe-action-popover__item fe-guests__status-option"
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

                    <div class="fe-guests__actions" role="cell">
                        <ActionPopover
                            :items="row.actions"
                            :subject="row.name"
                            placement="bottom-end"
                        />
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

        <GuestDeleteModal
            v-model="deleteOpen"
            :event-id="eventId"
            :guest="activeGuest"
            @deleted="onGuestDeleted"
        />

        <GuestBulkModal
            v-model="bulkOpen"
            :event-id="eventId"
            :guest-ids="selectedIds"
            :bulk="bulk"
            @done="onBulkDone"
        />
    </div>
</template>

<script type="text/javascript">
    import { ArrowDown, Close } from '@element-plus/icons-vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';
    import SearchInput from '@/components/SearchInput.vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import ActionPopover from '@/components/ActionPopover.vue';
    import Pagination from '@/components/Pagination.vue';
    import GuestFormModal from './GuestFormModal.vue';
    import GuestDetailsModal from './GuestDetailsModal.vue';
    import GuestDeleteModal from './GuestDeleteModal.vue';
    import GuestBulkModal from './GuestBulkModal.vue';

    export default {
        name: 'EventGuests',
        components: {
            ArrowDown,
            Close,
            ErrorState,
            Skeleton,
            SearchInput,
            SegmentedSwitch,
            ActionPopover,
            Pagination,
            GuestFormModal,
            GuestDetailsModal,
            GuestDeleteModal,
            GuestBulkModal,
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
                deleteOpen: false,
                bulkOpen: false,
                bulk: null,
                selectedIds: [],
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
            allSelected() {
                return this.guests.length > 0 && this.guests.every(guest => this.selectedIds.includes(guest.id));
            },
            emptyMessage() {
                return this.query.search || this.query.status
                    ? this.$t('No guests match your search or filter.')
                    : this.$t('No guests yet.');
            },
            statusLabels() {
                return {
                    going: this.$t('Going'),
                    not_going: this.$t('Not Going'),
                    checked_in: this.$t('Checked In'),
                    waitlisted: this.$t('Waitlisted'),
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

                    const row = {
                        ...guest,
                        serial: offset + index + 1,
                        checkedInAt: checkedIn
                            ? this.$t('at: %s', this.$formatDateTime(guest.checked_in_at))
                            : '',
                        initial: (guest.name || guest.email || '?').charAt(0).toUpperCase(),
                        sourceLabel: guest.source ? this.$guestSourceLabel(guest.source) : '—',
                        statusLabel: this.statusLabels[guest.status] || guest.status,
                        tagType: {
                            going: 'success',
                            not_going: 'info',
                            checked_in: 'primary',
                            waitlisted: 'warning',
                            cancelled: 'danger',
                        }[guest.status] || 'info',
                    };

                    row.actions = this.actionsFor(row);

                    return row;
                });
            },
        },
        methods: {
            actionsFor(row) {
                return [
                    { label: this.$t('View'), action: () => this.openDetails(row) },
                    { label: this.$t('Edit'), action: () => this.openEdit(row) },
                    { label: this.$t('Delete'), type: 'danger', action: () => this.promptDelete(row) },
                ];
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
                    payload: { status },
                    loadingState: 'mutating',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => this.onGuestSaved(response.guest),
                });
            },
            // No refetch: the tab counts move with the saved guest, and its row stays in the current tab until the next load.
            onGuestSaved(guest) {
                const index = this.guests.findIndex(item => item.id === guest.id);
                const previous = index !== -1 ? this.guests[index].status : null;

                if (index !== -1) {
                    this.guests.splice(index, 1, guest);
                } else {
                    this.guests.unshift(guest);
                    this.counts.all = (this.counts.all || 0) + 1;
                }

                if (previous !== guest.status) {
                    if (previous) {
                        this.counts[previous] = Math.max(0, (this.counts[previous] || 0) - 1);
                    }
                    this.counts[guest.status] = (this.counts[guest.status] || 0) + 1;
                }
            },
            toggleGuest(id) {
                this.selectedIds = this.selectedIds.includes(id)
                    ? this.selectedIds.filter(selected => selected !== id)
                    : [...this.selectedIds, id];
            },
            toggleAll() {
                this.selectedIds = this.allSelected ? [] : this.guests.map(guest => guest.id);
            },
            openBulk(bulk, hide) {
                hide?.();
                this.bulk = bulk;
                this.bulkOpen = true;
            },
            // A status change updates the rows in place, as the inline change does; a delete has rows to drop, so it reloads.
            onBulkDone(response) {
                if (this.bulk.action === 'delete') {
                    this.fetchGuests();
                    return;
                }

                (response.guests || []).forEach(guest => this.onGuestSaved(guest));
                this.selectedIds = [];
            },
            promptDelete(row) {
                this.activeGuest = row;
                this.deleteOpen = true;
            },
            onGuestDeleted() {
                this.goToLastPageIfEmptied();
                this.fetchGuests();
            },
            goToLastPageIfEmptied() {
                if (this.guests.length === 1 && this.pagination.current_page > 1) {
                    this.pagination.current_page -= 1;
                }
            },
            // Selection is per page: a new page, filter or search starts from none.
            fetchGuests() {
                this.selectedIds = [];

                this.$requestServer({
                    path: `events/${this.eventId}/guests`,
                    payload: {
                        search: this.query.search,
                        status: this.query.status,
                        page: this.pagination.current_page,
                        per_page: this.pagination.per_page,
                    },
                    onSuccess: response => {
                        // A bulk delete, or a status change out of the active filter, can empty the last page.
                        const lastPage = Math.max(1, Math.ceil(response.guests.total / response.guests.per_page));

                        if (!response.guests.data.length && this.pagination.current_page > lastPage) {
                            this.pagination.current_page = lastPage;
                            this.fetchGuests();
                            return;
                        }

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
