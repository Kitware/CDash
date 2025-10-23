<template>
  <v-chart
    class="tw-w-full tw-h-60"
    :option="chartOption"
    autoresize
  />
</template>

<script>
import { Duration } from 'luxon';
import VChart from 'vue-echarts';

export default {
  name: 'LineChart',
  components: {
    VChart,
  },

  props: {
    /**
     * The label for the y-axis. Optional.
     */
    yLabel: {
      type: String,
      default: '',
    },

    /**
     * An array of data points to plot.
     *
     * @type {Array<{x: import('luxon').DateTime, y: number}>}
     */
    data: {
      type: Array,
      required: true,
    },
  },

  computed: {
    chartOption() {
      const hasData = this.data && this.data.length > 0;
      let chartData = [];

      const baseOption = {
        grid: {
          left: '30px',
          right: '20px',
          top: '10px', // Don't cut off the top of the y-axis ticks
          bottom: '60px', // Give the slider some room
          containLabel: true,
        },
        yAxis: {
          type: 'value',
          name: this.yLabel,
          nameLocation: 'middle',
          nameGap: 30,
        },
        series: [],
      };

      if (hasData) {
        const overallStartTime = Math.min(...this.data.map(p => p.x.toMillis()));

        chartData = this.data.map(point => [point.x.toMillis() - overallStartTime, point.y]);

        return {
          ...baseOption,
          xAxis: {
            ...baseOption.xAxis,
            axisLabel: {
              formatter: (value) => {
                return this.formatDuration(value);
              },
            },
          },
          tooltip: {
            trigger: 'axis',
            formatter: (params) => {
              if (!params || params.length === 0 || !params[0] || !params[0].value) {
                return '';
              }

              // We assume this is only ever being used with fixed axis values and floating point
              // numbers, so we don't have any XSS concerns.
              const point = params[0];
              const value = point.value[1].toFixed(2);
              let tooltipText = `<b>${value}</b>`;
              if (this.yLabel) {
                tooltipText += ` ${this.yLabel}`;
              }
              return tooltipText;
            },
          },
          dataZoom: [
            {
              type: 'slider',
              start: 0,
              end: 100,
              xAxisIndex: 0,
            },
            {
              type: 'inside',
              start: 0,
              end: 100,
              xAxisIndex: 0,
            },
          ],
          series: [
            {
              data: chartData,
              type: 'line',
              symbol: 'none',
            },
          ],
        };
      }

      return baseOption;
    },
  },

  methods: {
    formatDuration(ms) {
      if (ms < 1000) {
        return `${ms}ms`;
      }
      if (ms < 60000) {
        return `${(ms / 1000).toFixed(2)}s`;
      }
      const duration = Duration.fromMillis(ms);
      return duration.toFormat("m'm' ss's'");
    },
  },
};
</script>
