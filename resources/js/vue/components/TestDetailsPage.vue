<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="tests"
  >
    <BuildSummaryCard :build-id="buildId" />

    <LoadingIndicator :is-loading="!build || !test">
      <div class="tw-mt-4 tw-border tw-border-base-300 tw-rounded-lg tw-bg-white tw-p-4 tw-flex tw-flex-row tw-items-start tw-gap-4">
        <div class="tw-flex tw-flex-col tw-gap-2 tw-flex-grow">
          <div>
            <span class="tw-inline-flex tw-flex-row tw-items-center tw-rounded tw-border tw-overflow-hidden tw-text-sm">
              <span
                class="tw-flex tw-flex-row tw-items-center tw-gap-1 tw-px-2 tw-py-0.5 tw-font-medium"
                :class="testStatusPillClass"
                data-test="test-status"
              >
                <FontAwesomeIcon :icon="testStatusIcon" />
                {{ testStatus }}
              </span>
              <span
                v-if="test.details"
                class="tw-bg-white tw-px-2 tw-py-0.5"
                data-test="test-details"
              >
                {{ test.details }}
              </span>
            </span>
          </div>
          <div>
            <a
              id="summary_link"
              class="tw-link tw-link-hover tw-text-lg tw-font-medium"
              :href="testHistoryUrl"
              data-test="test-name-link"
            >
              {{ test.name }}
            </a>
          </div>
          <div
            v-if="test.labels.edges.length > 0"
            class="tw-flex tw-flex-row tw-flex-wrap tw-items-center tw-gap-2"
          >
            <FontAwesomeIcon
              :icon="FA.faTags"
              class="tw-text-neutral-500"
            />
            <span
              v-for="{ node: label } in test.labels.edges"
              class="tw-badge tw-badge-outline tw-text-xs tw-text-neutral-500"
            >
              {{ label.text }}
            </span>
          </div>
          <div
            v-if="numericMeasurements.length > 0"
            class="tw-flex tw-flex-row tw-flex-wrap tw-items-start tw-gap-2"
          >
            <div
              v-for="measurement in numericMeasurements"
              class="tw-flex tw-flex-col tw-items-center tw-text-center tw-border tw-border-base-300 tw-rounded-lg tw-bg-gray-50 tw-p-2"
              data-test="numeric-measurement"
            >
              <span class="tw-text-xs tw-text-neutral-500">
                {{ measurement.name }}
              </span>
              <span class="tw-font-medium">
                {{ measurement.value }}
              </span>
            </div>
          </div>
        </div>
        <div
          id="executiontime"
          class="tw-flex tw-flex-row tw-items-center tw-gap-2 tw-border tw-border-base-300 tw-rounded-lg tw-bg-gray-50 tw-p-2"
          data-test="execution-time"
        >
          <span
            class="tw-flex tw-items-center tw-justify-center tw-rounded-full tw-w-8 tw-h-8"
            :class="testTimeStatusCircleClass"
            :title="'Average: ' + test.meanRunningTime + ', SD: ' + test.stdDevRunningTime"
          >
            <FontAwesomeIcon :icon="FA.faClock" />
          </span>
          <span class="builddateelapsed">
            {{ runningTime }}
          </span>
        </div>
      </div>
      <div class="tw-join tw-join-vertical tw-w-full tw-mt-4">
        <details
          v-if="hasEnvironment"
          class="tw-collapse tw-collapse-plus tw-join-item tw-border tw-border-base-300"
          data-test="environment-collapse"
        >
          <summary class="tw-collapse-title tw-text-lg tw-font-medium">
            <FontAwesomeIcon
              :icon="FA.faListUl"
              class="tw-mr-1"
            />
            Environment
          </summary>
          <div class="tw-collapse-content">
            <CodeBox
              id="environment"
              :text="environment"
            />
          </div>
        </details>
        <details
          class="tw-collapse tw-collapse-plus tw-join-item tw-border tw-border-base-300"
          data-test="command-line-collapse"
        >
          <summary class="tw-collapse-title tw-text-lg tw-font-medium">
            <FontAwesomeIcon
              :icon="FA.faTerminal"
              class="tw-mr-1"
            />
            Command Line
          </summary>
          <div class="tw-collapse-content">
            <CodeBox
              id="commandline"
              :text="test.command"
            />
          </div>
        </details>
        <details
          v-if="allMeasurements.length > 0"
          class="tw-collapse tw-collapse-plus tw-join-item tw-border tw-border-base-300"
          data-test="measurements-collapse"
        >
          <summary class="tw-collapse-title tw-text-lg tw-font-medium">
            <FontAwesomeIcon
              :icon="FA.faTableList"
              class="tw-mr-1"
            />
            Measurements
          </summary>
          <div class="tw-collapse-content tw-overflow-x-auto">
            <table class="tw-table tw-w-full">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="measurement in allMeasurements"
                  :key="measurement.id"
                  data-test="measurement-row"
                >
                  <td
                    class="tw-font-bold"
                    data-test="measurement-name"
                  >
                    {{ measurement.name }}
                  </td>
                  <td data-test="measurement-value">
                    <template v-if="measurement.type.startsWith('numeric/')">
                      {{ measurement.value }}
                    </template>
                    <template v-else-if="measurement.type === 'file'">
                      <a
                        class="cdash-link"
                        :href="$baseURL + '/api/v1/testDetails.php?buildtestid=' + testId + '&fileid=' + (getFileIndex(measurement) + 1)"
                        data-test="measurement-file-link"
                      >
                        <img :src="$baseURL + '/img/package.png'">
                      </a>
                    </template>
                    <template v-else-if="measurement.type === 'text/link'">
                      <a
                        class="cdash-link"
                        :href="measurement.value"
                        data-test="measurement-link"
                      >{{ measurement.name }}</a>
                    </template>
                    <template v-else-if="measurement.type === 'text/preformatted'">
                      <CodeBox :text="measurement.value" />
                    </template>
                    <template v-else>
                      {{ measurement.value }}
                    </template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </details>
        <details
          class="tw-collapse tw-collapse-plus tw-join-item tw-border tw-border-base-300"
          data-test="trend-collapse"
        >
          <summary class="tw-collapse-title tw-text-lg tw-font-medium">
            <FontAwesomeIcon
              :icon="FA.faChartLine"
              class="tw-mr-1"
            />
            Trend
          </summary>
          <div class="tw-collapse-content">
            <div
              v-if="build && test"
              class="tw-p-1"
            >
              <TestTrendChart
                :test-name="test.name"
                :project-id="Number(build.project.id)"
                :site-id="Number(build.site.id)"
                :build-name="build.name"
                :build-type="build.buildType"
                :build-start-time="build.startTime"
                :test-history-url="testHistoryUrl"
                :numeric-measurements="numericMeasurements"
                :enable-test-timing="build.project.enableTestTiming"
                :test-time-std-multiplier="build.project.testTimeStdMultiplier"
                :test-time-std-threshold="build.project.testTimeStdThreshold"
              />
            </div>
          </div>
        </details>
      </div>

      <!-- Images section -->
      <div
        v-if="hasImages"
        class="tw-mt-4 tw-border tw-border-base-300 tw-rounded-lg tw-overflow-hidden"
        data-test="images-card"
      >
        <div class="tw-flex tw-items-center tw-justify-between tw-px-3 tw-py-1 tw-bg-base-200 tw-border-b tw-border-base-300">
          <span class="tw-text-base tw-font-bold">
            <FontAwesomeIcon
              :icon="FA.faImages"
              class="tw-mr-1"
            />
            Images
          </span>
        </div>
        <div class="tw-p-4">
          <div
            v-if="compareImages.length > 0"
            data-test="interactive-image"
          >
            <div class="tw-font-bold tw-mb-2">
              Interactive Image
            </div>
            <div class="je_compare">
              <img
                v-for="(image, index) in compareImages"
                :key="image.id"
                :src="image.url"
                :alt="image.role"
                @load="index === 0 && initializeJeCompare()"
              >
            </div>
          </div>

          <div
            v-for="{ node: image } in test.testImages.edges"
            :key="image.id"
            class="tw-mt-4"
          >
            <div class="tw-font-bold tw-mb-2">
              {{ image.role }}
            </div>
            <img
              :src="image.url"
              :alt="image.role"
              class="tw-max-w-full"
            >
          </div>
        </div>
      </div>


      <!-- Output section -->
      <div
        class="tw-mt-4 tw-border tw-border-base-300 tw-rounded-lg tw-overflow-hidden"
        data-test="output-card"
      >
        <div class="tw-flex tw-items-center tw-justify-between tw-px-3 tw-py-1 tw-bg-base-200 tw-border-b tw-border-base-300">
          <span class="tw-text-base tw-font-bold">
            <FontAwesomeIcon
              :icon="FA.faFileLines"
              class="tw-mr-1"
            />
            Output
          </span>
        </div>
        <div>
          <CodeBox
            v-if="hasOutput"
            id="test_output"
            :text="test.output"
            :bordered="false"
          />
          <div
            v-else
            class="tw-p-4 tw-text-neutral-500 tw-italic"
            data-test="no-output-message"
          >
            No output for this test.
          </div>
        </div>
      </div>
    </LoadingIndicator>
  </BuildSidebar>
</template>

<script>
import $ from 'jquery';
import TestTrendChart from './TestTrendChart.vue';
import CodeBox from './shared/CodeBox.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import gql from 'graphql-tag';
import Utils from './shared/Utils';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {
  faCircleCheck,
  faCircleXmark,
  faCircleExclamation,
  faCircleQuestion,
  faClock,
  faTags,
  faFileLines,
  faListUl,
  faTerminal,
  faTableList,
  faImages,
  faChartLine,
  faLink,
} from '@fortawesome/free-solid-svg-icons';

export default {
  name: 'TestDetails',

  components: {
    BuildSidebar,
    LoadingIndicator,
    BuildSummaryCard,
    CodeBox,
    TestTrendChart,
    FontAwesomeIcon,
  },

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    testId: {
      type: Number,
      required: true,
    },

    testingDay: {
      type: String,
      required: true,
    },
  },

  apollo: {
    build: {
      query: gql`
        query($id: ID) {
          build(id: $id) {
            id
            name
            buildType
            startTime
            site {
              id
            }
            project {
              id
              name
              enableTestTiming
              testTimeStdMultiplier
              testTimeStdThreshold
            }
          }
        }
      `,
      variables() {
        return {
          id: this.buildId,
        };
      },
    },

    test: {
      query: gql`
        query($id: ID) {
          test(id: $id) {
            id
            name
            status
            details
            command
            output
            runningTime
            meanRunningTime
            stdDevRunningTime
            timeStatusCategory
            testMeasurements {
              id
              name
              type
              value
            }
            labels {
              edges {
                node {
                  id
                  text
                }
              }
            }
            testImages {
              edges {
                node {
                  id
                  role
                  url
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          id: this.testId,
        };
      },
    },
  },

  data () {
    return {
      jeCompareInitialized: false,
    };
  },

  computed: {
    FA() {
      return {
        faClock,
        faTags,
        faFileLines,
        faListUl,
        faTerminal,
        faTableList,
        faImages,
        faChartLine,
        faLink,
      };
    },

    files() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type === 'file';
      });
    },

    numericMeasurements() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type.startsWith('numeric/');
      });
    },

    environment() {
      return this.test.testMeasurements.find((measurement) => {
        return measurement.type === 'text/string' && measurement.name === 'Environment';
      })?.value ?? null;
    },

    hasOutput() {
      return typeof this.test.output === 'string' && this.test.output.trim() !== '';
    },

    hasEnvironment() {
      return typeof this.environment === 'string' && this.environment.trim() !== '';
    },

    hasImages() {
      return !!this.test?.testImages?.edges?.length;
    },

    runningTime() {
      return Utils.formatDuration(this.test.runningTime * 1000);
    },

    testHistoryUrl() {
      if (!this.build || !this.test) {
        return '';
      }
      return `${this.$baseURL}/queryTests.php?project=${encodeURIComponent(this.build.project.name)}&date=${this.testingDay}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=${encodeURIComponent(this.test.name)}`;
    },

    testStatus() {
      switch (this.test.status) {
        case 'PASSED':
          return 'Passed';
        case 'FAILED':
          return 'Failed';
        case 'NOT_RUN':
          return 'Not Run';
        default:
          return this.test.status;
      }
    },

    testStatusPillClass() {
      switch (this.test.status) {
        case 'PASSED':
          return 'tw-bg-success tw-text-success-content';
        case 'FAILED':
          return 'tw-bg-error tw-text-error-content';
        case 'NOT_RUN':
          return 'tw-bg-warning tw-text-warning-content';
        default:
          return 'tw-bg-neutral tw-text-neutral-content';
      }
    },

    testStatusIcon() {
      switch (this.test.status) {
        case 'PASSED':
          return faCircleCheck;
        case 'FAILED':
          return faCircleXmark;
        case 'NOT_RUN':
          return faCircleExclamation;
        default:
          return faCircleQuestion;
      }
    },

    testTimeStatusCircleClass() {
      // Only show color when the test passed and test timing is enabled;
      // otherwise (test failed or timing disabled) show gray.
      if (this.test.status !== 'PASSED' || !this.build.project.enableTestTiming) {
        return 'tw-bg-gray-200 tw-text-gray-500';
      }
      switch (this.test.timeStatusCategory) {
        case 'PASSED':
          return 'tw-bg-green-100 tw-text-green-600';
        case 'FAILED':
          return 'tw-bg-red-100 tw-text-red-600';
        default:
          return 'tw-bg-gray-200 tw-text-gray-500';
      }
    },

    compareImages() {
      if (!this.test || !this.test.testImages) {
        return [];
      }

      const validImages = [];
      const testImages = [];

      this.test.testImages.edges.forEach(({ node: image }) => {
        if (image.role === 'ValidImage') {
          validImages.push(image);
        } else if (image.role === 'TestImage') {
          testImages.push(image);
        }
      });

      if (validImages.length === 0 || testImages.length === 0) {
        return [];
      }

      return [...validImages, ...testImages];
    },

    allMeasurements() {
      if (!this.test || !this.test.testMeasurements) {
        return [];
      }
      const order = ['numeric/', 'file', 'text/link', 'text/string', 'text/preformatted'];
      const excludedNames = ['Environment', 'Command Line'];
      return [...this.test.testMeasurements]
        .filter((measurement) => !(measurement.type === 'text/string' && excludedNames.includes(measurement.name)))
        .sort((measurementA, measurementB) => {
          const getOrder = (type) => {
            const index = order.findIndex((orderItem) => type.startsWith(orderItem));
            return index === -1 ? 999 : index;
          };
          const orderA = getOrder(measurementA.type);
          const orderB = getOrder(measurementB.type);
          if (orderA !== orderB) {
            return orderA - orderB;
          }
          return measurementA.name.localeCompare(measurementB.name);
        });
    },
  },

  async mounted () {
    // Ensure jQuery is globally available before loading plugins
    window.jQuery = $;
    await import('../../angular/je_compare.js');
  },

  methods: {
    getFileIndex(measurement) {
      return this.files.findIndex((file) => file.id === measurement.id);
    },

    initializeJeCompare() {
      if (this.jeCompareInitialized) {
        return;
      }
      $('.je_compare').je_compare({caption: true});
      this.jeCompareInitialized = true;
    },
  },
};
</script>
