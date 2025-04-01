<template>
  <loading-indicator :is-loading="!build">
    <details class="tw-collapse tw-collapse-arrow tw-border-base-300 tw-bg-base-200 tw-border tw-group/collapse">
      <summary class="tw-collapse-title">
        <div class="tw-text-lg tw-font-medium tw-truncate tw-text-nowrap group-open/collapse:tw-text-wrap">
          <a
            :href="`${$baseURL}/builds/${build.id}`"
            class="tw-link tw-link-hover"
          >{{ build.name }}</a>
          <div class="tw-badge tw-badge-outline tw-ml-2 tw-text-neutral-500">
            {{ build.buildType }}
          </div>
        </div>
        <div class="tw-text-small tw-font-medium tw-text-neutral-500 tw-flex tw-flex-row tw-gap-2 tw-flex-wrap sm:tw-flex-nowrap tw-text-nowrap">
          <a
            :href="`${$baseURL}/sites/${build.site.id}`"
            class="tw-truncate"
          >
            <font-awesome-icon icon="fa-computer" /> {{ build.site.name }}
          </a>
          &bull;
          <span
            v-if="build.operatingSystemName"
            class="tw-truncate"
          >
            <font-awesome-icon
              v-if="build.operatingSystemName === 'Windows'"
              :icon="['fab', 'windows']"
            />
            <!-- TODO: Add more specific Linux types. May require CTest work. -->
            <font-awesome-icon
              v-else-if="build.operatingSystemName === 'Linux'"
              :icon="['fab', 'linux']"
            />
            <font-awesome-icon
              v-else-if="build.operatingSystemName === 'Darwin' || build.operatingSystemName === 'OSX'"
              :icon="['fab', 'apple']"
            />
            {{ build.operatingSystemName }} {{ build.operatingSystemRelease }}
            <div
              v-if="build.operatingSystemPlatform"
              class="tw-badge tw-badge-outline tw-text-xs tw-truncate"
            >
              {{ build.operatingSystemPlatform }}
            </div>
          </span>
          &bull;
          <span class="tw-truncate">
            {{ build.generator }}
          </span>
          <template v-if="build.compilerName">
            &bull;
            <span class="tw-truncate">
              {{ build.compilerName }} {{ build.compilerVersion }}
            </span>
          </template>
          <span class="tw-flex-grow tw-text-right tw-space-x-1">
            <span :title="fullHumanReadableDateTimeString(build.startTime)">{{ humanReadableBuildStartTime }}</span>
            <span v-if="humanReadableTotalDuration">({{ humanReadableTotalDuration }})</span>
          </span>
        </div>
      </summary>
      <div class="tw-collapse-content tw-flex tw-flex-col">
        <div class="tw-flex tw-flex-col tw-gap-4 tw-flex-nowrap tw-text-nowrap tw-text-center">
          <div class="tw-flex tw-flex-row tw-gap-4">
            <div class="tw-flex tw-flex-col">
              <div class="tw-bg-white tw-divide-y tw-rounded tw-shadow tw-text-left">
                <build-summary-card-step-summary
                  upper-left-text="Start"
                  :upper-right-text="humanReadableOverallStartTime"
                />
                <build-summary-card-step-summary
                  upper-left-text="Configure"
                  :upper-right-text="hasConfigure ? humanReadableConfigureDuration : null"
                  :main-text="configureText"
                  :link="hasConfigure ? `${$baseURL}/builds/${build.id}/configure` : null"
                  :highlight-color="configureHighlightColor"
                />
                <build-summary-card-step-summary
                  upper-left-text="Build"
                  :upper-right-text="hasBuild ? humanReadableBuildDuration : null"
                  :main-text="buildText"
                  :link="hasBuild ? `${$baseURL}/builds/${build.id}` : null"
                  :highlight-color="buildHighlightColor"
                />
                <build-summary-card-step-summary
                  upper-left-text="Test"
                  :upper-right-text="hasTest ? humanReadableTestDuration : null"
                  :main-text="testText"
                  :link="hasTest ? `${$baseURL}/builds/${build.id}/tests` : null"
                  :highlight-color="testHighlightColor"
                />
                <build-summary-card-step-summary
                  upper-left-text="End"
                  :upper-right-text="humanReadableOverallEndTime"
                />
                <build-summary-card-step-summary
                  upper-left-text="Submitted"
                  :upper-right-text="humanReadableSubmissionTime"
                />
              </div>
              <div class="tw-flex-grow" />
            </div>
            <div
              v-if="build.labels.edges.length > 0"
              class="tw-flex tw-flex-col"
            >
              <div class="tw-text-lg tw-text-left">
                Labels
              </div>
              <div class="tw-flex tw-flex-row tw-flex-wrap tw-gap-2">
                <span
                  v-for="label in build.labels.edges"
                  class="tw-badge tw-badge-outline tw-text-xs tw-text-neutral-500"
                >
                  {{ label.node.text }}
                </span>
              </div>
              <div class="tw-flex-grow" />
            </div>
          </div>
        </div>
        <div class="tw-divider tw-divider-vertical" />
        <div class="tw-flex tw-flex-col tw-gap-4">
          <div class="tw-text-lg tw-font-medium">
            <a
              :href="`${$baseURL}/sites/${build.site.id}`"
              class="tw-link tw-link-hover"
            >
              <font-awesome-icon icon="fa-computer" /> {{ build.site.name }}
            </a>
          </div>
          <div class="tw-flex tw-flex-row tw-gap-4">
            <table class="tw-table tw-w-auto tw-table-zebra tw-table-xs tw-text-left tw-bg-white tw-shadow tw-rounded">
              <thead>
                <tr>
                  <th>Site Information</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr>
                  <th>Processor Vendor</th>
                  <td v-if="build.site.mostRecentInformation.processorVendor">
                    {{ build.site.mostRecentInformation.processorVendor }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Processor Vendor ID</th>
                  <td v-if="build.site.mostRecentInformation.processorVendorId">
                    {{ build.site.mostRecentInformation.processorVendorId }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Processor Family ID</th>
                  <td v-if="build.site.mostRecentInformation.processorFamilyId">
                    {{ build.site.mostRecentInformation.processorFamilyId }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Processor Model ID</th>
                  <td v-if="build.site.mostRecentInformation.processorModelId">
                    {{ build.site.mostRecentInformation.processorModelId }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Processor Model Name</th>
                  <td v-if="build.site.mostRecentInformation.processorModelName">
                    {{ build.site.mostRecentInformation.processorModelName }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Cache Size</th>
                  <td v-if="build.site.mostRecentInformation.processorCacheSize">
                    {{ build.site.mostRecentInformation.processorCacheSize }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Logical CPUs</th>
                  <td v-if="build.site.mostRecentInformation.numberLogicalCpus">
                    {{ build.site.mostRecentInformation.numberLogicalCpus }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Physical CPUs</th>
                  <td v-if="build.site.mostRecentInformation.numberPhysicalCpus">
                    {{ build.site.mostRecentInformation.numberPhysicalCpus }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Virtual Memory</th>
                  <td v-if="build.site.mostRecentInformation.totalVirtualMemory">
                    {{ humanReadableMemory(build.site.mostRecentInformation.totalVirtualMemory) }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Physical Memory</th>
                  <td v-if="build.site.mostRecentInformation.totalPhysicalMemory">
                    {{ humanReadableMemory(build.site.mostRecentInformation.totalPhysicalMemory) }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
                <tr>
                  <th>Clock Frequency</th>
                  <td v-if="build.site.mostRecentInformation.processorClockFrequency">
                    {{ humanReadableSiteClockFrequency }}
                  </td>
                  <td
                    v-else
                    class="tw-italic"
                  >
                    Unknown
                  </td>
                </tr>
              </tbody>
            </table>
            <div>
              <div class="tw-text-lg">
                Description
              </div>
              <div v-if="build.site.mostRecentInformation.description">
                {{ build.site.mostRecentInformation.description }}
              </div>
              <div
                v-else
                class="tw-italic"
              >
                No description provided.
              </div>
            </div>
          </div>
        </div>
      </div>
    </details>
  </loading-indicator>
</template>

<script>
import gql from 'graphql-tag';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import LoadingIndicator from './LoadingIndicator.vue';
import { DateTime, Interval, Duration } from 'luxon';
import BuildSummaryCardStepSummary from './BuildSummaryCardStepSummary.vue';

export default {
  components: {BuildSummaryCardStepSummary, LoadingIndicator, FontAwesomeIcon},

  props: {
    buildId: {
      type: Number,
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
            startTime
            endTime
            submissionTime
            stamp
            buildType
            generator
            operatingSystemName
            operatingSystemPlatform
            operatingSystemRelease
            operatingSystemVersion
            compilerName
            compilerVersion
            configureDuration
            buildDuration
            testDuration
            configureWarningsCount
            configureErrorsCount
            buildWarningsCount
            buildErrorsCount
            passedTestsCount
            failedTestsCount
            notRunTestsCount
            site {
              id
              name
              mostRecentInformation {
                processorVendor
                processorVendorId
                processorFamilyId
                processorModelId
                processorCacheSize
                numberLogicalCpus
                numberPhysicalCpus
                totalVirtualMemory
                totalPhysicalMemory
                processorClockFrequency
                description
              }
            }
            project {
              id
            }
            labels(first: 100) { # We assume that projects won't have more than 100 labels.  Displaying more would be a challenge...
              edges {
                node {
                  id
                  text
                }
              }
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
  },

  computed: {
    hasConfigure() {
      return this.build.configureWarningsCount > -1 && this.build.configureErrorsCount > -1;
    },

    hasBuild() {
      return this.build.buildWarningsCount > -1 && this.build.buildErrorsCount > -1;
    },

    hasTest() {
      return this.build.notRunTestsCount > -1 && this.build.failedTestsCount > -1 && this.build.passedTestsCount > -1;
    },

    configureText() {
      if (!this.hasConfigure) {
        return 'No Submission';
      }

      if (this.build.configureWarningsCount === 0 && this.build.configureErrorsCount === 0) {
        return 'Success';
      }

      let retval = '';
      if (this.build.configureWarningsCount > 0) {
        retval += `${this.build.configureWarningsCount} Warning${this.pluralize(this.build.configureWarningsCount > 0)}${this.commaSeparator(this.build.configureErrorsCount > 0)}`;
      }
      if (this.build.configureErrorsCount > 0) {
        retval += `${this.build.configureErrorsCount} Error${this.pluralize(this.build.configureErrorsCount > 0)}`;
      }

      return retval;
    },

    buildText() {
      if (!this.hasBuild) {
        return 'No Submission';
      }

      if (this.build.buildWarningsCount === 0 && this.build.buildErrorsCount === 0) {
        return 'Success';
      }

      let retval = '';
      if (this.build.buildWarningsCount > 0) {
        retval += `${this.build.buildWarningsCount} Warning${this.pluralize(this.build.buildWarningsCount > 0)}${this.commaSeparator(this.build.buildErrorsCount > 0)}`;
      }
      if (this.build.buildErrorsCount > 0) {
        retval += `${this.build.buildErrorsCount} Error${this.pluralize(this.build.buildErrorsCount > 0)}`;
      }

      return retval;
    },

    testText() {
      if (!this.hasTest) {
        return 'No Submission';
      }

      let retval = '';
      if (this.build.notRunTestsCount > 0) {
        retval += `${this.build.notRunTestsCount} Not Run${this.commaSeparator(this.build.failedTestsCount > 0 || this.build.passedTestsCount)}`;
      }
      if (this.build.failedTestsCount > 0) {
        retval += `${this.build.failedTestsCount} Failed${this.commaSeparator(this.build.passedTestsCount)}`;
      }
      if (this.build.passedTestsCount > 0) {
        retval += `${this.build.passedTestsCount} Passed`;
      }

      return retval;
    },

    humanReadableOverallStartTime() {
      return DateTime.fromISO(this.build.startTime).toLocaleString(DateTime.DATETIME_MED_WITH_SECONDS);
    },

    humanReadableOverallEndTime() {
      return DateTime.fromISO(this.build.endTime).toLocaleString(DateTime.DATETIME_MED_WITH_SECONDS);
    },

    humanReadableSubmissionTime() {
      return DateTime.fromISO(this.build.submissionTime).toLocaleString(DateTime.DATETIME_MED_WITH_SECONDS);
    },

    /**
     * If the build started sometime in the last month, display a relative timestamp.
     * Otherwise, display a shortened version of the full date string.
     */
    humanReadableBuildStartTime() {
      const startTime = DateTime.fromISO(this.build.startTime);
      if (startTime < DateTime.now().minus({months: 1})) {
        return startTime.toLocaleString(DateTime.DATE_MED);
      }
      else {
        return startTime.toRelative();
      }
    },

    humanReadableConfigureDuration() {
      return Duration.fromObject({ seconds: this.build.configureDuration })
        .rescale()
        .toHuman({ unitDisplay: 'short' });
    },

    humanReadableBuildDuration() {
      return Duration.fromObject({ seconds: this.build.buildDuration })
        .rescale()
        .toHuman({ unitDisplay: 'short' });
    },

    humanReadableTestDuration() {
      return Duration.fromObject({ seconds: this.build.testDuration })
        .rescale()
        .toHuman({ unitDisplay: 'short' });
    },

    humanReadableTotalDuration() {
      return Interval.fromDateTimes(
        DateTime.fromISO(this.build.startTime),
        DateTime.fromISO(this.build.endTime),
      ).toDuration().rescale().toHuman({ unitDisplay: 'short' });
    },

    configureColor() {
      if (this.build.configureErrorsCount > 0) {
        return 'tw-bg-red-400';
      }
      else if (this.build.configureWarningsCount > 0) {
        return 'tw-bg-orange-400';
      }
      else {
        return 'tw-bg-green-400';
      }
    },

    /**
     * Unfortunately there is no realistic way to avoid duplicating code here because Tailwind
     * needs to see the entire class name at build time.
     */
    configureHighlightColor() {
      if (!this.hasConfigure) {
        return 'tw-border-x-gray-400';
      }
      else if (this.build.configureErrorsCount > 0) {
        return 'tw-border-x-red-400';
      }
      else if (this.build.configureWarningsCount > 0) {
        return 'tw-border-x-orange-400';
      }
      else {
        return 'tw-border-x-green-400';
      }
    },

    buildColor() {
      if (this.build.buildErrorsCount > 0) {
        return 'tw-bg-red-400';
      }
      else if (this.build.buildWarningsCount > 0) {
        return 'tw-bg-orange-400';
      }
      else {
        return 'tw-bg-green-400';
      }
    },

    buildHighlightColor() {
      if (!this.hasBuild) {
        return 'tw-border-x-gray-400';
      }
      else if (this.build.buildErrorsCount > 0) {
        return 'tw-border-x-red-400';
      }
      else if (this.build.buildWarningsCount > 0) {
        return 'tw-border-x-orange-400';
      }
      else {
        return 'tw-border-x-green-400';
      }
    },

    testColor() {
      if (this.build.failedTestsCount > 0) {
        return 'tw-bg-red-400';
      }
      else {
        return 'tw-bg-green-400';
      }
    },

    testHighlightColor() {
      if (!this.hasTest) {
        return 'tw-border-x-gray-400';
      }
      else if (this.build.failedTestsCount > 0) {
        return 'tw-border-x-red-400';
      }
      else {
        return 'tw-border-x-green-400';
      }
    },

    /**
     * Returns the total duration of the configure+build+test process in seconds
     *
     * TODO: Re-evaluate whether this should be computed from the start and end times instead.
     */
    totalDuration() {
      return this.build.configureDuration + this.build.buildDuration + this.build.testDuration;
    },

    humanReadableSiteClockFrequency() {
      const clockFrequency = this.build.site.mostRecentInformation.processorClockFrequency;
      if (clockFrequency > 1000) {
        return `${clockFrequency / 1000} GHz`;
      }
      else {
        return `${clockFrequency} MHz`;
      }
    },
  },

  methods: {
    commaSeparator(condition) {
      return condition ? ', ' : '';
    },

    pluralize(condition) {
      return condition ? 's' : '';
    },

    humanReadableMemory(inputInMiB) {
      if (inputInMiB < 1024) {
        return `${inputInMiB} MiB`;
      }
      else if (inputInMiB < 1024 * 1024) {
        return `${(inputInMiB / 1024).toFixed(2)} GiB`;
      }
      else {
        return `${(inputInMiB / (1024 * 1024)).toFixed(2)} TiB`;
      }
    },

    fullHumanReadableDateTimeString(timestamp) {
      return DateTime.fromISO(timestamp).toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
    },
  },
};
</script>
