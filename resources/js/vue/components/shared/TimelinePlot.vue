<template>
  <v-chart
    ref="chart"
    class="chart"
    :option="chartOption"
    autoresize
    @click="onClick"
    @dblclick="onDblClick"
  />
</template>

<script>
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { LineChart } from 'echarts/charts';
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
  DataZoomComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';

use([
  CanvasRenderer,
  LineChart,
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
  DataZoomComponent,
]);

export default {
  name: 'TimelinePlot',
  components: {
    VChart,
  },
  props: {
    plotData: {
      type: Object,
      required: true,
    },
    title: {
      type: String,
      required: true,
    },
    xLabel: {
      type: String,
      required: true,
    },
    yLabel: {
      type: String,
      required: true,
    },
  },
  computed: {
    chartOption() {
      if (!this.plotData || !Array.isArray(this.plotData)) {
        return {};
      }

      const legendFontSize = 18;
      const titleFontSize = 22;

      return {
        animation: false,
        title: {
          text: this.title,
          left: 'center',
          textStyle: {
            fontSize: titleFontSize,
          },
        },
        grid: {
          top: 80,
          right: 30,
          bottom: 80,
          left: 60,
          containLabel: true,
        },
        tooltip: {
          trigger: 'item',
          formatter: (params) => {
            const seriesName = params.seriesName;
            const xValue = params.data[0];
            const yValue = params.data[1];
            return `<b>${seriesName}</b>: ${yValue}<br/><b>${this.xLabel}</b>: ${xValue}`;
          },
        },
        legend: {
          data: this.plotData.map((d) => d.name),
          right: 30,
          top: 60,
          orient: 'vertical',
          backgroundColor: 'rgba(255, 255, 255, 0.8)',
          borderColor: 'lightgrey',
          borderWidth: 1,
          textStyle: {
            fontSize: legendFontSize,
          },
        },
        xAxis: {
          type: 'time',
          name: this.xLabel,
          nameLocation: 'middle',
          nameGap: 35,
          nameTextStyle: {
            fontSize: legendFontSize,
          },
        },
        yAxis: {
          type: 'value',
          name: this.yLabel,
          nameLocation: 'middle',
          nameGap: 45,
          nameTextStyle: {
            fontSize: legendFontSize,
          },
        },
        dataZoom: [
          {
            type: 'inside',
            xAxisIndex: 0,
            filterMode: 'none',
          },
          {
            type: 'slider',
            xAxisIndex: 0,
            filterMode: 'none',
            bottom: 10,
          },
        ],
        series: this.plotData.map((d) => ({
          name: d.name,
          type: 'line',
          data: d.values.map((v) => [v.x, v.y, v.url]),
          itemStyle: {
            color: d.color,
          },
          showSymbol: false,
          emphasis: {
            focus: 'series',
            itemStyle: {
              opacity: 1,
              borderWidth: 2,
            },
          },
          lineStyle: {
            width: 1.5,
          },
        })),
      };
    },
  },
  methods: {
    onClick(params) {
      if (params.data && params.data[2]) {
        window.location.href = params.data[2];
      }
    },
    onDblClick() {
      if (this.$refs.chart) {
        this.$refs.chart.dispatchAction({
          type: 'restore',
        });
      }
    },
  },
};
</script>

<style scoped>
.chart {
  height: 400px;
  width: 100%;
}
</style>
