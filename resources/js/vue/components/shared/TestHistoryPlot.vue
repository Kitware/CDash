<template>
  <div class="chart-container">
    <div class="year-navigation">
      <button
        :disabled="!canGoToPreviousYear"
        aria-label="Previous year"
        @click="previousYear"
      >
        ‹
      </button>
      <span class="year-label">{{ currentYear }}</span>
      <button
        :disabled="!canGoToNextYear"
        aria-label="Next year"
        @click="nextYear"
      >
        ›
      </button>
    </div>
    <loading-indicator :is-loading="!testStatuses" />
    <div
      ref="chart"
      class="chart"
    />
  </div>
</template>

<script>
import {DateTime} from 'luxon';
import * as echarts from 'echarts/core';
import {CanvasRenderer} from 'echarts/renderers';
import {
  CalendarComponent,
  LegendComponent,
  TitleComponent,
  TooltipComponent,
  VisualMapComponent,
} from 'echarts/components';
import {CustomChart, ScatterChart} from 'echarts/charts';
import gql from 'graphql-tag';
import LoadingIndicator from './LoadingIndicator.vue';

const decalPattern = {
  symbol: 'rect',
  symbolSize: 0.5,
  symbolKeepAspect: false,
  color: 'rgba(255, 255, 255, 0.5)',
  backgroundColor: 'transparent',
  dashArrayX: [1, 0],
  dashArrayY: [4, 1.5],
  rotation: Math.PI / 4,
};

echarts.use([
  CanvasRenderer,
  CalendarComponent,
  TitleComponent,
  TooltipComponent,
  VisualMapComponent,
  LegendComponent,
  CustomChart,
  ScatterChart,
]);

export default {
  name: 'TestHistoryPlot',
  components: {LoadingIndicator},

  props: {
    baseUrl: {
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
    testName: {
      type: String,
      required: true,
    },
    buildName: {
      type: String,
      required: true,
    },
  },

  data() {
    return {
      currentYear: DateTime.now().year,
      chart: null,
    };
  },

  apollo: {
    testStatuses: {
      query: gql`
        query($projectid: ID, $testname: String!, $buildname: String!) {
          testStatuses: project(id: $projectid) {
            id
            name
            buildsWhereTestPassed: builds(filters: {
              any: [
                {
                  all: [
                    {
                      has: {
                        tests: {
                          all: [
                            {
                              eq: {
                                status: PASSED
                              }
                            },
                            {
                              eq: {
                                name: $testname
                              }
                            },
                          ]
                        }
                      }
                    },
                    {
                      eq: {
                        name: $buildname
                      }
                    }
                  ]
                }
                {
                  has: {
                    children: {
                      all: [
                        {
                          has: {
                            tests: {
                              all: [
                                {
                                  eq: {
                                    status: PASSED
                                  }
                                },
                                {
                                  eq: {
                                    name: $testname
                                  }
                                },
                              ]
                            }
                          }
                        },
                        {
                          eq: {
                            name: $buildname
                          }
                        }
                      ]
                    }
                  }
                }
              ]
            }, first: 1000000) {
              edges {
                node {
                  id
                  startTime
                }
              }
            }
            buildsWhereTestFailed: builds(filters: {
              any: [
                {
                  has: {
                    tests: {
                      all: [
                        {
                          eq: {
                            status: FAILED
                          }
                        },
                        {
                          eq: {
                            name: $testname
                          }
                        },
                      ]
                    }
                  }
                }
                {
                  has: {
                    children: {
                      all: [
                        {
                          has: {
                            tests: {
                              all: [
                                {
                                  eq: {
                                    status: FAILED
                                  }
                                },
                                {
                                  eq: {
                                    name: $testname
                                  }
                                },
                              ]
                            }
                          }
                        }
                      ]
                    }
                  }
                }
              ]
            }, first: 1000000) {
              edges {
                node {
                  id
                  startTime
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          projectid: this.projectId,
          testname: this.testName,
          buildname: this.buildName,
        };
      },
    },
  },

  computed: {
    history() {
      if (!this.testStatuses) {
        return [];
      }

      const passed = this.testStatuses.buildsWhereTestPassed.edges.map(edge => ({
        date: edge.node.startTime,
        status: 'passed',
      }));

      const failed = this.testStatuses.buildsWhereTestFailed.edges.map(edge => ({
        date: edge.node.startTime,
        status: 'failed',
      }));

      return [...passed, ...failed];
    },

    yearsWithData() {
      if (!this.history || this.history.length === 0) {
        return [];
      }
      const years = new Set(this.history.map(item => parseInt(item.date.substring(0, 4), 10)));
      return Array.from(years).sort();
    },

    canGoToPreviousYear() {
      const currentIndex = this.yearsWithData.indexOf(this.currentYear);
      return currentIndex > 0;
    },

    canGoToNextYear() {
      const currentIndex = this.yearsWithData.indexOf(this.currentYear);
      return currentIndex < this.yearsWithData.length - 1;
    },

    chartData() {
      if (!this.currentYear) {
        return [];
      }

      const dailyBins = this.history
        .filter(item => parseInt(item.date.substring(0, 4), 10) === this.currentYear)
        .reduce((acc, item) => {
          const dateStr = item.date.substring(0, 10);
          if (!acc[dateStr]) {
            acc[dateStr] = { numpassing: 0, numfailing: 0 };
          }
          if (item.status === 'passed') {
            acc[dateStr].numpassing++;
          }
          else if (item.status === 'failed') {
            acc[dateStr].numfailing++;
          }
          return acc;
        }, {});

      const yearData = [];
      let currentDate = DateTime.fromObject({ year: this.currentYear });
      while (currentDate.year === this.currentYear) {
        const dateStr = currentDate.toISODate();
        const bin = dailyBins[dateStr];

        yearData.push([
          dateStr,
          bin ? bin.numpassing : 0,
          bin ? bin.numfailing : 0,
        ]);
        currentDate = currentDate.plus({ days: 1 });
      }
      return yearData;
    },

    chartRange() {
      return this.currentYear.toString();
    },

    chartOptions() {
      return {
        tooltip: {
          formatter: function (p) {
            if (p.seriesType === 'custom') {
              const num_passing = p.data[1];
              const num_failing = p.data[2];

              if (num_passing === 0 && num_failing === 0) {
                return `Date: ${p.data[0]}<br/>No tests`;
              }

              let status_text = '';
              if (num_passing > 0) {
                status_text += `${num_passing} passing`;
              }
              if (num_failing > 0) {
                if (status_text.length > 0) {
                  status_text += ', ';
                }
                status_text += `${num_failing} failing`;
              }
              const date = p.data[0];
              return `Date: ${date}<br/>${status_text}`;
            }
            return null;
          },
        },
        calendar: {
          top: 30,
          left: 30,
          right: 30,
          height: 'auto',
          cellSize: 13,
          range: this.chartRange,
          itemStyle: {
            borderWidth: 0.5,
            borderColor: '#fff',
          },
          yearLabel: { show: false },
          dayLabel: {
            firstDay: 1,
            nameMap: 'en',
          },
          monthLabel: { nameMap: 'en' },
          splitLine: {
            show: true,
            lineStyle: {
              color: '#ddd',
              width: 1,
              type: 'solid',
            },
          },
        },
        series: [
          {
            type: 'custom',
            coordinateSystem: 'calendar',
            data: this.chartData,
            renderItem: function (params, api) {
              const cellPoint = api.coord(api.value(0));
              const cellWidth = params.coordSys.cellWidth;
              const cellHeight = params.coordSys.cellHeight;
              const num_passing = api.value(1);
              const num_failing = api.value(2);

              if (isNaN(cellPoint[0]) || isNaN(cellPoint[1])) {
                return;
              }

              let value = 0;
              if (num_passing > 0 && num_failing === 0) {
                value = 1; // Passing
              }
              else if (num_passing === 0 && num_failing > 0) {
                value = 2; // Failing
              }
              else if (num_passing > 0 && num_failing > 0) {
                value = 3; // Both
              }

              const gap = 2;
              const shapeWidth = cellWidth - gap;
              const shapeHeight = cellHeight - gap;
              const x = cellPoint[0] - cellWidth / 2 + gap / 2;
              const y = cellPoint[1] - cellHeight / 2 + gap / 2;

              if (value === 1) { // Passing
                return {
                  type: 'rect',
                  shape: { x, y, width: shapeWidth, height: shapeHeight, r: 3 },
                  style: { fill: '#52c41a' },
                };
              }
              else if (value === 2) { // Failing
                return {
                  type: 'rect',
                  shape: { x, y, width: shapeWidth, height: shapeHeight, r: 3 },
                  style: { fill: '#f5222d', decal: decalPattern },
                };
              }
              else if (value === 3) { // Both
                return {
                  type: 'group',
                  clipPath: { type: 'rect', shape: { x, y, width: shapeWidth, height: shapeHeight, r: 3 } },
                  children: [
                    { type: 'polygon', shape: { points: [[x, y], [x + shapeWidth, y], [x, y + shapeHeight]] }, style: { fill: '#52c41a' } },
                    { type: 'polygon', shape: { points: [[x + shapeWidth, y], [x + shapeWidth, y + shapeHeight], [x, y + shapeHeight]] }, style: { fill: '#f5222d', decal: decalPattern } },
                  ],
                };
              }
              return {
                type: 'rect',
                shape: { x, y, width: shapeWidth, height: shapeHeight, r: 3 },
                style: { fill: '#fff', stroke: '#eee', lineWidth: 1 },
              };
            },
            legendHoverLink: false,
          },
        ],
      };
    },
  },

  watch: {
    currentYear() {
      if (this.chart) {
        this.chart.setOption(this.chartOptions);
      }
    },
    testStatuses() {
      if (this.chart) {
        this.chart.setOption(this.chartOptions);
      }
    },
    yearsWithData(newYears) {
      if (newYears.length > 0) {
        this.currentYear = newYears[newYears.length - 1];
      }
    },
  },

  mounted() {
    this.chart = echarts.init(this.$refs.chart);
    this.chart.on('click', this.handleChartClick);
    this.chart.getZr().on('mousemove', params => {
      const pixel = [params.offsetX, params.offsetY];
      const seriesIndex = this.chart.convertFromPixel('grid', pixel);
      if (seriesIndex) {
        this.chart.getZr().setCursorStyle('pointer');
      }
    });
    this.chart.setOption(this.chartOptions);
  },

  beforeUnmount() {
    if (this.chart) {
      this.chart.dispose();
    }
  },

  methods: {
    previousYear() {
      const currentIndex = this.yearsWithData.indexOf(this.currentYear);
      if (currentIndex > 0) {
        this.currentYear = this.yearsWithData[currentIndex - 1];
      }
    },

    nextYear() {
      const currentIndex = this.yearsWithData.indexOf(this.currentYear);
      if (currentIndex < this.yearsWithData.length - 1) {
        this.currentYear = this.yearsWithData[currentIndex + 1];
      }
    },

    handleChartClick(params) {
      if (params.seriesType === 'custom' && params.data) {
        const date = params.data[0];
        window.location.href = `${this.baseUrl}/queryTests.php?project=${this.projectName}&date=${date}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=${this.testName}`;
      }
    },
  },
};
</script>

<style scoped>
.chart-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 940px;
  background-color: #f0f2f5;
  padding: 10px 0;
  box-sizing: border-box;
}
.chart {
  width: 100%;
  height: 144px;
}
.year-navigation {
  display: flex;
  align-items: center;
}
.year-label {
  font-weight: bold;
  margin: 0 12px;
  font-size: 1.1em;
  color: #262626;
}
.year-navigation button {
  background-color: #f0f2f5;
  border: 1px solid #d9d9d9;
  color: #595959;
  cursor: pointer;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  line-height: 1;
  transition: all 0.3s;
  box-shadow: 0 2px 0 rgba(0, 0, 0, 0.015);
}
.year-navigation button:hover {
  border-color: #40a9ff;
  color: #40a9ff;
}
.year-navigation button:disabled {
  border-color: #d9d9d9;
  color: rgba(0, 0, 0, 0.25);
  cursor: not-allowed;
  background-color: #f5f5f5;
}
</style>
