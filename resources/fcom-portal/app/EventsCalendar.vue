<template>
    <div class="fe-events-calendar">
        <div class="fe-events-calendar__toolbar">
            <h2 class="fe-events-calendar__title">{{ titleLabel }}</h2>

            <segmented-switch
                v-model="currentMode"
                :options="modes"
                :label="$t('Switch between month, week and day view')"
            />

            <div class="fe-events-calendar__nav">
                <el-button class="fcom_secondary_button" size="small" :aria-label="$t('Previous')" :title="$t('Previous')" @click="moveStep(-1)">
                    <el-icon><ArrowLeft/></el-icon>
                </el-button>
                <el-button class="fcom_secondary_button" size="small" @click="moveTo(dayjs())">{{ $t('Today') }}</el-button>
                <el-button class="fcom_secondary_button" size="small" :aria-label="$t('Next')" :title="$t('Next')" @click="moveStep(1)">
                    <el-icon><ArrowRight/></el-icon>
                </el-button>
            </div>
        </div>

        <el-calendar v-if="mode === 'month'" v-model="selectedDate">
            <template #date-cell="{ data }">
                <div
                    class="fe-events-calendar__cell"
                    :class="{ 'fe-events-calendar__cell--outside': data.type !== 'current-month' }"
                >
                    <span class="fe-events-calendar__day">{{ data.day.slice(8) }}</span>

                    <div class="fe-events-calendar__entries">
                        <button
                            v-for="entry in entriesFor(data.day).slice(0, visiblePerDay)"
                            :key="entry.event.id"
                            type="button"
                            class="fe-events-calendar__event"
                            :title="entry.event.title"
                            :aria-label="$t('View %s', entry.event.title)"
                            @click.stop="$emit('open', entry.event)"
                        >
                            <span class="fe-events-calendar__event-time">{{ entryTime(entry) }}</span>
                            <span class="fe-events-calendar__event-title">{{ entry.event.title }}</span>
                        </button>

                        <el-popover
                            v-if="entriesFor(data.day).length > visiblePerDay"
                            :width="260"
                            trigger="click"
                            placement="right"
                            :persistent="false"
                            popper-class="fe-events-calendar__popover"
                        >
                            <template #reference>
                                <button type="button" class="fe-events-calendar__more" @click.stop>
                                    {{ $t('%d more', entriesFor(data.day).length - visiblePerDay) }}
                                </button>
                            </template>

                            <p class="fe-events-calendar__popover-title">{{ dayjs(data.day).format('dddd, MMM D') }}</p>

                            <button
                                v-for="entry in entriesFor(data.day)"
                                :key="entry.event.id"
                                type="button"
                                class="fe-events-calendar__event"
                                :title="entry.event.title"
                                :aria-label="$t('View %s', entry.event.title)"
                                @click="$emit('open', entry.event)"
                            >
                                <span class="fe-events-calendar__event-time">{{ entryTime(entry) }}</span>
                                <span class="fe-events-calendar__event-title">{{ entry.event.title }}</span>
                            </button>
                        </el-popover>
                    </div>
                </div>
            </template>
        </el-calendar>

        <div v-else class="fe-events-calendar__week">
            <div class="fe-events-calendar__week-head" :style="{ gridTemplateColumns: weekColumns }">
                <span aria-hidden="true"></span>

                <div
                    v-for="day in viewDays"
                    :key="`head-${day.format('YYYY-MM-DD')}`"
                    class="fe-events-calendar__week-day"
                    :class="{ 'fe-events-calendar__week-day--today': day.isSame(dayjs(), 'day') }"
                >
                    <span class="fe-events-calendar__week-day-name">{{ day.format('ddd') }}</span>
                    <span class="fe-events-calendar__week-day-number">{{ day.format('D') }}</span>
                </div>
            </div>

            <div class="fe-events-calendar__week-body" :style="{ gridTemplateColumns: weekColumns }">
                <div class="fe-events-calendar__week-times">
                    <span v-for="hour in hours" :key="`hour-${hour}`" class="fe-events-calendar__week-time">
                        <template v-if="hour">{{ dayjs(date).hour(hour).minute(0).format(hourFormat) }}</template>
                    </span>
                </div>

                <div
                    v-for="day in viewDays"
                    :key="`col-${day.format('YYYY-MM-DD')}`"
                    class="fe-events-calendar__week-col"
                >
                    <span v-for="hour in hours" :key="`cell-${hour}`" class="fe-events-calendar__week-cell" aria-hidden="true"></span>

                    <button
                        v-for="block in dayBlocks(day)"
                        :key="`block-${block.entry.event.id}`"
                        type="button"
                        class="fe-events-calendar__week-event"
                        :style="blockStyle(block)"
                        :title="block.entry.event.title"
                        :aria-label="$t('View %s', block.entry.event.title)"
                        @click="$emit('open', block.entry.event)"
                    >
                        <span class="fe-events-calendar__event-time">{{ dayjs(block.entry.event.start_at_site).format(timeFormat) }}</span>
                        <span class="fe-events-calendar__event-title">{{ block.entry.event.title }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
import { DAY_MS, toDayKey, toUtcDay } from '@/utils/date';
import { timeFormat } from '../utils';

// Month / week / day grid, after the admin EventsGrid; dates come from fcom's own dayjs.
export default {
    name: 'EventsCalendar',
    components: { ArrowLeft, ArrowRight, SegmentedSwitch },
    props: {
        events: { type: Array, required: true },
        date: { type: String, required: true },
        mode: { type: String, default: 'month' },
    },
    emits: ['open', 'change-date', 'update:mode'],
    data() {
        return {
            visiblePerDay: 3,
            timeFormat: timeFormat(),
        };
    },
    computed: {
        currentMode: {
            get() {
                return this.mode;
            },
            set(value) {
                this.$emit('update:mode', value);
            },
        },
        modes() {
            return [
                { value: 'month', label: this.$t('Month') },
                { value: 'week', label: this.$t('Week') },
                { value: 'day', label: this.$t('Day') },
            ];
        },
        selectedDate: {
            get() {
                return this.dayjs(this.date).toDate();
            },
            set(value) {
                this.moveTo(this.dayjs(value));
            },
        },
        titleLabel() {
            const anchor = this.dayjs(this.date);

            if (this.mode === 'day') {
                return anchor.format('dddd, MMM D, YYYY');
            }

            if (this.mode === 'week') {
                return `${anchor.startOf('week').format('MMM D')} – ${anchor.endOf('week').format('MMM D, YYYY')}`;
            }

            return anchor.format('MMMM YYYY');
        },
        viewDays() {
            const anchor = this.dayjs(this.date);

            if (this.mode === 'day') {
                return [anchor];
            }

            const start = anchor.startOf('week');

            return Array.from({ length: 7 }, (unused, index) => start.add(index, 'day'));
        },
        weekColumns() {
            return `64px repeat(${this.viewDays.length}, minmax(0, 1fr))`;
        },
        hours() {
            return Array.from({ length: 24 }, (unused, index) => index);
        },
        hourFormat() {
            return this.timeFormat.includes('H') ? 'HH:00' : 'h A';
        },
        // Each day of the visible month grid, with the events on it; a multi-day event lands on every day it spans.
        entriesByDay() {
            const month = this.dayjs(this.date);
            const first = toUtcDay(month.startOf('month').startOf('week').format('YYYY-MM-DD'));
            const last = toUtcDay(month.endOf('month').endOf('week').format('YYYY-MM-DD'));
            const days = {};

            this.events.forEach((event) => {
                const start = toUtcDay(event.start_at_site);
                const end = toUtcDay(event.end_at_site);

                for (let day = Math.max(start, first); day <= Math.min(end, last); day += DAY_MS) {
                    const key = toDayKey(day);

                    days[key] = days[key] || [];
                    days[key].push({ event, dayIndex: (day - start) / DAY_MS + 1 });
                }
            });

            Object.values(days).forEach((entries) => entries.sort(
                (a, b) => a.event.start_at_site.localeCompare(b.event.start_at_site)
            ));

            return days;
        },
    },
    methods: {
        dayjs(value) {
            return window.dayjs(value);
        },
        entriesFor(day) {
            return this.entriesByDay[day] || [];
        },
        entryTime(entry) {
            if (entry.dayIndex > 1) {
                return this.$t('Day %d', entry.dayIndex);
            }

            return this.dayjs(entry.event.start_at_site).format(this.timeFormat);
        },
        dayBlocks(day) {
            const dayStart = day.startOf('day');
            const dayEnd = dayStart.add(1, 'day');

            return this.entriesFor(day.format('YYYY-MM-DD')).map((entry) => {
                const start = this.dayjs(entry.event.start_at_site);
                const end = this.dayjs(entry.event.end_at_site);
                const from = start.isBefore(dayStart) ? 0 : start.diff(dayStart, 'minute');
                const until = end.isAfter(dayEnd) ? 24 * 60 : Math.max(end.diff(dayStart, 'minute'), from + 30);

                return { entry, from, until };
            });
        },
        // In percent of the day column, so it follows the hour rows' CSS height.
        blockStyle(block) {
            return {
                top: `${(block.from / (24 * 60)) * 100}%`,
                height: `calc(${((block.until - block.from) / (24 * 60)) * 100}% - 2px)`,
            };
        },
        moveStep(step) {
            this.moveTo(this.dayjs(this.date).add(step, this.mode));
        },
        moveTo(date) {
            if (!date.isSame(this.dayjs(this.date), 'day')) {
                this.$emit('change-date', date.format('YYYY-MM-DD'));
            }
        },
    },
};
</script>
