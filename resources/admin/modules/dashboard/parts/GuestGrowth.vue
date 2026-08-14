<template>
    <section v-loading="loading" class="fe-dashboard__card fe-dashboard__chart">
        <div class="fe-dashboard__chart-head">
            <h2 class="fe-dashboard__card-title">{{ sectionTitle }}</h2>

            <el-date-picker
                v-model="range"
                type="daterange"
                format="MMM D, YYYY"
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
        GridComponent,
        TooltipComponent,
        LegendComponent,
    } from 'echarts/components';

    echarts.use([LineChart, CanvasRenderer, GridComponent, TooltipComponent, LegendComponent]);

    const cssVar = function (name, fallback) {
        const value = getComputedStyle(document.documentElement)
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
                range: [
                    dayjs().subtract(29, 'day').format('YYYY-MM-DD'),
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
        },
        methods: {
            fetchChartData() {
                if (!this.endPoint || this.loading) return;
                
                this.loading = true;
                this.$get(this.endPoint, {
                    start_date: this.range[0],
                    end_date: this.range[1],
                })
                    .then(response => {
                        this.chartData = {
                            xAxisData: response.xAxisData || [],
                            series: response.series || [],
                        };
                        this.$nextTick(() => this.renderChart());
                    })
                    .catch(error => {
                        this.$handleError(error);
                    })
                    .finally(() => {
                        this.loading = false;
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
                const lineColor = cssVar('--fe-primary-border', '#d3d8de');

                this.chart.setOption({
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
            disposeChart() {
                if (this.chart) {
                    this.chart.dispose();
                    this.chart = null;
                }
            },
        },
        created() {
            this.chart = null;
        },
        mounted() {
            this.fetchChartData();
            window.addEventListener('resize', this.resizeChart);
        },
        beforeUnmount() {
            window.removeEventListener('resize', this.resizeChart);
            this.disposeChart();
        },
    };
</script>
