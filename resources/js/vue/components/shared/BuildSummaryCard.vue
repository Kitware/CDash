<template>
  <LoadingIndicator :is-loading="!build">
    <div
      class="tw-border-base-300 tw-bg-base-200 tw-border tw-rounded-md tw-p-4"
      data-test="build-summary-card"
    >
      <div class="tw-text-lg tw-font-medium tw-truncate tw-text-nowrap">
        <a
          :href="`${$baseURL}/builds/${build.id}`"
          class="tw-link tw-link-hover"
        >{{ build.name }}</a>
        <div class="tw-badge tw-badge-outline tw-ml-2 tw-text-neutral-500">
          {{ build.buildType }}
        </div>
      </div>
      <div
        class="tw-text-small tw-font-medium tw-text-neutral-500 tw-flex tw-flex-row tw-gap-2 tw-flex-wrap sm:tw-flex-nowrap tw-text-nowrap"
      >
        <a
          :href="`${$baseURL}/sites/${build.site.id}`"
          class="tw-truncate tw-link tw-link-hover"
        >
          <FontAwesomeIcon :icon="FA.faComputer" /> {{ build.site.name }}
        </a>
        &bull;
        <span
          v-if="build.operatingSystemName"
          class="tw-truncate"
        >
          <FontAwesomeIcon
            v-if="build.operatingSystemName === 'Windows'"
            :icon="FA.faWindows"
          />
          <!-- TODO: Add more specific Linux types. May require CTest work. -->
          <FontAwesomeIcon
            v-else-if="build.operatingSystemName === 'Linux'"
            :icon="FA.faLinux"
          />
          <FontAwesomeIcon
            v-else-if="build.operatingSystemName === 'Darwin' || build.operatingSystemName === 'OSX'"
            :icon="FA.faApple"
          />
          {{ build.operatingSystemName }} {{ build.operatingSystemRelease }}
          <span
            v-if="build.operatingSystemPlatform"
            class="tw-badge tw-badge-outline tw-text-xs tw-truncate"
          >
            {{ build.operatingSystemPlatform }}
          </span>
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
    </div>
  </LoadingIndicator>
</template>

<script>
import gql from 'graphql-tag';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import LoadingIndicator from './LoadingIndicator.vue';
import { DateTime, Interval } from 'luxon';
import { faComputer } from '@fortawesome/free-solid-svg-icons';
import {
  faWindows,
  faLinux,
  faApple,
} from '@fortawesome/free-brands-svg-icons';
import Utils from './Utils';

export default {
  name: 'BuildSummaryCard',
  components: { LoadingIndicator, FontAwesomeIcon },

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
            buildType
            generator
            operatingSystemName
            operatingSystemPlatform
            operatingSystemRelease
            compilerName
            compilerVersion
            site {
              id
              name
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
    FA() {
      return {
        faComputer,
        faWindows,
        faLinux,
        faApple,
      };
    },

    /**
     * If the build started sometime in the last month, display a relative timestamp.
     * Otherwise, display a shortened version of the full date string.
     */
    humanReadableBuildStartTime() {
      return Utils.formatRelativeTimestamp(this.build.startTime);
    },

    humanReadableTotalDuration() {
      return Interval.fromDateTimes(
        DateTime.fromISO(this.build.startTime),
        DateTime.fromISO(this.build.endTime),
      ).toDuration().rescale().toHuman({ unitDisplay: 'short' });
    },
  },

  methods: {

    fullHumanReadableDateTimeString(timestamp) {
      return DateTime.fromISO(timestamp).toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
    },
  },
};
</script>
