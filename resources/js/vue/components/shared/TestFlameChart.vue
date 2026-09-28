<template>
  <FlameChart
    :items="chartItems"
    :category-colors="categoryColors"
    category-label="Passing/Failing"
    :tooltip-fields="tooltipFields"
    :large="false"
    :progressive="0"
    @click="onItemClick"
  />
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
    /**
     * Test records with Luxon DateTime startTime and Duration duration values, and
     * numeric measurements ({ name, value } objects).
     */
    tests: {
      type: Array,
      required: true,
    },
  },

  data() {
    return {
      categoryColors: {},
    };
  },

  computed: {
    chartItems() {
      return this.tests.map((test) => ({
        ...test,
        category: this.humanReadableTestStatus(test.status),
      }));
    },
  },

  created() {
    this.categoryColors = {
      Passed: resolveTailwindColor('tw-bg-success'),
      Failed: resolveTailwindColor('tw-bg-error'),
    };
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

    tooltipFields(test) {
      return [
        { label: 'Name', value: test.name, isBold: true },
        { label: 'SubProject', value: test.subProject },
        { label: 'Status', value: test.category },
        { label: 'Duration', value: Utils.formatDuration(test.duration.toMillis()) },
      ];
    },

    onItemClick(test) {
      window.location.href = `${this.$baseURL}/tests/${test.id}`;
    },
  },
};
</script>
