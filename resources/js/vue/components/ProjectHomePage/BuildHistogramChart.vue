<template>
  <div
    v-if="loadFailed"
    class="tw-flex tw-items-center tw-justify-center tw-text-sm tw-text-neutral-500"
    data-test="build-histogram-error"
  >
    The build history could not be loaded.
  </div>
  <div
    v-else-if="!histogram"
    class="tw-flex tw-items-center tw-justify-center"
    data-test="build-histogram-loading"
  >
    <span class="tw-loading tw-loading-spinner" />
  </div>
  <VChart
    v-else-if="passedCount + failedCount > 0"
    ref="chart"
    class="tw-cursor-pointer"
    :option="chartOption"
    autoresize
    role="img"
    :aria-label="`Last ${HISTOGRAM_DAYS} days: ${passedCount.toLocaleString()} passed and ${failedCount.toLocaleString()} failed builds`"
    data-test="build-histogram"
    :data-test-passed-count="passedCount"
    :data-test-failed-count="failedCount"
    @zr:click="openDay"
  />
  <div
    v-else
    class="tw-flex tw-items-center tw-justify-center tw-text-sm tw-text-neutral-500"
    data-test="build-histogram-empty"
  >
    No builds in the last {{ HISTOGRAM_DAYS }} days
  </div>
</template>

<script>
import { DateTime } from 'luxon';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart } from 'echarts/charts';
import {
  GridComponent,
  TooltipComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';
import {
  FAILED_COLOR,
  HISTOGRAM_DAYS,
  PASSED_COLOR,
} from './BuildHistogram';

use([
  CanvasRenderer,
  BarChart,
  GridComponent,
  TooltipComponent,
]);

const AXIS_COLOR = '#e5e7eb';
const LABEL_COLOR = '#6b7280';
const ROUNDED_TOP = [4, 4, 0, 0];
// The distance between the cursor and the tooltip, as in ECharts.
const TOOLTIP_GAP = 20;

export default {
  name: 'BuildHistogramChart',

  components: {
    VChart,
  },

  props: {
    /**
     * Passing and failing builds for each testing day, and the page each bar links to, or null while loading.
     *
     * @type {Array<{date: import('luxon').DateTime, passed: number, failed: number, url: string}>|null}
     */
    histogram: {
      type: [Array, null],
      required: true,
    },

    loadFailed: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    HISTOGRAM_DAYS() {
      return HISTOGRAM_DAYS;
    },

    passedCount() {
      return this.histogram?.reduce((sum, { passed }) => sum + passed, 0) ?? 0;
    },

    failedCount() {
      return this.histogram?.reduce((sum, { failed }) => sum + failed, 0) ?? 0;
    },

    chartOption() {
      // A white border separates the stacked segments.  Days without builds in a segment have no bar
      // ('-'), so the border doesn't cover the axis line.
      const barStyle = {
        borderColor: '#ffffff',
        borderWidth: 1,
      };

      return {
        animation: false,
        grid: {
          left: 0,
          right: 0,
          top: 8,
          bottom: 0,
          containLabel: true,
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
          // The cards would clip the tooltip where it extends past the chart.
          appendTo: 'body',
          position: this.tooltipPosition,
          // Only dates and counts are shown, so there are no XSS concerns.
          formatter: ([{ dataIndex }]) => {
            const { date, passed, failed } = this.histogram[dataIndex];
            return `<b>Testing day ${date.toLocaleString(DateTime.DATE_MED_WITH_WEEKDAY)}</b><br>`
              + `Passed: ${passed.toLocaleString()}<br>`
              + `Failed: ${failed.toLocaleString()}`;
          },
        },
        xAxis: {
          type: 'category',
          data: this.histogram.map(({ date }) => date.toLocaleString({ month: 'short', day: 'numeric' })),
          axisTick: {
            show: false,
          },
          axisLine: {
            lineStyle: {
              color: AXIS_COLOR,
            },
          },
          axisLabel: {
            color: LABEL_COLOR,
            fontSize: 11,
            hideOverlap: true,
          },
        },
        yAxis: {
          type: 'value',
          minInterval: 1,
          splitNumber: 3,
          axisLabel: {
            color: LABEL_COLOR,
            fontSize: 11,
          },
          splitLine: {
            lineStyle: {
              color: AXIS_COLOR,
            },
          },
        },
        series: [
          {
            name: 'Passed',
            type: 'bar',
            stack: 'builds',
            barMaxWidth: 24,
            itemStyle: {
              ...barStyle,
              color: PASSED_COLOR,
            },
            // Round the top of each bar, which is the passing segment on days without failures.
            data: this.histogram.map(({ passed, failed }) => ({
              value: passed || '-',
              itemStyle: {
                borderRadius: failed > 0 ? 0 : ROUNDED_TOP,
              },
            })),
          },
          {
            name: 'Failed',
            type: 'bar',
            stack: 'builds',
            barMaxWidth: 24,
            itemStyle: {
              ...barStyle,
              color: FAILED_COLOR,
              borderRadius: ROUNDED_TOP,
              decal: {
                symbol: 'rect',
                dashArrayX: [1, 0],
                dashArrayY: [4, 8],
                rotation: Math.PI / 4,
              },
            },
            data: this.histogram.map(({ failed }) => failed || '-'),
          },
        ],
      };
    },
  },

  methods: {
    // Like ECharts' default position, except that the tooltip only moves to the left of the cursor when it
    // would run off the page, rather than the chart, which is often narrower than the tooltip.
    tooltipPosition([x, y], params, dom, rect, { contentSize: [width, height], viewSize: [, chartHeight] }) {
      const chartLeft = this.$refs.chart.$el.getBoundingClientRect().left;
      const pageWidth = document.documentElement.clientWidth;

      let left = chartLeft + x + TOOLTIP_GAP + width > pageWidth ? x - TOOLTIP_GAP - width : x + TOOLTIP_GAP;
      // Keep the tooltip on narrow screens, even if it covers the cursor.
      left = Math.max(-chartLeft, Math.min(left, pageWidth - chartLeft - width));

      const top = y + TOOLTIP_GAP + height > chartHeight ? y - TOOLTIP_GAP - height : y + TOOLTIP_GAP;
      return [left, top];
    },

    // Open the testing day of the clicked column, so days with few builds are easy to click.
    openDay({ offsetX, offsetY }) {
      const chart = this.$refs.chart;
      if (!chart.containPixel('grid', [offsetX, offsetY])) {
        return;
      }
      const [index] = chart.convertFromPixel({ seriesIndex: 0 }, [offsetX, offsetY]);
      const day = this.histogram[Math.round(index)];
      if (day) {
        window.location.href = day.url;
      }
    },
  },
};
</script>
