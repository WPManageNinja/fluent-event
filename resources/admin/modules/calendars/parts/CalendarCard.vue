<template>
    <div class="fe-calendars__card">
        <div class="fe-calendars__card-head">
            <span class="fe-color-swatch" :style="{ background: themeColor }"></span>
            <h3 class="fe-calendars__card-title">{{ calendar.title }}</h3>
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
    export default {
        name: 'CalendarCard',
        props: {
            calendar: {
                type: Object,
                required: true,
            },
        },
        computed: {
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
    };
</script>
