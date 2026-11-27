<template>
    <section v-loading="loading" class="fe-dashboard__card fe-dashboard__chart" :aria-busy="loading">
        <div class="fe-dashboard__chart-head">
            <h2 class="fe-dashboard__card-title">{{ sectionTitle }}</h2>

            <el-date-picker
                v-model="range"
                type="daterange"
                class="fe-dashboard__range"
                :class="{ 'fe-dashboard__range--wide': wideDates }"
                :format="pickerFormat"
                value-format="YYYY-MM-DD"
                :clearable="false"
                :start-placeholder="$t('From')"
                :end-placeholder="$t('To')"
                @change="fetchChartData"
            />
        </div>

        <div v-if="hasData" ref="canvas" class="fe-dashboard__chart-canvas"></div>

        <p v-else class="fe-dashboard__empty">
            {{ $t('No guest activity in this period.') }}
        </p>
    </section>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import * as echarts from 'echarts/core';
    import { LineChart } from 'echarts/charts';
    import { CanvasRenderer } from 'echarts/renderers';
    import {
        AriaComponent,
        GridComponent,
        TooltipComponent,
        LegendComponent,
    } from 'echarts/components';

    echarts.use([
        LineChart,
        CanvasRenderer,
        AriaComponent,
        GridComponent,
        TooltipComponent,
        LegendComponent,
    ]);

    const cssVar = function (name, fallback) {
        const value = getComputedStyle(document.body)
            .getPropertyValue(name)
            .trim();

        return value || fallback;
    };

    export default {
        name: 'GuestGrowth',
        props: {
            sectionTitle: {
                type: String,
                required: true,
            },
            endPoint: {
                type: String,
                required: true,
            },
        },
        data() {
            return {
                loading: false,
                narrow: false,
                range: [
                    dayjs().subtract(6, 'day').format('YYYY-MM-DD'),
                    dayjs().format('YYYY-MM-DD'),
                ],
                chartData: {
                    xAxisData: [],
                    series: [],
                },
            };
        },
        computed: {
            hasData() {
                return this.chartData.series.some(
                    line => line.data.some(point => point > 0)
                );
            },
            dateFormat() {
                return window.fluentEventAdmin?.date_format || 'MMM D, YYYY';
            },
            // Phones get the site's format with short month and day names, so both dates fit.
            pickerFormat() {
                return this.narrow
                    ? this.dateFormat.replace('MMMM', 'MMM').replace('dddd', 'ddd')
                    : this.dateFormat;
            },
            // The format at its longest month; past 12 characters it outgrows the narrow picker.
            wideDates() {
                return dayjs('2026-09-28').format(this.pickerFormat).length > 12;
            },
        },
        methods: {
            fetchChartData() {
                if (!this.endPoint) return;

                this.$requestServer({
                    path: this.endPoint,
                    payload: {
                        start_date: this.range[0],
                        end_date: this.range[1],
                    },
                    errorState: null,
                    errorNotify: true,
                    onSuccess: response => {
                        // The server caps the span, so show the range it actually drew.
                        if (response.range) {
                            this.range = [response.range.start, response.range.end];
                        }
                        this.chartData = {
                            xAxisData: response.xAxisData || [],
                            series: response.series || [],
                        };
                        this.$nextTick(() => this.renderChart());
                    },
                });
            },
            renderChart() {
                if (!this.hasData || !this.$refs.canvas) {
                    this.disposeChart();
                    return;
                }

                if (!this.chart) {
                    this.chart = echarts.init(this.$refs.canvas);
                }

                const textColor = cssVar('--fe-secondary-text', '#525866');
                const lineColor = cssVar('--fe-primary-border', '#eaecf0');

                this.chart.setOption({
                    aria: {
                        enabled: true,
                        label: {
                            data: { maxCount: this.chartData.xAxisData.length },
                        },
                    },
                    color: [
                        cssVar('--fe-chart-going', '#0099ff'),
                        cssVar('--fe-chart-checked-in', '#1a7f37'),
                        cssVar('--fe-chart-cancelled', '#e06c75'),
                    ],
                    legend: {
                        top: 0,
                        icon: 'circle',
                        itemWidth: 8,
                        itemHeight: 8,
                        itemGap: 24,
                        textStyle: { color: textColor },
                    },
                    tooltip: {
                        trigger: 'axis',
                        backgroundColor: cssVar('--fe-primary-bg', '#ffffff'),
                        borderColor: lineColor,
                        textStyle: { color: cssVar('--fe-primary-text', '#19283a') },
                    },
                    grid: {
                        left: 8,
                        right: 16,
                        top: 40,
                        bottom: 8,
                        containLabel: true,
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: this.chartData.xAxisData,
                        axisLabel: { color: textColor, fontSize: 12 },
                        axisLine: { lineStyle: { color: lineColor } },
                    },
                    yAxis: {
                        type: 'value',
                        minInterval: 1,
                        axisLabel: { color: textColor, fontSize: 12 },
                        splitLine: { lineStyle: { color: lineColor, type: 'dashed' } },
                    },
                    series: this.chartData.series.map(line => ({
                        name: line.name,
                        type: 'line',
                        smooth: true,
                        showSymbol: false,
                        lineStyle: { width: 2 },
                        areaStyle: { opacity: 0.1 },
                        data: line.data,
                    })),
                }, true);
            },
            resizeChart() {
                if (this.chart) {
                    this.chart.resize();
                }
            },
            setNarrow(query) {
                this.narrow = query.matches;
            },
            disposeChart() {
                if (this.chart) {
                    this.chart.dispose();
                    this.chart = null;
                }
            },
        },
        created() {
            this.chart = null;
            this.themeObserver = null;
            this.narrowQuery = null;
        },
        mounted() {
            this.fetchChartData();
            window.addEventListener('resize', this.resizeChart);

            this.narrowQuery = window.matchMedia('(max-width: 480px)');
            this.setNarrow(this.narrowQuery);
            this.narrowQuery.addEventListener('change', this.setNarrow);

            this.themeObserver = new MutationObserver(this.renderChart);
            this.themeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            });
        },
        beforeUnmount() {
            window.removeEventListener('resize', this.resizeChart);
            this.narrowQuery?.removeEventListener('change', this.setNarrow);
            this.themeObserver?.disconnect();
            this.disposeChart();
        },
    };
</script>
