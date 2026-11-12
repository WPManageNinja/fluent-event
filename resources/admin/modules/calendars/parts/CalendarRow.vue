<template>
    <li class="fe-calendars__row">
        <div class="fe-calendars__row-main">
            <div class="fe-calendars__row-name">
                <span class="fe-calendars__swatch" :style="{ backgroundColor: $calendarColor(calendar) }" aria-hidden="true"></span>
                <router-link
                    class="fe-calendars__row-link"
                    :title="calendar.title"
                    :to="{ name: 'calendar_detail', params: { id: calendar.id } }"
                >
                    {{ calendar.title }}
                </router-link>
            </div>
            <span class="fe-calendars__row-slug" :title="calendar.slug">{{ calendar.slug }}</span>
        </div>

        <div class="fe-calendars__row-stats">
            <span class="fe-calendars__row-status">
                <el-dropdown trigger="click" placement="bottom-start" @command="changeStatus">
                    <el-tag
                        class="fe-calendars__status-tag"
                        :type="$calendarStatusType(calendar.status)"
                        size="small"
                        :aria-label="$t('Change status of %s', calendar.title)"
                    >
                        {{ $calendarStatusLabel(calendar.status) }}
                        <el-icon class="fe-calendars__status-arrow" aria-hidden="true">
                            <ArrowDown />
                        </el-icon>
                    </el-tag>

                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item
                                v-for="option in $calendarStatuses()"
                                :key="option.value"
                                :command="option.value"
                                class="fe-calendars__status-option"
                                :class="{ 'fe-calendars__status-option--active': option.value === calendar.status }"
                            >
                                {{ option.label }}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </span>
            <span class="fe-calendars__row-stat">
                <el-icon aria-hidden="true"><Calendar /></el-icon>
                {{ $_n('%d event', '%d events', calendar.events_count) }}
            </span>
            <span class="fe-calendars__row-stat">
                <el-icon aria-hidden="true"><User /></el-icon>
                {{ $_n('%d guest', '%d guests', calendar.guests_count) }}
            </span>
        </div>

        <div class="fe-calendars__row-actions">
            <el-button size="small" @click="goToEventCreate">
                <el-icon><Plus /></el-icon>
                <span>{{ $t('Add Event') }}</span>
            </el-button>

            <ActionPopover
                :items="actions"
                :subject="calendar.title"
                placement="bottom-end"
            />
        </div>
    </li>
</template>

<script type="text/javascript">
    import { ArrowDown, Calendar, Plus, User } from '@element-plus/icons-vue';
    import ActionPopover from '@/components/ActionPopover.vue';

    export default {
        name: 'CalendarRow',
        components: {
            ArrowDown,
            Calendar,
            Plus,
            User,
            ActionPopover,
        },
        props: {
            calendar: {
                type: Object,
                required: true,
            },
        },
        emits: ['updated'],
        data() {
            return {
                saving: false,
            };
        },
        computed: {
            actions() {
                const actions = [
                    { label: this.$t('Edit'), action: () => this.goToEdit() },
                ];

                if (this.spaceHref) {
                    actions.push({ label: this.$t('View in Community'), action: () => this.openSpace() });
                }

                return actions;
            },
            spaceHref() {
                return /^https?:\/\//.test(this.calendar.space_url || '') ? this.calendar.space_url : '';
            },
        },
        methods: {
            // The update endpoint validates the whole calendar, so the unchanged fields go along.
            changeStatus(status) {
                if (status === this.calendar.status) return;

                this.$requestServer({
                    method: 'patch',
                    path: `calendars/${this.calendar.id}`,
                    payload: {
                        title: this.calendar.title,
                        slug: this.calendar.slug,
                        status,
                        theme_color: this.calendar.meta?.theme_color,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: () => this.$emit('updated'),
                });
            },
            goToEventCreate() {
                this.$router.push({
                    name: 'event_create',
                    query: { calendar_id: this.calendar.id },
                });
            },
            goToEdit() {
                this.$router.push({
                    name: 'calendar_edit',
                    params: { id: this.calendar.id },
                });
            },
            openSpace() {
                window.open(this.spaceHref, '_blank', 'noopener');
            },
        },
    };
</script>
