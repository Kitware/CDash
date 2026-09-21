<template>
  <div>
    <div
      id="test-legend-container"
      class="tw-flex tw-flex-wrap tw-justify-center tw-gap-x-5 tw-gap-y-2.5 tw-p-2.5 tw-text-xs"
    >
      <div
        v-for="(colorClass, status) in colorClasses"
        :key="status"
        class="tw-flex tw-items-center"
      >
        <span
          class="tw-w-3 tw-h-3 tw-mr-1.5 tw-rounded-sm"
          :class="colorClass"
        />
        <span class="tw-text-gray-700">{{ status }}</span>
      </div>
    </div>
    <FlameChart
      class="tw-w-full tw-p-0 tw-flex-grow"
      :data="processedChartData.data"
      :tracks="processedChartData.tracks"
      :overall-start-time="processedChartData.overallStartTime"
      :overall-end-time="processedChartData.overallEndTime"
      :height="totalChartHeight"
      :tooltip-formatter="getTooltipElement"
      :render-item="renderItem"
      :large="false"
      :progressive="0"
      @click="onCellClick"
    />
  </div>
</template>

<script>
import FlameChart from './Charts/FlameChart.vue';
import Utils from './Utils';

// Convert DaisyUI's OKLCH colors to RGB so ECharts can compute hover colors.
function resolveTailwindColor(colorClass) {
  const swatch = document.createElement('span');
  swatch.className = colorClass;
  swatch.style.position = 'absolute';
  swatch.style.visibility = 'hidden';
  document.body.appendChild(swatch);
  const computedColor = getComputedStyle(swatch).backgroundColor;
  document.body.removeChild(swatch);

  const canvas = document.createElement('canvas');
  canvas.width = 1;
  canvas.height = 1;
  const context = canvas.getContext('2d');
  context.fillStyle = computedColor;
  context.fillRect(0, 0, 1, 1);
  const [r, g, b] = context.getImageData(0, 0, 1, 1).data;
  return `rgb(${r}, ${g}, ${b})`;
}

export default {
  name: 'TestFlameChart',

  components: {
    FlameChart,
  },

  props: {
    /** Test records with Luxon DateTime startTime and Duration duration values. */
    tests: {
      type: Array,
      required: true,
    },
  },

  data() {
    return {
      colorClasses: {
        Passed: 'tw-bg-success',
        Failed: 'tw-bg-error',
      },
      resolvedColors: {},
      testBarHeight: 15,
      testBarSpacing: 5,
      minimumBarWidth: 2,
    };
  },

  computed: {
    processedChartData() {
      if (!this.tests || this.tests.length === 0) {
        return {
          data: [],
          tracks: [],
          overallStartTime: 0,
          overallEndTime: 0,
        };
      }

      const rawTestData = this.tests.map((test, index) => {
        const startTime = test.startTime.toMillis();
        const endTime = startTime + test.duration.toMillis();
        return {
          ...test,
          startTime,
          endTime,
          originalIndex: index,
        };
      });

      const overallStartTime = rawTestData.reduce((start, test) => Math.min(start, test.startTime), Infinity);
      const overallEndTime = rawTestData.reduce((end, test) => Math.max(end, test.endTime), -Infinity);

      const trackEndTimes = [];
      const processedData = [];
      const sortedData = rawTestData.slice().sort((a, b) => a.startTime - b.startTime);

      sortedData.forEach((test) => {
        let placed = false;
        let trackIndex = -1;
        for (let i = 0; i < trackEndTimes.length; i++) {
          if (test.startTime >= trackEndTimes[i]) {
            trackEndTimes[i] = test.endTime;
            trackIndex = i;
            placed = true;
            break;
          }
        }
        if (!placed) {
          trackIndex = trackEndTimes.length;
          trackEndTimes.push(test.endTime);
        }

        const processedTest = {
          value: [
            trackIndex,
            test.startTime,
            test.endTime,
            test.duration.toMillis(),
            test.originalIndex,
            test.status,
            test.disabled,
            test.name,
            test.subProject,
            test.id,
          ],
        };
        processedData.push(processedTest);
      });

      return {
        data: processedData,
        tracks: trackEndTimes.map((_, i) => `Slot ${i + 1}`),
        overallStartTime,
        overallEndTime,
      };
    },

    totalChartHeight() {
      const numtracks = this.processedChartData.tracks.length;
      return ((this.testBarHeight + this.testBarSpacing) * numtracks) + 60;
    },
  },

  created() {
    this.resolvedColors = Object.fromEntries(
      Object.entries(this.colorClasses).map(([status, colorClass]) => [
        status,
        resolveTailwindColor(colorClass),
      ]),
    );
  },

  methods: {
    humanReadableTestStatus(status) {
      switch (status) {
        case 'PASSED':
          return 'Passed';
        case 'FAILED':
          return 'Failed';
        case 'NOT_RUN':
          return 'Not Run';
        default:
          return status;
      }
    },

    getTooltipElement(params) {
      if (!params.data.value || !Array.isArray(params.data.value)) {
        return '';
      }
      const data = params.data.value;
      const duration = Utils.formatDuration(data[3]);
      const status = this.humanReadableTestStatus(data[5]);
      const name = data[7];
      const subProject = data[8];

      const container = document.createElement('div');

      const appendLine = (label, value, isBold = false) => {
        if (value) {
          if (container.childNodes.length > 0) {
            container.appendChild(document.createElement('br'));
          }
          const labelNode = document.createTextNode(`${label}: `);
          container.appendChild(labelNode);

          if (isBold) {
            const valueNode = document.createElement('b');
            valueNode.textContent = value;
            container.appendChild(valueNode);
          } else {
            container.appendChild(document.createTextNode(value));
          }
        }
      };

      appendLine('Name', name, true);
      appendLine('SubProject', subProject);
      appendLine('Status', status);
      appendLine('Duration', duration);

      return container;
    },

    onCellClick(params) {
      if (!params.data.value || !Array.isArray(params.data.value)) {
        return;
      }

      const testId = params.data.value[9];
      window.location.href = `${this.$baseURL}/tests/${testId}`;
    },

    renderItem(params, api) {
      const trackIndex = api.value(0);
      const start = api.coord([api.value(1), trackIndex]);
      const end = api.coord([api.value(2), trackIndex]);
      if (!start || !end) {
        return;
      }

      const height = this.testBarHeight;
      const status = this.humanReadableTestStatus(api.value(5));
      const isDisabled = api.value(6);

      const style = {
        fill: this.resolvedColors[status],
        opacity: 0.85,
      };

      if (isDisabled) {
        Object.assign(style, {
          fill: '#d1d5db',
          stroke: '#d1d5db',
          lineWidth: 0.5,
          opacity: 0.4,
        });
      }

      // Keep zero-duration tests visible and clickable.
      const width = Math.max(end[0] - start[0], this.minimumBarWidth);

      return {
        type: 'rect',
        shape: {
          x: start[0],
          y: start[1] - (height / 2),
          width: width,
          height: height,
        },
        style: style,
      };
    },
  },
};
</script>
