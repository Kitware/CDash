<template>
  <div>
    <div
      id="legend-container"
      class="tw-flex tw-flex-wrap tw-justify-center tw-gap-x-5 tw-gap-y-2.5 tw-p-2.5 tw-text-xs"
    >
      <div
        v-for="(color, type) in colors"
        :key="type"
        class="tw-flex tw-items-center"
      >
        <span
          class="tw-w-3 tw-h-3 tw-mr-1.5 tw-rounded-sm"
          :style="{ backgroundColor: color }"
        />
        <span class="tw-text-gray-700">{{ type }}</span>
      </div>
    </div>
    <div
      ref="chartContainerRef"
      class="tw-w-full tw-p-0 tw-flex-grow"
    />
    <template v-if="selectedCommandId">
      <div class="tw-divider" />
      <command-info-card :command-id="selectedCommandId" />
    </template>
  </div>
</template>

<script>
import * as echarts from 'echarts';
import CommandInfoCard from './CommandInfoCard.vue';
import Utils from './Utils';

export default {
  components: {CommandInfoCard},
  props: {
    /**
     * An array of command objects. Each object is expected to have the following properties:
     * {
     *     id: Number,
     *     startTime: Object, // A Luxon DateTime object
     *     duration: Object,  // A Luxon Duration object.
     *     type: String,
     *     disabled: Boolean  // Whether to "gray out" the command
     *     targetName: String
     *     source: String,
     *     command: String,
     *     language: String,
     *     config: String,
     * }
     */
    commands: {
      type: Array,
      required: true,
    },
  },
  data() {
    return {
      colors: {
        // TODO: Use Tailwind for these colors instead.
        'COMPILE': '#0072B2',
        'LINK': '#009E73',
        'CUSTOM': '#D55E00',
        'CMAKE_BUILD': '#F0E442',
        'CMAKE_INSTALL': '#CC79A7',
        'INSTALL': '#56B4E9',
      },
      commandBarHeight: 15,
      commandBarSpacing: 5,
      processedChartData: {},
      overallStartTime: 0,
      selectedCommandId: null,
    };
  },
  watch: {
    commands: {
      handler(newCommands) {
        if (newCommands && newCommands.length > 0) {
          this.renderChart(newCommands);
        }
      },
      deep: true,
    },
  },
  created() {
    this.chart = null;
  },
  mounted() {
    this.$nextTick(() => {
      window.addEventListener('resize', this.handleResize);
      if (this.commands && this.commands.length > 0) {
        this.renderChart(this.commands);
      }
    });
  },
  unmounted() {
    if (this.chart) {
      this.chart.dispose();
    }
    window.removeEventListener('resize', this.handleResize);
  },
  methods: {
    processData(incomingCommands) {
      const rawCommandData = incomingCommands.map((cmd, index) => {
        const startTime = cmd.startTime.toMillis();
        const endTime = startTime + cmd.duration.toMillis();
        return {
          ...cmd,
          startTime,
          endTime,
          originalIndex: index,
        };
      });

      if (rawCommandData.length > 0) {
        this.overallStartTime = Math.min(...rawCommandData.map(cmd => cmd.startTime));
      }
      else {
        this.overallStartTime = 0;
      }

      const trackEndTimes = [];
      const processedData = [];
      const sortedData = rawCommandData.slice().sort((a, b) => a.startTime - b.startTime);

      sortedData.forEach(command => {
        let placed = false;
        let trackIndex = -1;
        for (let i = 0; i < trackEndTimes.length; i++) {
          if (command.startTime >= trackEndTimes[i]) {
            trackEndTimes[i] = command.endTime;
            trackIndex = i;
            placed = true;
            break;
          }
        }
        if (!placed) {
          trackIndex = trackEndTimes.length;
          trackEndTimes.push(command.endTime);
        }

        const processedCommand = {
          value: [
            trackIndex,
            command.startTime,
            command.endTime,
            command.duration.toMillis(),
            command.originalIndex,
            command.type,
            command.disabled,
            command.targetName,
            command.source,
            command.language,
            command.config,
            command.id,
          ],
        };
        processedData.push(processedCommand);
      });

      this.processedChartData = {
        data: processedData,
        tracks: trackEndTimes.map((_, i) => `Thread ${i + 1}`),
      };
    },

    initializeChart() {
      if (!this.$refs.chartContainerRef || this.chart) {
        return;
      }
      this.chart = echarts.init(this.$refs.chartContainerRef);
      this.chart.on('click', this.onCellClick);
    },

    renderChart(commands) {
      this.processData(commands);

      const numtracks = this.processedChartData.tracks.length;
      const overallEndTime = Math.max(...this.processedChartData.data.map(d => d.value[2]));
      const totalChartHeight = (this.commandBarHeight + this.commandBarSpacing) * numtracks + 60;

      // The container size must be set before initializing the chart.
      if (this.$refs.chartContainerRef) {
        this.$refs.chartContainerRef.style.height = `${totalChartHeight}px`;
      }

      // If the chart instance doesn't exist yet, initialize it now that the container has dimensions.
      if (!this.chart) {
        this.initializeChart();
      }

      // Ensure the chart is resized to fit the newly set container height.
      this.chart.resize();

      const option = {
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
          max: overallEndTime,
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
          data: this.processedChartData.tracks,
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
          id: 'command-series',
          type: 'custom',
          coordinateSystem: 'cartesian2d',
          data: this.processedChartData.data,
          large: true,
          progressive: 400,
          renderItem: (params, api) => {
            const trackIndex = api.value(0);
            const start = api.coord([api.value(1), trackIndex]);
            const end = api.coord([api.value(2), trackIndex]);
            if (!start || !end) {
              return;
            }

            const height = this.commandBarHeight;
            const itemType = api.value(5);
            const isDisabled = api.value(6);

            const style = {
              fill: this.colors[itemType],
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
            return {
              type: 'rect',
              shape: {
                x: start[0],
                y: start[1] - height / 2,
                width: end[0] - start[0],
                height: height,
              },
              style: style,
            };
          },
          encode: {
            x: [1, 2],
            y: 0,
          },
        }],
      };

      this.chart.setOption(option, true);
    },

    getTooltipElement(params) {
      if (!params.data.value || !Array.isArray(params.data.value)) {
        return '';
      }
      const data = params.data.value;
      const type = data[5];
      const duration = Utils.formatDuration(data[3]);
      const targetName = data[7];
      const source = data[8];
      const language = data[9];
      const config = data[10];

      const container = document.createElement('div');

      const appendLine = (label, value, isBold = false, isCode = false) => {
        if (value) {
          if (container.childNodes.length > 0) {
            container.appendChild(document.createElement('br'));
          }
          const labelNode = document.createTextNode(`${label}: `);
          container.appendChild(labelNode);

          let valueNode;
          if (isCode) {
            valueNode = document.createElement('div');
            valueNode.className = 'tw-font-mono tw-bg-gray-100 tw-p-1 tw-rounded tw-whitespace-pre-wrap tw-break-all !tw-mt-0';
            valueNode.textContent = value;
          }
          else if (isBold) {
            valueNode = document.createElement('b');
            valueNode.textContent = value;
          }
          else {
            valueNode = document.createTextNode(value);
          }
          container.appendChild(valueNode);
        }
      };

      appendLine('Target', targetName, true);
      appendLine('Type', type);
      appendLine('Duration', duration);
      appendLine('Language', language);
      appendLine('Config', config);
      appendLine('Source', source, false, true);

      return container;
    },

    handleResize() {
      if (this.chart) {
        this.chart.resize();
      }
    },

    onCellClick(params) {
      if (!params.data.value || !Array.isArray(params.data.value)) {
        return;
      }

      this.selectedCommandId = parseInt(params.data.value[11]);
    },
  },
};
</script>
