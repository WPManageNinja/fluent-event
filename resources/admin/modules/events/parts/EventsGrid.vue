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
                        <EventsGridEntry
                            v-for="entry in visibleEntries(data.day)"
                            :key="entry.event.id"
                            :event="entry.event"
                            :time="entryTime(entry)"
                            :color="entryColor(entry)"
                            @open="$emit('open', $event)"
                        />

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

                                <EventsGridEntry
                                    v-for="entry in entriesFor(data.day)"
                                    :key="entry.event.id"
                                    :event="entry.event"
                                    :time="entryTime(entry)"
                                    :color="entryColor(entry)"
                                    @open="$emit('open', $event)"
                                />
                            </template>
                        </el-popover>
                    </div>
                </div>
            </template>
        </el-calendar>

        <div v-else class="fe-events-grid__week">
            <div class="fe-events-grid__week-head" :style="weekGridStyle">
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

            <div class="fe-events-grid__week-body" :style="weekGridStyle">
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
                    v-for="column in dayColumns"
                    :key="`col-${column.key}`"
                    class="fe-events-grid__week-col"
                >
                    <span
                        v-for="hour in hours"
                        :key="`cell-${hour}`"
                        class="fe-events-grid__week-cell"
                        aria-hidden="true"
                    ></span>

                    <button
                        v-for="block in column.blocks"
                        :key="`block-${block.entry.event.id}`"
                        type="button"
                        class="fe-events-grid__week-event"
                        :style="blockStyle(block)"
                        :title="block.entry.event.title"
                        :aria-label="$t('View %s', block.entry.event.title)"
                        @click="$emit('open', block.entry.event)"
                    >
                        <span class="fe-events-grid__event-time">
                            {{ $formatTime(block.entry.event.start_at_local) }}
                        </span>
                        <span class="fe-events-grid__event-title">
                            {{ block.entry.event.title }}
                        </span>
                    </button>

                    <!-- Not teleported, so Tab reaches the hidden events; fixed so the scrolling grid cannot clip it.
                         Touch has no hover, so a tap switches the chip to a click trigger. -->
                    <el-popover
                        v-for="more in column.more"
                        :key="more.key"
                        :width="260"
                        :trigger="moreTrigger"
                        placement="right"
                        :teleported="false"
                        :popper-options="morePopper"
                        :persistent="false"
                        popper-class="fe-events-grid__popover"
                    >
                        <template #reference>
                            <button
                                type="button"
                                class="fe-events-grid__week-more"
                                :style="moreStyle(more)"
                                :aria-label="$t('%d more', more.entries.length)"
                                @pointerdown="moreTrigger = $event.pointerType === 'touch' ? 'click' : 'hover'"
                            >
                                {{ $t('+%d', more.entries.length) }}
                            </button>
                        </template>

                        <template #default>
                            <p class="fe-events-grid__popover-title">
                                {{ $formatDate(column.key, 'dddd, MMM D') }}
                            </p>

                            <EventsGridEntry
                                v-for="entry in more.entries"
                                :key="entry.event.id"
                                :event="entry.event"
                                :time="entryTime(entry)"
                                :color="entryColor(entry)"
                                @open="$emit('open', $event)"
                            />
                        </template>
                    </el-popover>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
    import SegmentedSwitch from '@/components/SegmentedSwitch.vue';
    import EventsGridEntry from './EventsGridEntry.vue';
    import { DAY_MS, toDayKey, toUtcDay } from '@/utils/date';

    const VISIBLE_PER_DAY = 3;
    const HOUR_PX = 48;
    const MINUTES_PER_DAY = 1440;
    const MAX_LANES = 3;
    const MORE_STRIP_PX = 36;
    // Narrower, and three lanes plus the "+N" strip no longer fit; the week scrolls sideways instead.
    const DAY_MIN_PX = 120;

    export default {
        name: 'EventsGrid',
        components: {
            ArrowLeft,
            ArrowRight,
            SegmentedSwitch,
            EventsGridEntry,
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
                moreTrigger: 'hover',
                morePopper: {
                    strategy: 'fixed',
                    // A phone has no room beside the chip on either side; drop below it instead.
                    modifiers: [{ name: 'flip', options: { fallbackPlacements: ['left', 'bottom', 'top'] } }],
                },
            };
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
            dayColumns() {
                return this.viewDays.map(day => ({
                    key: day.format('YYYY-MM-DD'),
                    ...this.dayLayout(day),
                }));
            },
            weekGridStyle() {
                const days = this.viewDays.length;

                return {
                    gridTemplateColumns: `64px repeat(${days}, minmax(${DAY_MIN_PX}px, 1fr))`,
                    minWidth: `${64 + days * DAY_MIN_PX}px`,
                };
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
                return this.$formatTime(entry.event.start_at_local);
            },
            entryColor(entry) {
                return entry.event.calendar?.meta?.theme_color || null;
            },
            hourLabel(hour) {
                const timeFormat = window.fluentEventAdmin?.time_format || 'h:mm a';
                const format = timeFormat.includes('H') ? 'HH:00' : 'h A';

                return dayjs(this.month).hour(hour).minute(0).format(format);
            },
            dayLayout(day) {
                const dayStart = day.startOf('day');
                const dayEnd = dayStart.add(1, 'day');

                const blocks = this.entriesFor(day.format('YYYY-MM-DD')).map(entry => {
                    const start = dayjs(entry.event.start_at_local);
                    const end = dayjs(entry.event.end_at_local);

                    const from = start.isBefore(dayStart) ? 0 : start.diff(dayStart, 'minute');
                    const until = end.isAfter(dayEnd)
                        ? MINUTES_PER_DAY
                        : Math.max(end.diff(dayStart, 'minute'), from + 30);

                    return { entry, from, until, lane: 0 };
                }).sort((a, b) => a.from - b.from || b.until - a.until);

                // Clashing events split the column: each takes the first lane free at its start.
                // A run of overlaps keeps MAX_LANES lanes; the rest fold into one "+N" strip on the right.
                const visible = [];
                const more = [];
                let group = [];
                let laneEnds = [];
                let groupEnd = 0;

                const closeGroup = () => {
                    const hidden = group.filter(block => block.lane >= MAX_LANES);
                    const lanes = Math.min(laneEnds.length, MAX_LANES);
                    const strip = hidden.length ? MORE_STRIP_PX : 0;

                    group.filter(block => block.lane < MAX_LANES).forEach(block => {
                        visible.push({ ...block, lanes, strip });
                    });

                    if (hidden.length) {
                        const from = Math.min(...hidden.map(block => block.from));

                        more.push({
                            key: `${day.format('YYYY-MM-DD')}-${from}`,
                            from,
                            until: Math.max(...hidden.map(block => block.until)),
                            entries: hidden.map(block => block.entry),
                        });
                    }

                    group = [];
                    laneEnds = [];
                };

                blocks.forEach(block => {
                    if (group.length && block.from >= groupEnd) {
                        closeGroup();
                    }

                    const free = laneEnds.findIndex(laneEnd => laneEnd <= block.from);
                    block.lane = free === -1 ? laneEnds.length : free;
                    laneEnds[block.lane] = block.until;
                    group.push(block);
                    groupEnd = Math.max(groupEnd, block.until);
                });

                closeGroup();

                return { blocks: visible, more };
            },
            blockStyle(block) {
                return {
                    top: `${(block.from / 60) * HOUR_PX}px`,
                    height: `${((block.until - block.from) / 60) * HOUR_PX - 2}px`,
                    left: `calc(4px + (100% - 8px - ${block.strip}px) * ${block.lane / block.lanes})`,
                    width: `calc((100% - 8px - ${block.strip}px) / ${block.lanes} - 2px)`,
                    '--fe-event-color': this.entryColor(block.entry),
                };
            },
            moreStyle(more) {
                return {
                    top: `${(more.from / 60) * HOUR_PX}px`,
                    height: `${((more.until - more.from) / 60) * HOUR_PX - 2}px`,
                    right: '4px',
                    width: `${MORE_STRIP_PX - 2}px`,
                };
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
