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
    <flame-chart
      class="tw-w-full tw-p-0 tw-flex-grow"
      :data="processedChartData.data"
      :tracks="processedChartData.tracks"
      :overall-start-time="processedChartData.overallStartTime"
      :overall-end-time="processedChartData.overallEndTime"
      :height="totalChartHeight"
      :tooltip-formatter="getTooltipElement"
      :render-item="renderItem"
      @click="onCellClick"
    />
    <template v-if="selectedCommandId">
      <div class="tw-divider" />
      <command-info-card :command-id="selectedCommandId" />
    </template>
  </div>
</template>

<script>
import FlameChart from './Charts/FlameChart.vue';
import CommandInfoCard from './CommandInfoCard.vue';
import Utils from './Utils';

export default {
  name: 'CommandFlameChart',
  components: {
    CommandInfoCard,
    FlameChart,
  },
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
      selectedCommandId: null,
    };
  },
  computed: {
    processedChartData() {
      if (!this.commands || this.commands.length === 0) {
        return {
          data: [],
          tracks: [],
          overallStartTime: 0,
          overallEndTime: 0,
        };
      }

      const rawCommandData = this.commands.map((cmd, index) => {
        const startTime = cmd.startTime.toMillis();
        const endTime = startTime + cmd.duration.toMillis();
        return {
          ...cmd,
          startTime,
          endTime,
          originalIndex: index,
        };
      });

      const overallStartTime = Math.min(...rawCommandData.map(cmd => cmd.startTime));
      const overallEndTime = Math.max(...rawCommandData.map(cmd => cmd.endTime));

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

      return {
        data: processedData,
        tracks: trackEndTimes.map((_, i) => `Thread ${i + 1}`),
        overallStartTime,
        overallEndTime,
      };
    },

    totalChartHeight() {
      const numtracks = this.processedChartData.tracks.length;
      return (this.commandBarHeight + this.commandBarSpacing) * numtracks + 60;
    },
  },
  methods: {
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
      // No-op, vue-echarts handles resize via autoresize prop
    },

    onCellClick(params) {
      if (!params.data.value || !Array.isArray(params.data.value)) {
        return;
      }

      const clickedCommandId = parseInt(params.data.value[11]);
      if (this.selectedCommandId === clickedCommandId) {
        this.selectedCommandId = null;
      }
      else {
        this.selectedCommandId = clickedCommandId;
      }
    },

    renderItem(params, api) {
      const trackIndex = api.value(0);
      const start = api.coord([api.value(1), trackIndex]);
      const end = api.coord([api.value(2), trackIndex]);
      if (!start || !end) {
        return;
      }

      const height = this.commandBarHeight;
      const itemType = api.value(5);
      const isDisabled = api.value(6);
      const commandId = api.value(11);

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

      if (commandId === this.selectedCommandId) {
        Object.assign(style, {
          stroke: '#000000',
          lineWidth: 2,
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
  },
};
</script>
