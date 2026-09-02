<template>
  <LoadingIndicator :is-loading="!build">
    <div class="tw-border-base-300 tw-bg-base-200 tw-border tw-rounded-lg tw-p-4">
      <div class="tw-flex tw-flex-row tw-justify-between tw-items-start">
        <div class="tw-text-lg tw-font-medium tw-truncate tw-text-nowrap">
          <a
            :href="`${$baseURL}/builds/${build.id}`"
            class="tw-link tw-link-hover"
          >{{ build.name }}</a>
          <div class="tw-badge tw-badge-outline tw-ml-2 tw-text-neutral-500">
            {{ build.buildType }}
          </div>
        </div>
        <div class="tw-text-small tw-font-medium tw-text-neutral-500 tw-space-x-1 tw-shrink-0">
          <span :title="fullHumanReadableDateTimeString(build.startTime)">{{ humanReadableBuildStartTime }}</span>
          <span v-if="humanReadableTotalDuration">({{ humanReadableTotalDuration }})</span>
        </div>
      </div>
      <div class="tw-text-small tw-font-medium tw-text-neutral-500 tw-flex tw-flex-row tw-gap-4 tw-flex-wrap tw-text-nowrap tw-mt-1">
        <a
          :href="`${$baseURL}/sites/${build.site.id}`"
          class="tw-truncate tw-min-w-0 tw-link-hover"
        >
          <FontAwesomeIcon :icon="FA.faComputer" /> {{ build.site.name }}
        </a>

        <span
          v-if="build.operatingSystemName"
          class="tw-truncate tw-min-w-0"
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
        </span>

        <span
          v-if="build.operatingSystemPlatform"
          class="tw-truncate tw-min-w-0"
        >
          <FontAwesomeIcon
            :icon="FA.faMicrochip"
          />
          {{ build.operatingSystemPlatform }}
        </span>

        <span
          v-if="build.updateStep?.revision"
          class="tw-truncate tw-min-w-0"
        >
          <FontAwesomeIcon
            :icon="FA.faCodeCommit"
          />
          <span class="tw-font-mono">
            {{ build.updateStep?.revision }}
          </span>
        </span>
      </div>
      <div
        v-if="build.labels && build.labels.edges.length > 0"
        class="tw-mt-2 tw-flex tw-flex-row tw-flex-wrap tw-gap-2"
      >
        <span
          v-for="label in build.labels.edges"
          :key="label.node.id"
          class="tw-badge tw-badge-outline tw-text-xs tw-text-neutral-500"
        >
          {{ label.node.text }}
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
import {
  faComputer,
  faMicrochip,
  faCodeCommit,
} from '@fortawesome/free-solid-svg-icons';
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
            operatingSystemName
            operatingSystemPlatform
            operatingSystemRelease
            site {
              id
              name
            }
            updateStep {
              id
              revision
            }
            # We assume that projects won't have more than 100 labels.  Displaying more would be a challenge...
            labels(first: 100) {
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
    FA() {
      return {
        faComputer,
        faWindows,
        faLinux,
        faApple,
        faMicrochip,
        faCodeCommit,
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
