<template>
    <div class="fe-calendars__card">
        <div class="fe-calendars__card-cover" :style="{ color: themeColor }">
            <el-tag
                v-if="calendar.status !== 'active'"
                class="capitalize fe-calendars__card-status"
                :type="$calendarStatusType(calendar.status)"
                size="small"
            >
                {{ $t(calendar.status) }}
            </el-tag>

            <span class="fe-calendars__card-mark" aria-hidden="true">{{ initials }}</span>
        </div>

        <div class="fe-calendars__card-body">
            <div class="fe-calendars__card-head">
                <h3 class="fe-calendars__card-title">
                    <router-link
                        class="fe-calendars__card-link"
                        :title="calendar.title"
                        :to="{ name: 'calendar_detail', params: { id: calendar.id } }"
                    >
                        {{ calendar.title }}
                    </router-link>
                </h3>

                <ActionPopover
                    v-if="$userCan('write_calendars')"
                    :items="actions"
                    :subject="calendar.title"
                    placement="bottom-end"
                />
            </div>

            <p class="fe-calendars__card-slug" :title="calendar.slug">{{ calendar.slug }}</p>

            <div class="fe-calendars__card-meta">
                <span class="fe-calendars__card-stat">
                    {{ $t('%d events', calendar.events_count) }}
                </span>
                <span class="fe-calendars__card-stat">
                    {{ $t('%d guests', calendar.guests_count) }}
                </span>
            </div>
        </div>
    </div>
</template>

<script type="text/javascript">
    import ActionPopover from '@/components/ActionPopover.vue';

    export default {
        name: 'CalendarCard',
        components: {
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
            initials() {
                return (this.calendar.title || '').trim().slice(0, 2);
            },
        },
        methods: {
            goToEdit() {
                this.$router.push({
                    name: 'calendar_edit',
                    params: { id: this.calendar.id },
                });
            },
        },
    };
</script>
