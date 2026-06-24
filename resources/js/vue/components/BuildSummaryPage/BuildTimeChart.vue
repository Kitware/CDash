<template>
  <VChart
    class="tw-w-full tw-h-60 tw-cursor-pointer"
    :option="chartOption"
    autoresize
    @click="handleChartClick"
  >
    <template #tooltip="params">
      <div
        v-if="params && params.length > 0"
        class="tw-text-sm"
      >
        <strong>Start:</strong> {{ tooltipTimestamp(params[0].dataIndex) }}<br>
        <strong>Configure Time:</strong> {{ tooltipDuration(params[0].dataIndex, 'configureTime') }}<br>
        <strong>Build Time:</strong> {{ tooltipDuration(params[0].dataIndex, 'buildTime') }}<br>
        <strong>Test Time:</strong> {{ tooltipDuration(params[0].dataIndex, 'testTime') }}
      </div>
    </template>
  </VChart>
</template>

<script>
import VChart from 'vue-echarts';
import Utils from '../shared/Utils';

import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart } from 'echarts/charts';
import { TooltipComponent, GridComponent, MarkAreaComponent } from 'echarts/components';

echarts.use([
  CanvasRenderer,
  BarChart,
  TooltipComponent,
  GridComponent,
  MarkAreaComponent,
]);

export default {
  name: 'BuildTimeChart',

  components: {
    VChart,
  },

  props: {
    /**
     * An array of data points to plot.
     *
     * @type {Array<{
     *   configureTime: import('luxon').Duration,
     *   buildTime: import('luxon').Duration,
     *   testTime: import('luxon').Duration,
     *   buildId: number | string,
     *   startTimestamp: import('luxon').DateTime | string,
     *   configureFailed: boolean,
     *   buildFailed: boolean,
     *   testFailed: boolean
     * }>}
     */
    data: {
      type: Array,
      required: true,
    },
  },

  computed: {
    chartOption() {
      const hasData = this.data && this.data.length > 0;

      const baseOption = {
        animation: false,
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
        },
        grid: {
          left: '10px',
          right: '10px',
          top: '20px',
          bottom: '10px',
          containLabel: true,
        },
        yAxis: {
          type: 'value',
          name: 'Total Time',
          nameLocation: 'middle',
          nameGap: 60,
          axisLabel: {
            formatter: (value) => Utils.formatDuration(value),
          },
        },
      };

      if (hasData) {
        return {
          ...baseOption,
          xAxis: {
            type: 'category',
            data: this.data.map(d => d.buildId),
            axisLabel: {
              show: false,
            },
          },
          series: [
            {
              name: 'Configure',
              type: 'bar',
              stack: 'total',
              data: this.data.map(d => d.configureTime.toMillis()),
              color: '#bce784',
            },
            {
              name: 'Build',
              type: 'bar',
              stack: 'total',
              data: this.data.map(d => d.buildTime.toMillis()),
              color: '#5dd39e',
            },
            {
              name: 'Test',
              type: 'bar',
              stack: 'total',
              data: this.data.map(d => d.testTime.toMillis()),
              color: '#348aa7',
              markArea: {
                silent: true,
                data: this.data.reduce((acc, d, i) => {
                  if (d.configureFailed || d.buildFailed || d.testFailed) {
                    acc.push([
                      { xAxis: i },
                      { xAxis: i },
                    ]);
                  }
                  return acc;
                }, []),
                itemStyle: {
                  color: 'rgba(255, 100, 100, 0.18)',
                },
              },
            },
          ],
        };
      }

      return baseOption;
    },
  },

  methods: {
    tooltipTimestamp(index) {
      const buildInfo = this.data[index];
      if (!buildInfo) {
        return '';
      }
      return buildInfo.startTimestamp && buildInfo.startTimestamp.toFormat
        ? buildInfo.startTimestamp.toFormat('yyyy-MM-dd HH:mm:ss')
        : buildInfo.startTimestamp;
    },

    tooltipDuration(index, field) {
      const buildInfo = this.data[index];
      if (!buildInfo) {
        return '';
      }
      return Utils.formatDuration(buildInfo[field].toMillis());
    },

    handleChartClick(params) {
      if (params && params.dataIndex !== undefined) {
        const buildInfo = this.data[params.dataIndex];
        if (buildInfo && buildInfo.buildId) {
          window.location.href = `${this.$baseURL}/builds/${buildInfo.buildId}`;
        }
      }
    },
  },
};
</script>
