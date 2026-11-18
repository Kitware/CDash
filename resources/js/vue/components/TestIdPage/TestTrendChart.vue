<template>
  <div class="tw-w-full">
    <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-gap-2 tw-mb-2">
      <select
        id="measurement-switcher"
        v-model="selectedMeasurement"
        class="tw-select tw-select-bordered tw-select-sm"
        data-test="measurement-switcher"
      >
        <option value="time">
          Test Time
        </option>
        <option
          v-for="measurement in numericMeasurements"
          :key="measurement.name"
          :value="measurement.name"
        >
          {{ measurement.name }}
        </option>
      </select>
      <TrendFilterBar
        v-model="filters"
        :build="{ siteName, name: buildName, group: buildType }"
      >
        <a
          class="tw-btn tw-btn-sm tw-btn-outline tw-font-normal tw-border-base-300 tw-text-neutral-500 hover:tw-bg-base-200 hover:tw-text-neutral-700"
          :class="{ 'tw-invisible': !historyUrl, 'tw-opacity-50 tw-pointer-events-none': loading }"
          :aria-disabled="loading || undefined"
          data-test="test-history-link"
          :href="historyUrl && !loading ? historyUrl : undefined"
        >
          <FontAwesomeIcon
            :icon="FA.faLink"
            size="xs"
          />
          Show History
        </a>
      </TrendFilterBar>
    </div>
    <div
      v-if="loading && history.length === 0"
      class="tw-flex tw-justify-center tw-items-center tw-h-60"
    >
      <span class="tw-loading tw-loading-spinner tw-loading-lg" />
    </div>
    <VChart
      v-else
      class="tw-w-full tw-h-60 tw-cursor-pointer tw-transition-opacity"
      :class="{ 'tw-opacity-50': loading }"
      :aria-busy="loading"
      :option="chartOption"
      autoresize
      data-test="trend-chart"
      :data-test-selected-measurement="selectedMeasurement"
      :data-test-point-count="chartData.length"
      @click="handleChartClick"
    >
      <template #tooltip="tooltipParams">
        <div
          v-if="tooltipParams && tooltipParams.length > 0"
          class="tw-p-2"
        >
          <div v-if="chartData[tooltipParams[0].dataIndex]">
            <strong>Date:</strong> {{ new Date(chartData[tooltipParams[0].dataIndex].x).toLocaleString() }}<br>
            <template
              v-for="field in tooltipBuildFields"
              :key="field.key"
            >
              <strong>{{ field.label }}:</strong> {{ chartData[tooltipParams[0].dataIndex][field.key] }}<br>
            </template>
            <strong>Status:</strong> {{ chartData[tooltipParams[0].dataIndex].status }}<br>
            <strong>{{ selectedMeasurementLabel }}:</strong> {{ formatValue(chartData[tooltipParams[0].dataIndex].y) }}
            <template v-if="selectedMeasurement === 'time' && enableTestTiming && chartData[tooltipParams[0].dataIndex].status === 'PASSED'">
              <br><strong>Timing Status:</strong> {{ chartData[tooltipParams[0].dataIndex].timeStatusCategory }}
              <br><strong>Timing Threshold:</strong> {{ formatDuration(getUpperThreshold(chartData[tooltipParams[0].dataIndex])) }}
            </template>
          </div>
        </div>
      </template>
    </VChart>
  </div>
</template>

<script>
import VChart from 'vue-echarts';
import Utils from '../shared/Utils';
import TrendFilterBar from '../shared/TrendFilterBar.vue';
import { loadTrendFilters, saveTrendFilters, trendBuildConditions, trendHistoryFilterParams, variedBuildFields } from '../shared/TrendFilters';
import gql from 'graphql-tag';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faLink } from '@fortawesome/free-solid-svg-icons';

import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart, LineChart, CustomChart } from 'echarts/charts';
import { TooltipComponent, GridComponent } from 'echarts/components';

echarts.use([
  CanvasRenderer,
  BarChart,
  LineChart,
  CustomChart,
  TooltipComponent,
  GridComponent,
]);

export default {
  name: 'TestTrendChart',

  components: {
    VChart,
    FontAwesomeIcon,
    TrendFilterBar,
  },

  props: {
    testName: {
      type: String,
      required: true,
    },
    projectId: {
      type: Number,
      required: true,
    },
    projectName: {
      type: String,
      required: true,
    },
    siteId: {
      type: Number,
      required: true,
    },
    siteName: {
      type: String,
      required: true,
    },
    buildName: {
      type: String,
      required: true,
    },
    buildType: {
      type: String,
      required: true,
    },
    numericMeasurements: {
      type: Array,
      default: () => [],
    },
    enableTestTiming: {
      type: Boolean,
      default: false,
    },
    testTimeStdMultiplier: {
      type: Number,
      default: 0,
    },
    testTimeStdThreshold: {
      type: Number,
      default: 0,
    },
    buildStartTime: {
      type: String,
      required: true,
    },
  },

  apollo: {
    history: {
      query: gql`
        query TestTrend(
          $projectId: ID!,
          $testName: String!,
          $limit: Int!,
          $buildFilters: ProjectBuildsFiltersMultiFilterInput,
          $measurementFilters: TestTestMeasurementsFiltersMultiFilterInput
        ) {
          project(id: $projectId) {
            id
            builds(
              first: $limit,
              orderBy: [{ column: START_TIME, order: DESC }],
              onlyParents: false,
              filters: $buildFilters
            ) {
              edges {
                node {
                  id
                  name
                  buildType
                  startTime
                  site {
                    id
                    name
                  }
                  tests(filters: { eq: { name: $testName } }) {
                    edges {
                      node {
                        id
                        status
                        runningTime
                        meanRunningTime
                        stdDevRunningTime
                        timeStatusCategory
                        testMeasurements(filters: $measurementFilters) {
                          id
                          name
                          value
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      `,
      variables() {
        const measurementNames = this.numericMeasurements.map((measurement) => measurement.name);
        const measurementFilters = measurementNames.length > 0
          ? { any: measurementNames.map((name) => ({ eq: { name } })) }
          : { eq: { name: 'NonExistentMeasurementToReturnNothing' } };

        return {
          projectId: this.projectId,
          testName: this.testName,
          limit: this.filters.limit,
          buildFilters: {
            all: [
              ...trendBuildConditions(this.filters, {
                name: this.buildName,
                buildType: this.buildType,
                siteId: this.siteId,
              }),
              { has: { tests: { eq: { name: this.testName } } } },
              { le: { startTime: this.buildStartTime } },
            ],
          },
          measurementFilters,
        };
      },
      skip() {
        return !this.projectId || !this.testName || !this.buildName || !this.buildType || !this.siteId || !this.buildStartTime;
      },
      update(data) {
        if (!data.project || !data.project.builds) {
          return [];
        }
        // Flatten the builds -> tests structure
        const tests = [];
        data.project.builds.edges.forEach((buildEdge) => {
          const build = buildEdge.node;
          build.tests.edges.forEach((testEdgeRecord) => {
            const testRecord = testEdgeRecord.node;
            tests.push({
              ...testRecord,
              buildName: build.name,
              buildType: build.buildType,
              siteName: build.site.name,
              startTime: build.startTime,
              startTimeTimestamp: new Date(build.startTime).getTime(),
            });
          });
        });
        return tests.sort((testA, testB) => testA.startTimeTimestamp - testB.startTimeTimestamp);
      },
    },
  },

  data() {
    return {
      selectedMeasurement: 'time',
      filters: loadTrendFilters('test'),
      history: [],
      FA: {
        faLink,
      },
    };
  },

  computed: {
    loading() {
      return this.$apollo.queries.history.loading;
    },

    chartData() {
      if (!this.history) {
        return [];
      }
      return this.history.map((testRecord) => {
        const measurement = this.selectedMeasurement === 'time'
          ? null
          : testRecord.testMeasurements.find((measurement) => measurement.name === this.selectedMeasurement);

        return {
          x: testRecord.startTimeTimestamp,
          y: this.selectedMeasurement === 'time' ? testRecord.runningTime : (measurement ? parseFloat(measurement.value) : 0),
          status: testRecord.status,
          buildName: testRecord.buildName,
          buildType: testRecord.buildType,
          siteName: testRecord.siteName,
          buildtestid: testRecord.id,
          meanRunningTime: testRecord.meanRunningTime,
          stdDevRunningTime: testRecord.stdDevRunningTime,
          timeStatusCategory: testRecord.timeStatusCategory,
        };
      });
    },

    /**
     * The test's results in the builds shown in the chart.
     */
    historyUrl() {
      if (!this.history || this.history.length === 0) {
        return '';
      }
      const params = trendHistoryFilterParams(this.filters, {
        name: this.buildName,
        buildType: this.buildType,
        siteName: this.siteName,
        startTime: this.buildStartTime,
      }, this.history[0].startTime, [['testname', 61, this.testName]]);
      return `${this.$baseURL}/queryTests.php?project=${encodeURIComponent(this.projectName)}&${params}`;
    },

    tooltipBuildFields() {
      return variedBuildFields(this.chartData);
    },

    yAxisMax() {
      if (!this.chartData || this.chartData.length === 0) {
        return 1;
      }
      let max = Math.max(...this.chartData.map((dataPoint) => dataPoint.y));
      if (this.selectedMeasurement === 'time' && this.enableTestTiming) {
        const historicalMax = this.chartData.reduce((maxVal, dataPoint) => {
          if (dataPoint.status !== 'PASSED') {
            return maxVal;
          }
          const threshold = Math.max(this.testTimeStdThreshold, this.testTimeStdMultiplier * dataPoint.stdDevRunningTime);
          return Math.max(maxVal, dataPoint.meanRunningTime + threshold);
        }, 0);
        max = Math.max(max, historicalMax);
      }
      return max > 0 ? max * 1.1 : 1;
    },

    barSeriesData() {
      return this.chartData.map((dataPoint) => {
        const isFailed = dataPoint.status && dataPoint.status.toLowerCase() === 'failed';
        return {
          value: dataPoint.y,
          itemStyle: {
            color: isFailed ? '#d9534f' : '#5cb85c',
            decal: isFailed ? {
              symbol: 'rect',
              dashArrayX: [1, 0],
              dashArrayY: [4, 8],
              rotation: Math.PI / 4,
            } : null,
          },
        };
      });
    },

    thresholdSeriesData() {
      if (this.selectedMeasurement !== 'time' || !this.enableTestTiming) {
        return [];
      }
      return this.chartData.map((dataPoint, index) => [index, this.getUpperThreshold(dataPoint)]);
    },
    selectedMeasurementLabel() {
      return this.selectedMeasurement === 'time'
        ? 'Test Time'
        : this.selectedMeasurement;
    },

    chartOption() {
      const hasData = this.chartData && this.chartData.length > 0;
      const measurementLabel = this.selectedMeasurementLabel;

      const baseOption = {
        animation: false,
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
        },
        grid: {
          left: '60px',
          right: '10px',
          top: '30px',
          bottom: '10px',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          data: this.chartData.map((dataPoint) => dataPoint.buildtestid),
          axisLabel: {
            show: false,
          },
        },
        yAxis: {
          type: 'value',
          name: measurementLabel,
          nameLocation: 'middle',
          nameGap: 45,
          min: 0,
          max: this.yAxisMax,
          axisLabel: {
            formatter: (value) => this.selectedMeasurement === 'time' ? Utils.formatDuration(value * 1000) : value,
          },
        },
      };

      if (hasData) {
        return {
          ...baseOption,
          series: [
            {
              name: measurementLabel,
              type: 'bar',
              barWidth: '70%',
              data: this.barSeriesData,
            },
            ...(this.selectedMeasurement === 'time' && this.enableTestTiming ? [
              {
                name: 'Upper Threshold Background',
                type: 'custom',
                renderItem: (renderParams, chartApi) => this.renderThresholdBackground(renderParams, chartApi),
                data: this.thresholdSeriesData,
                z: 1,
              },
              {
                name: 'Upper Threshold Line',
                type: 'custom',
                renderItem: (renderParams, chartApi) => this.renderThresholdLine(renderParams, chartApi),
                data: this.thresholdSeriesData,
                z: 3,
              },
            ] : []),
          ],
        };
      }

      return baseOption;
    },
  },

  watch: {
    filters(filters) {
      saveTrendFilters('test', filters);
    },
  },

  methods: {
    handleChartClick(eventParams) {
      if (eventParams && eventParams.dataIndex !== undefined) {
        const item = this.chartData[eventParams.dataIndex];
        if (item && item.buildtestid) {
          window.location.href = `${this.$baseURL}/tests/${item.buildtestid}`;
        }
      }
    },

    formatDuration(seconds) {
      return Utils.formatDuration(seconds * 1000);
    },

    formatValue(value) {
      return this.selectedMeasurement === 'time'
        ? this.formatDuration(value)
        : value;
    },

    getUpperThreshold(item) {
      const threshold = Math.max(this.testTimeStdThreshold, this.testTimeStdMultiplier * item.stdDevRunningTime);
      return item.meanRunningTime + threshold;
    },

    getPreviousPassedIndex(currentIndex) {
      let previousIndex = currentIndex - 1;
      while (previousIndex >= 0 && this.chartData[previousIndex].status !== 'PASSED') {
        previousIndex--;
      }
      return previousIndex;
    },

    renderThresholdBackground(renderParams, chartApi) {
      const index = renderParams.dataIndex;
      const dataItem = this.chartData[index];
      if (dataItem.status !== 'PASSED') {
        return;
      }

      const xCenter = chartApi.coord([index, 0])[0];
      const fullWidth = chartApi.size([1, 0])[0];
      const barWidth = fullWidth * 0.7;
      const xLeft = xCenter - (barWidth / 2);
      const threshold = chartApi.value(1);
      const yThreshold = chartApi.coord([0, threshold])[1];
      const yZero = chartApi.coord([0, 0])[1];

      const children = [
        {
          type: 'rect',
          shape: {
            x: xLeft,
            y: yThreshold,
            width: barWidth,
            height: yZero - yThreshold,
          },
          style: chartApi.style({
            fill: 'rgba(51, 102, 204, 0.1)',
          }),
        },
      ];

      const previousIndex = this.getPreviousPassedIndex(index);
      if (previousIndex >= 0) {
        const prevDataItem = this.chartData[previousIndex];
        const prevXCenter = chartApi.coord([previousIndex, 0])[0];
        const prevXRight = prevXCenter + (barWidth / 2);
        const prevThreshold = this.getUpperThreshold(prevDataItem);
        const prevYThreshold = chartApi.coord([0, prevThreshold])[1];

        children.push({
          type: 'polygon',
          shape: {
            points: [
              [prevXRight, prevYThreshold],
              [xLeft, yThreshold],
              [xLeft, yZero],
              [prevXRight, yZero],
            ],
          },
          style: chartApi.style({
            fill: 'rgba(51, 102, 204, 0.1)',
          }),
        });
      }

      return {
        type: 'group',
        children,
      };
    },

    renderThresholdLine(renderParams, chartApi) {
      const index = renderParams.dataIndex;
      const dataItem = this.chartData[index];
      if (dataItem.status !== 'PASSED') {
        return;
      }

      const xCenter = chartApi.coord([index, 0])[0];
      const fullWidth = chartApi.size([1, 0])[0];
      const barWidth = fullWidth * 0.7;
      const xLeft = xCenter - (barWidth / 2);
      const xRight = xCenter + (barWidth / 2);
      const threshold = chartApi.value(1);
      const yThreshold = chartApi.coord([0, threshold])[1];

      const children = [
        {
          type: 'line',
          shape: {
            x1: xLeft,
            y1: yThreshold,
            x2: xRight,
            y2: yThreshold,
          },
          style: chartApi.style({
            stroke: '#3366cc',
            lineWidth: 2,
          }),
        },
      ];

      const previousIndex = this.getPreviousPassedIndex(index);
      if (previousIndex >= 0) {
        const prevDataItem = this.chartData[previousIndex];
        const prevXCenter = chartApi.coord([previousIndex, 0])[0];
        const prevXRight = prevXCenter + (barWidth / 2);
        const prevThreshold = this.getUpperThreshold(prevDataItem);
        const prevYThreshold = chartApi.coord([0, prevThreshold])[1];

        children.push({
          type: 'line',
          shape: {
            x1: prevXRight,
            y1: prevYThreshold,
            x2: xLeft,
            y2: yThreshold,
          },
          style: chartApi.style({
            stroke: '#3366cc',
            lineWidth: 2,
          }),
        });
      }

      return {
        type: 'group',
        children,
      };
    },
  },
};
</script>
