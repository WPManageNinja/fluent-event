<template>
    <div class="fe-events-grid">
        <div class="fe-events-grid__toolbar">
            <h2 class="fe-events-grid__month">{{ titleLabel }}</h2>

            <SegmentedSwitch
                v-model="mode"
                class="fe-switch--compact"
                :options="viewModes"
                :label="$t('Switch between month, week and day view')"
            />

            <el-button-group>
                <el-button
                    :aria-label="$t('Previous')"
                    :title="$t('Previous')"
                    @click="moveStep(-1)"
                >
                    <el-icon><ArrowLeft /></el-icon>
                </el-button>

                <el-button @click="moveToday">{{ $t('Today') }}</el-button>

                <el-button
                    :aria-label="$t('Next')"
                    :title="$t('Next')"
                    @click="moveStep(1)"
                >
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </el-button-group>
        </div>

        <el-calendar v-if="isMonthView" v-model="selectedDate">
            <template #header>
                <span aria-hidden="true"></span>
            </template>

            <template #date-cell="{ data }">
                <div
                    class="fe-events-grid__cell"
                    :class="{ 'fe-events-grid__cell--outside': data.type !== 'current-month' }"
                >
                    <span class="fe-events-grid__day">{{ dayNumber(data.day) }}</span>

                    <div class="fe-events-grid__entries">
                        <button
                            v-for="entry in visibleEntries(data.day)"
                            :key="entry.event.id"
                            type="button"
                            class="fe-events-grid__event"
                            :style="{ '--fe-event-color': entryColor(entry) }"
                            :title="entry.event.title"
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

                        <el-popover
                            v-if="hiddenEntries(data.day).length"
                            :width="260"
                            trigger="click"
                            placement="right"
                            :persistent="false"
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

                                <div class="fe-events-grid__entries">
                                    <button
                                        v-for="entry in entriesFor(data.day)"
                                        :key="entry.event.id"
                                        type="button"
                                        class="fe-events-grid__event"
                                        :style="{ '--fe-event-color': entryColor(entry) }"
                                        :title="entry.event.title"
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
                                </div>
                            </template>
                        </el-popover>
                    </div>

                    <!-- Phones: a bar per event; a tap lists the day's events below. -->
                    <button
                        type="button"
                        class="fe-events-grid__day-button"
                        :aria-pressed="pickedDay === data.day ? 'true' : 'false'"
                        :aria-label="$formatDate(data.day, 'dddd, MMM D')"
                        @click.stop="pickDay(data.day)"
                    >
                        <span
                            v-for="entry in visibleEntries(data.day)"
                            :key="`bar-${entry.event.id}`"
                            class="fe-events-grid__bar"
                            :style="{ '--fe-event-color': entryColor(entry) }"
                        ></span>
                    </button>
                </div>
            </template>
        </el-calendar>

        <div
            v-if="isMonthView && pickedDay"
            ref="agenda"
            class="fe-events-grid__agenda"
            role="region"
            :aria-label="$formatDate(pickedDay, 'dddd, MMM D')"
        >
            <p class="fe-events-grid__agenda-date">
                <span class="fe-events-grid__agenda-number">{{ dayNumber(pickedDay) }}</span>
                <span class="fe-events-grid__agenda-weekday">{{ $formatDate(pickedDay, 'ddd') }}</span>
            </p>

            <button
                v-for="entry in entriesFor(pickedDay)"
                :key="`agenda-${entry.event.id}`"
                type="button"
                class="fe-events-grid__agenda-event"
                :style="{ '--fe-event-color': entryColor(entry) }"
                :aria-label="$t('View %s', entry.event.title)"
                @click="$emit('open', entry.event)"
            >
                <span class="fe-events-grid__agenda-event-time">{{ entryTime(entry) }}</span>
                <span class="fe-events-grid__agenda-event-title">{{ entry.event.title }}</span>
            </button>

            <p v-if="!entriesFor(pickedDay).length" class="fe-events-grid__agenda-empty">
                {{ $t('No events on this day.') }}
            </p>
        </div>

        <div v-if="!isMonthView" class="fe-events-grid__week">
            <div class="fe-events-grid__week-head" :style="{ gridTemplateColumns: weekColumns }">
                <span class="fe-events-grid__week-gutter" aria-hidden="true"></span>

                <div
                    v-for="day in viewDays"
                    :key="`head-${day.format('YYYY-MM-DD')}`"
                    class="fe-events-grid__week-day"
                    :class="{ 'fe-events-grid__week-day--today': isToday(day) }"
                >
                    <span class="fe-events-grid__week-day-name">{{ day.format('ddd') }}</span>
                    <span class="fe-events-grid__week-day-number">{{ day.format('D') }}</span>
                </div>
            </div>

            <div class="fe-events-grid__week-body" :style="{ gridTemplateColumns: weekColumns }">
                <div class="fe-events-grid__week-times">
                    <span
                        v-for="hour in hours"
                        :key="`hour-${hour}`"
                        class="fe-events-grid__week-time"
                    >
                        <template v-if="hour">{{ hourLabel(hour) }}</template>
                    </span>
                </div>

                <div
                    v-for="day in viewDays"
                    :key="`col-${day.format('YYYY-MM-DD')}`"
                    class="fe-events-grid__week-col"
                >
                    <span
                        v-for="hour in hours"
                        :key="`cell-${hour}`"
                        class="fe-events-grid__week-cell"
                        aria-hidden="true"
                    ></span>

                    <button
                        v-for="block in dayBlocks(day)"
                        :key="`block-${block.entry.event.id}`"
                        type="button"
                        class="fe-events-grid__week-event"
                        :style="blockStyle(block)"
                        :title="block.entry.event.title"
                        :aria-label="$t('View %s', block.entry.event.title)"
                        @click="$emit('open', block.entry.event)"
                    >
                        <span class="fe-events-grid__event-time">
                            {{ $formatTime(block.entry.event.start_at_site) }}
                        </span>
                        <span class="fe-events-grid__event-title">
                            {{ block.entry.event.title }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import { DAY_MS, lastDay, toDayKey, toUtcDay } from '@/utils/date';

    const VISIBLE_PER_DAY = 3;
    const HOUR_PX = 48;
    const MINUTES_PER_DAY = 1440;

    export default {
        name: 'EventsGrid',
        components: {
            ArrowLeft,
            ArrowRight,
            SegmentedSwitch,
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
            viewMode: {
                type: String,
                default: 'month',
            },
        },
        emits: ['open', 'change-month', 'update:viewMode'],
        data() {
            return {
                // The day tapped on a phone; its events show below the grid.
                pickedDay: null,
            };
        },
        watch: {
            // Another month clears the picked day, even one picked from that month's days.
            month(month, previous) {
                if (month.slice(0, 7) !== String(previous).slice(0, 7)) {
                    this.pickedDay = null;
                }
            },
            viewMode() {
                this.pickedDay = null;
            },
        },
        computed: {
            mode: {
                get() {
                    return this.viewMode;
                },
                set(value) {
                    this.$emit('update:viewMode', value);
                },
            },
            isMonthView() {
                return this.viewMode === 'month';
            },
            viewModes() {
                return [
                    { value: 'month', label: this.$t('Month') },
                    { value: 'week', label: this.$t('Week') },
                    { value: 'day', label: this.$t('Day') },
                ];
            },
            selectedDate: {
                get() {
                    return dayjs(this.month).toDate();
                },
                set(value) {
                    this.moveTo(dayjs(value));
                },
            },
            titleLabel() {
                const anchor = dayjs(this.month);

                if (this.viewMode === 'day') {
                    return this.$formatDate(this.month);
                }

                if (this.viewMode === 'week') {
                    const start = anchor.startOf('week');
                    const end = anchor.endOf('week');

                    return `${start.format('MMM D')} – ${end.format('MMM D, YYYY')}`;
                }

                return this.$formatDate(this.month, 'MMMM YYYY');
            },
            viewDays() {
                const anchor = dayjs(this.month);

                if (this.viewMode === 'day') {
                    return [anchor];
                }

                const start = anchor.startOf('week');

                return Array.from({ length: 7 }, (unused, index) => start.add(index, 'day'));
            },
            weekColumns() {
                return `64px repeat(${this.viewDays.length}, 1fr)`;
            },
            hours() {
                return Array.from({ length: 24 }, (unused, index) => index);
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
                    const start = toUtcDay(event.start_at_site);
                    const end = toUtcDay(lastDay(event.start_at_site, event.end_at_site));

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
                    (a, b) => a.event.start_at_site.localeCompare(b.event.start_at_site)
                ));

                return days;
            },
        },
        methods: {
            dayNumber(day) {
                return this.$formatDate(day, 'D');
            },
            isToday(day) {
                return day.isSame(dayjs(), 'day');
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
                return this.$formatTime(entry.event.start_at_site);
            },
            entryColor(entry) {
                return entry.event.calendar?.meta?.theme_color || null;
            },
            hourLabel(hour) {
                const timeFormat = window.fluentEventAdmin?.time_format || 'h:mm a';
                const format = timeFormat.includes('H') ? 'HH:00' : 'h A';

                return dayjs(this.month).hour(hour).minute(0).format(format);
            },
            dayBlocks(day) {
                const dayStart = day.startOf('day');
                const dayEnd = dayStart.add(1, 'day');

                return this.entriesFor(day.format('YYYY-MM-DD')).map(entry => {
                    const start = dayjs(entry.event.start_at_site);
                    const end = dayjs(entry.event.end_at_site);

                    const from = start.isBefore(dayStart) ? 0 : start.diff(dayStart, 'minute');
                    const until = end.isAfter(dayEnd)
                        ? MINUTES_PER_DAY
                        : Math.max(end.diff(dayStart, 'minute'), from + 30);

                    return { entry, from, until };
                });
            },
            blockStyle(block) {
                return {
                    top: `${(block.from / 60) * HOUR_PX}px`,
                    height: `${((block.until - block.from) / 60) * HOUR_PX - 2}px`,
                    '--fe-event-color': this.entryColor(block.entry),
                };
            },
            // Scrolls the list into view, no further than needed.
            pickDay(day) {
                this.pickedDay = day;

                this.$nextTick(() => {
                    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    this.$refs.agenda && this.$refs.agenda.scrollIntoView({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
                });
            },
            moveStep(step) {
                this.moveTo(dayjs(this.month).add(step, this.viewMode));
            },
            moveToday() {
                this.moveTo(dayjs());
            },
            moveTo(date) {
                if (date.isSame(dayjs(this.month), 'day')) return;
                this.$emit('change-month', date.format('YYYY-MM-DD'));
            },
        },
    };
</script>
