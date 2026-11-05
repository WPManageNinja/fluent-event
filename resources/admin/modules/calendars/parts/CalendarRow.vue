<template>
    <li class="fe-calendars__row">
        <div class="fe-calendars__row-main">
            <div class="fe-calendars__row-name">
                <span class="fe-calendars__swatch" :style="{ backgroundColor: themeColor }" aria-hidden="true"></span>
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
                <el-tag :type="$calendarStatusType(calendar.status)" size="small">
                    {{ $calendarStatusLabel(calendar.status) }}
                </el-tag>
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
    import { Calendar, Plus, User } from '@element-plus/icons-vue';
    import ActionPopover from '@/components/ActionPopover.vue';

    export default {
        name: 'CalendarRow',
        components: {
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
        computed: {
            actions() {
                return [
                    { label: this.$t('Edit'), action: () => this.goToEdit() },
                ];
            },
            themeColor() {
                return this.calendar.meta?.theme_color || 'var(--fe-secondary-border, #9ca3af)';
            },
        },
        methods: {
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
        },
    };
</script>
