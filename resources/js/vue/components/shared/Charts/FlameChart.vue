<template>
  <div>
    <div
      v-if="hasHeatmapData"
      class="tw-flex tw-justify-end tw-px-2.5 tw-pt-2.5"
    >
      <select
        v-model="colorMode"
        class="tw-select tw-select-bordered tw-select-sm"
        data-test="flame-chart-color-mode"
      >
        <option value="category">
          {{ categoryLabel }}
        </option>
        <option value="heatmap">
          {{ heatmapMeasurement }}
        </option>
      </select>
    </div>
    <div
      v-if="colorMode === 'category'"
      class="tw-flex tw-flex-wrap tw-justify-center tw-items-center tw-gap-x-5 tw-gap-y-2.5 tw-p-2.5 tw-text-xs"
      data-test="flame-chart-legend"
    >
      <div
        v-for="(color, category) in categoryColors"
        :key="category"
        class="tw-flex tw-items-center"
      >
        <span
          class="tw-w-3 tw-h-3 tw-mr-1.5 tw-rounded-sm"
          :style="{ backgroundColor: color }"
        />
        <span class="tw-text-gray-700">{{ category }}</span>
      </div>
    </div>
    <VChart
      class="tw-w-full tw-p-0 tw-flex-grow"
      :option="chartOptions"
      :style="{ height: height + 'px' }"
      autoresize
      @click="onClick"
    />
  </div>
</template>

<script>
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { CustomChart } from 'echarts/charts';
import {
  GridComponent,
  TooltipComponent,
  DataZoomComponent,
  VisualMapContinuousComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';
import Utils from '../Utils';
import FlameChartLayout, { Dimension } from './FlameChartLayout';

use([
  CanvasRenderer,
  CustomChart,
  GridComponent,
  TooltipComponent,
  DataZoomComponent,
  VisualMapContinuousComponent,
]);

export default {
  name: 'FlameChart',

  components: {
    VChart,
  },

  props: {
    /**
     * The items to display. Each item is expected to have the following properties:
     * {
     *     id: Number|String,     // Compared against selectedId
     *     startTime: Object,     // A Luxon DateTime object
     *     duration: Object,      // A Luxon Duration object
     *     category: String,      // A key of categoryColors
     *     disabled: Boolean,     // Whether to "gray out" the item
     *     measurements: Array,   // Optional numeric measurements: { name: String, value: Number }
     * }
     */
    items: {
      type: Array,
      required: true,
    },

    /** A mapping of each item category to the color used for it and shown in the legend. */
    categoryColors: {
      type: Object,
      required: true,
    },

    /** The name of the color mode which colors items by category. */
    categoryLabel: {
      type: String,
      required: true,
    },

    /** The measurement used to color items in heatmap mode, which is only offered if an item has it. */
    heatmapMeasurement: {
      type: String,
      required: false,
      default: 'MaxRSS',
    },

    /**
     * Returns the tooltip lines for an item as { label, value, isBold, isCode } objects.
     * The item's measurements are listed after these lines.
     */
    tooltipFields: {
      type: Function,
      required: true,
    },

    /** The id of an item to outline. */
    selectedId: {
      type: [Number, String],
      required: false,
      default: null,
    },

    large: {
      type: Boolean,
      required: false,
      default: true,
    },

    progressive: {
      type: Number,
      required: false,
      default: 400,
    },
  },

  emits: {
    /** Emitted with the clicked item. */
    click: (item) => item !== undefined,
  },

  data() {
    return {
      colorMode: 'category',
      barHeight: 15,
      barSpacing: 5,
      // Keep zero-duration items visible and clickable.
      minimumBarWidth: 2,
      // Items without the heatmap measurement, shown only in heatmap mode.
      noDataColor: '#e1e0d9',
    };
  },

  computed: {
    layout() {
      return FlameChartLayout.layout(this.items, this.heatmapMeasurement);
    },

    height() {
      return ((this.barHeight + this.barSpacing) * this.layout.trackCount) + 60;
    },

    hasHeatmapData() {
      return this.layout.heatmapRange !== null;
    },

    // Let ECharts use its theme's default gradient by leaving inRange unset.
    // Read via `api.visual('color')` in renderItem.
    visualMap() {
      if (this.colorMode !== 'heatmap' || !this.hasHeatmapData) {
        return null;
      }
      const { min, max } = this.layout.heatmapRange;
      return {
        show: false,
        type: 'continuous',
        seriesIndex: 0,
        dimension: Dimension.HEATMAP_VALUE,
        min,
        max: max > min ? max : min + 1,
      };
    },

    chartOptions() {
      // Referenced here so that changing either redraws the items.
      const { colorMode, selectedId } = this;

      return {
        // An array lets vue-echarts remove the visual map without resetting the zoom.
        visualMap: this.visualMap ? [this.visualMap] : [],
        tooltip: {
          confine: true,
          trigger: 'item',
          extraCssText: 'max-width: 500px; white-space: normal;',
          formatter: this.getTooltipElement,
        },
        grid: {
          top: '0px',
          left: '10px',
          right: '10px',
          bottom: '50px',
        },
        xAxis: {
          max: this.layout.overallEndTime,
          type: 'time',
          axisLabel: {
            formatter: (val) => {
              const relativeTime = val - this.layout.overallStartTime;
              return Utils.formatDuration(relativeTime);
            },
          },
        },
        yAxis: {
          show: false,
          type: 'category',
          data: Array.from({ length: this.layout.trackCount }, (_, i) => `Track ${i + 1}`),
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
          data: this.layout.data,
          large: this.large,
          progressive: this.progressive,
          renderItem: (params, api) => this.renderItem(api, colorMode, selectedId),
          encode: {
            x: [Dimension.START_TIME, Dimension.END_TIME],
            y: Dimension.TRACK,
          },
        }],
      };
    },
  },

  watch: {
    hasHeatmapData(hasData) {
      if (!hasData) {
        this.colorMode = 'category';
      }
    },
  },

  methods: {
    itemFromEvent(params) {
      const value = params.data?.value;
      return Array.isArray(value) ? this.items[value[Dimension.ITEM_INDEX]] : undefined;
    },

    getTooltipElement(params) {
      const item = this.itemFromEvent(params);
      if (!item) {
        return '';
      }

      const container = document.createElement('div');
      const lines = [
        ...this.tooltipFields(item),
        ...(item.measurements ?? []).map((measurement) => ({
          label: measurement.name,
          value: Utils.formatMeasurement(measurement.name, measurement.value),
        })),
      ];

      lines.forEach(({ label, value, isBold = false, isCode = false }) => {
        if (!value) {
          return;
        }
        if (container.childNodes.length > 0) {
          container.appendChild(document.createElement('br'));
        }
        container.appendChild(document.createTextNode(`${label}: `));

        let valueNode;
        if (isCode) {
          valueNode = document.createElement('div');
          valueNode.className = 'tw-font-mono tw-bg-gray-100 tw-p-1 tw-rounded tw-whitespace-pre-wrap tw-break-all !tw-mt-0';
          valueNode.textContent = value;
        } else if (isBold) {
          valueNode = document.createElement('b');
          valueNode.textContent = value;
        } else {
          valueNode = document.createTextNode(value);
        }
        container.appendChild(valueNode);
      });

      return container;
    },

    onClick(params) {
      const item = this.itemFromEvent(params);
      if (item) {
        this.$emit('click', item);
      }
    },

    renderItem(api, colorMode, selectedId) {
      const track = api.value(Dimension.TRACK);
      const start = api.coord([api.value(Dimension.START_TIME), track]);
      const end = api.coord([api.value(Dimension.END_TIME), track]);
      if (!start || !end) {
        return;
      }

      const item = this.items[api.value(Dimension.ITEM_INDEX)];

      let style;
      if (colorMode === 'heatmap') {
        const hasHeatmapValue = Number.isFinite(api.value(Dimension.HEATMAP_VALUE));
        style = { fill: hasHeatmapValue ? api.visual('color') : this.noDataColor, opacity: 0.9 };
      } else {
        style = { fill: this.categoryColors[item.category], opacity: 0.85 };
      }

      if (item.disabled) {
        Object.assign(style, {
          fill: '#d1d5db',
          stroke: '#d1d5db',
          lineWidth: 0.5,
          opacity: 0.4,
        });
      }

      if (selectedId !== null && item.id === selectedId) {
        Object.assign(style, {
          stroke: '#000000',
          lineWidth: 2,
        });
      }

      return {
        type: 'rect',
        shape: {
          x: start[0],
          y: start[1] - (this.barHeight / 2),
          width: Math.max(end[0] - start[0], this.minimumBarWidth),
          height: this.barHeight,
        },
        style: style,
      };
    },
  },
};
</script>
