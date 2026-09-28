<template>
    <h2 v-if="title" class="fe-dashboard__section-title">{{ title }}</h2>

    <div class="fe-dashboard__tiles">
        <div v-for="(stat, key) in stats" :key="key" class="fe-dashboard__tile">
            <span
                class="fe-dashboard__tile-icon"
                :class="`fe-dashboard__tile-icon--${stat.icon.toLowerCase()}`"
                aria-hidden="true"
            >
                <el-icon><component :is="tileIcon(stat)" /></el-icon>
            </span>

            <div class="fe-dashboard__tile-body">
                <p class="fe-dashboard__tile-title">{{ stat.title }}</p>

                <div class="fe-dashboard__tile-row">
                    <span class="fe-dashboard__tile-value">
                        {{ stat.is_number ? $formatNumber(stat.amount) : stat.amount }}
                    </span>

                    <span
                        v-if="stat.change != null"
                        class="fe-dashboard__tile-change"
                        :class="changeClass(stat)"
                        :title="$t('vs previous period')"
                    >
                        <el-icon v-if="stat.change" aria-hidden="true">
                            <CaretTop v-if="stat.change > 0" />
                            <CaretBottom v-else />
                        </el-icon>
                        {{ stat.change > 0 ? '+' : '' }}{{ stat.change }}%
                        <span class="screen-reader-text">{{ $t('vs previous period') }}</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/javascript">
    import {
        Calendar,
        Tickets,
        User,
        CircleCheck,
        Finished,
        CircleClose,
        Timer,
        VideoPlay,
        Select,
        CaretTop,
        CaretBottom,
    } from '@element-plus/icons-vue';

    const ICONS = {
        Calendar,
        Event: Tickets,
        Guest: User,
        Going: CircleCheck,
        CheckedIn: Finished,
        Cancelled: CircleClose,
        Upcoming: Timer,
        Ongoing: VideoPlay,
        Completed: Select,
    };

    export default {
        name: 'StatGroups',
        components: { CaretTop, CaretBottom },
        props: {
            title: {
                type: String,
                default: '',
            },
            stats: {
                type: Object,
                required: true,
            },
        },
        methods: {
            tileIcon(stat) {
                return ICONS[stat.icon] || User;
            },
            changeClass(stat) {
                if (!stat.change) return 'fe-dashboard__tile-change--flat';

                return stat.change > 0
                    ? 'fe-dashboard__tile-change--up'
                    : 'fe-dashboard__tile-change--down';
            },
        },
    };
</script>
