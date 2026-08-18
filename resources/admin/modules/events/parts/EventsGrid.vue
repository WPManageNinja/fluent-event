<template>
    <div class="fe-events-grid">
        <el-calendar v-model="selectedDate">
            <template #header>
                <h2 class="fe-events-grid__month">{{ monthLabel }}</h2>

                <el-button-group>
                    <el-button
                        :aria-label="$t('Previous month')"
                        :title="$t('Previous month')"
                        @click="moveMonth(-1)"
                    >
                        <el-icon><ArrowLeft /></el-icon>
                    </el-button>

                    <el-button @click="moveToday">{{ $t('Today') }}</el-button>

                    <el-button
                        :aria-label="$t('Next month')"
                        :title="$t('Next month')"
                        @click="moveMonth(1)"
                    >
                        <el-icon><ArrowRight /></el-icon>
                    </el-button>
                </el-button-group>
            </template>

            <template #date-cell="{ data }">
                <div
                    class="fe-events-grid__cell"
                    :class="{ 'fe-events-grid__cell--outside': data.type !== 'current-month' }"
                >
                    <span class="fe-events-grid__day">{{ dayNumber(data.day) }}</span>

                    <div class="fe-events-grid__entries">
                        <el-tooltip
                            v-for="entry in visibleEntries(data.day)"
                            :key="entry.event.id"
                            :content="entry.event.title"
                            placement="top"
                            :show-after="300"
                        >
                            <button
                                type="button"
                                class="fe-events-grid__event"
                                :style="{ '--fe-event-color': entryColor(entry) }"
                                :aria-label="$t('View %s', entry.event.title)"
                                @click="$emit('open', entry.event)"
                            >
                                <span class="fe-events-grid__event-time">
                                    {{ entryTime(entry) }}
                                </span>
                                <span class="fe-events-grid__event-title">
                                    {{ entry.event.title }}
                                </span>
                            </button>
                        </el-tooltip>

                        <el-popover
                            v-if="hiddenEntries(data.day).length"
                            :width="260"
                            trigger="click"
                            placement="right"
                            popper-class="fe-events-grid__popover"
                        >
                            <template #reference>
                                <button type="button" class="fe-events-grid__more">
                                    {{ $t('%d more', hiddenEntries(data.day).length) }}
                                </button>
                            </template>

                            <template #default>
                                <p class="fe-events-grid__popover-title">
                                    {{ $formatDate(data.day, 'dddd, MMM D') }}
                                </p>

                                <el-tooltip
                                    v-for="entry in entriesFor(data.day)"
                                    :key="entry.event.id"
                                    :content="entry.event.title"
                                    placement="top"
                                    :show-after="300"
                                >
                                    <button
                                        type="button"
                                        class="fe-events-grid__event"
                                        :style="{ '--fe-event-color': entryColor(entry) }"
                                        :aria-label="$t('View %s', entry.event.title)"
                                        @click="$emit('open', entry.event)"
                                    >
                                        <span class="fe-events-grid__event-time">
                                            {{ entryTime(entry) }}
                                        </span>
                                        <span class="fe-events-grid__event-title">
                                            {{ entry.event.title }}
                                        </span>
                                    </button>
                                </el-tooltip>
                            </template>
                        </el-popover>
                    </div>
                </div>
            </template>
        </el-calendar>
    </div>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
    import { DAY_MS, toDayKey, toUtcDay } from '@/utils/date';

    const VISIBLE_PER_DAY = 3;

    export default {
        name: 'EventsGrid',
        components: {
            ArrowLeft,
            ArrowRight,
        },
        props: {
            events: {
                type: Array,
                required: true,
            },
            month: {
                type: String,
                required: true,
            },
        },
        emits: ['open', 'change-month'],
        computed: {
            selectedDate: {
                get() {
                    return dayjs(this.month).toDate();
                },
                set(value) {
                    this.moveTo(dayjs(value));
                },
            },
            monthLabel() {
                return this.$formatDate(this.month, 'MMMM YYYY');
            },
            visibleFrom() {
                return dayjs(this.month).startOf('month').startOf('week').format('YYYY-MM-DD');
            },
            visibleTo() {
                return dayjs(this.month).endOf('month').endOf('week').format('YYYY-MM-DD');
            },
            entriesByDay() {
                const first = toUtcDay(this.visibleFrom);
                const last = toUtcDay(this.visibleTo);
                const days = {};

                this.events.forEach(event => {
                    const start = toUtcDay(event.start_at_local);
                    const end = toUtcDay(event.end_at_local);

                    for (
                        let day = Math.max(start, first);
                        day <= Math.min(end, last);
                        day += DAY_MS
                    ) {
                        const key = toDayKey(day);

                        days[key] = days[key] || [];
                        days[key].push({
                            event,
                            dayIndex: (day - start) / DAY_MS + 1,
                        });
                    }
                });

                Object.values(days).forEach(entries => entries.sort(
                    (a, b) => a.event.start_at_local.localeCompare(b.event.start_at_local)
                ));

                return days;
            },
        },
        methods: {
            dayNumber(day) {
                return this.$formatDate(day, 'D');
            },
            entriesFor(day) {
                return this.entriesByDay[day] || [];
            },
            visibleEntries(day) {
                return this.entriesFor(day).slice(0, VISIBLE_PER_DAY);
            },
            hiddenEntries(day) {
                return this.entriesFor(day).slice(VISIBLE_PER_DAY);
            },
            entryTime(entry) {
                if (entry.dayIndex > 1) return this.$t('Day %d', entry.dayIndex);
                return this.$formatTime(entry.event.start_at_local);
            },
            entryColor(entry) {
                return entry.event.calendar?.meta?.theme_color || null;
            },
            moveMonth(step) {
                this.moveTo(dayjs(this.month).add(step, 'month'));
            },
            moveToday() {
                this.moveTo(dayjs());
            },
            moveTo(date) {
                if (date.isSame(dayjs(this.month), 'month')) return;
                this.$emit('change-month', date.format('YYYY-MM-DD'));
            },
        },
    };
</script>
