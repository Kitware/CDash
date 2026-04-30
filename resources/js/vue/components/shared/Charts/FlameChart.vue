<template>
  <v-chart
    class="tw-w-full"
    :option="chartOptions"
    :style="{ height: height + 'px' }"
    autoresize
    @click="$emit('click', $event)"
  />
</template>

<script>
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { CustomChart } from 'echarts/charts';
import {
  GridComponent,
  TooltipComponent,
  DataZoomComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';
import Utils from '../Utils';

use([
  CanvasRenderer,
  CustomChart,
  GridComponent,
  TooltipComponent,
  DataZoomComponent,
]);

export default {
  name: 'FlameChart',
  components: {
    VChart,
  },
  props: {
    data: {
      type: Array,
      required: true,
    },
    tracks: {
      type: Array,
      required: true,
    },
    overallStartTime: {
      type: Number,
      required: true,
    },
    overallEndTime: {
      type: Number,
      required: true,
    },
    height: {
      type: Number,
      required: true,
    },
    tooltipFormatter: {
      type: Function,
      required: true,
    },
    renderItem: {
      type: Function,
      required: true,
    },
  },
  emits: ['click'],
  computed: {
    chartOptions() {
      return {
        tooltip: {
          confine: true,
          trigger: 'item',
          extraCssText: 'max-width: 500px; white-space: normal;',
          formatter: this.tooltipFormatter,
        },
        grid: {
          top: '0px',
          left: '10px',
          right: '10px',
          bottom: '50px',
        },
        xAxis: {
          max: this.overallEndTime,
          type: 'time',
          axisLabel: {
            formatter: val => {
              const relativeTime = val - this.overallStartTime;
              return Utils.formatDuration(relativeTime);
            },
          },
        },
        yAxis: {
          show: false,
          type: 'category',
          data: this.tracks,
          inverse: true,
        },
        dataZoom: [
          {
            type: 'slider',
            filterMode: 'weakFilter',
            showDataShadow: false,
            bottom: 5,
            height: 15,
          },
          {
            type: 'inside',
            filterMode: 'weakFilter',
          },
        ],
        series: [{
          type: 'custom',
          coordinateSystem: 'cartesian2d',
          data: this.data,
          large: true,
          progressive: 400,
          renderItem: this.renderItem,
          encode: {
            x: [1, 2],
            y: 0,
          },
        }],
      };
    },
  },
};
</script>
