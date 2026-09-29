<template>
  <div>
    <FlameChart
      :items="chartItems"
      :category-colors="colors"
      category-label="Command Type"
      :tooltip-fields="tooltipFields"
      :selected-id="selectedCommandId"
      @click="onItemClick"
    />
    <template v-if="selectedCommandId">
      <div class="tw-divider" />
      <CommandInfoCard :command-id="Number(selectedCommandId)" />
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
     *     measurements: Array, // Optional numeric measurements: { name: String, value: Number }
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
        COMPILE: '#0072B2',
        LINK: '#009E73',
        CUSTOM: '#D55E00',
        CMAKE_BUILD: '#F0E442',
        CMAKE_INSTALL: '#CC79A7',
        INSTALL: '#56B4E9',
      },
      selectedCommandId: null,
    };
  },

  computed: {
    chartItems() {
      return this.commands.map((command) => ({
        ...command,
        category: command.type,
      }));
    },
  },

  methods: {
    tooltipFields(command) {
      return [
        { label: 'Target', value: command.targetName, isBold: true },
        { label: 'Type', value: command.type },
        { label: 'Duration', value: Utils.formatDuration(command.duration.toMillis()) },
        { label: 'Language', value: command.language },
        { label: 'Config', value: command.config },
        { label: 'Source', value: command.source, isCode: true },
      ];
    },

    onItemClick(command) {
      this.selectedCommandId = this.selectedCommandId === command.id ? null : command.id;
    },
  },
};
</script>
