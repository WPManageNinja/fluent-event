<template>
    <div class="fe-calendars__card">
        <div class="fe-calendars__card-head">
            <span class="fe-color-swatch" :style="{ background: themeColor }"></span>
            <h3 class="fe-calendars__card-title">
                <router-link
                    class="fe-calendars__card-link"
                    :to="{ name: 'calendar_detail', params: { id: calendar.id } }"
                >
                    {{ calendar.title }}
                </router-link>
            </h3>

            <ActionPopover
                v-if="$userCan('write_calendars')"
                :items="actions"
                placement="bottom-end"
            />
        </div>

        <p class="fe-calendars__card-slug">{{ calendar.slug }}</p>

        <div class="fe-calendars__card-meta">
            <el-tag class="capitalize" type="info" size="small">
                {{ $t(calendar.visibility) }}
            </el-tag>
            <el-tag class="capitalize" :type="statusType" size="small">
                {{ $t(calendar.status) }}
            </el-tag>
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
            statusType() {
                return {
                    active: 'success',
                    draft: 'info',
                    archived: 'danger',
                }[this.calendar.status] || 'info';
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
