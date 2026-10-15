<template>
  <div
    class="tw-border tw-border-base-300 tw-rounded-lg tw-overflow-hidden"
    data-test="dashboard-card"
  >
    <div class="tw-flex tw-items-center tw-gap-2 tw-px-4 tw-py-2 tw-bg-base-200 tw-border-b tw-border-base-300">
      <FontAwesomeIcon
        :icon="FA.faChartLine"
        class="tw-text-neutral-500"
      />
      <span class="tw-font-bold">Recent activity</span>
    </div>
    <div
      v-if="lastSubmissionTime"
      class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-3 tw-divide-y sm:tw-divide-y-0 sm:tw-divide-x tw-divide-base-300"
    >
      <div class="tw-px-4 tw-py-3">
        <div class="tw-text-sm tw-text-neutral-500">
          Builds in the last 24 hours
        </div>
        <div
          class="tw-text-lg tw-font-semibold"
          data-test="builds-last-day"
        >
          {{ buildsLastDay.toLocaleString() }}
        </div>
      </div>
      <div class="tw-px-4 tw-py-3">
        <div class="tw-text-sm tw-text-neutral-500">
          Builds in the last 7 days
        </div>
        <div
          class="tw-text-lg tw-font-semibold"
          data-test="builds-last-week"
        >
          {{ buildsLastWeek.toLocaleString() }}
        </div>
      </div>
      <div class="tw-px-4 tw-py-3">
        <div class="tw-text-sm tw-text-neutral-500">
          Last submission
        </div>
        <div
          class="tw-text-lg tw-font-semibold"
          :title="DateTime.fromISO(lastSubmissionTime).toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)"
          data-test="last-submission"
        >
          {{ Utils.formatRelativeTimestamp(lastSubmissionTime) }}
        </div>
      </div>
    </div>
    <div
      v-else
      class="tw-px-4 tw-py-6 tw-text-center tw-text-neutral-500"
      data-test="no-builds-message"
    >
      No builds have been submitted to this project yet.
    </div>
    <div
      v-if="testingDayStart"
      class="tw-flex tw-items-center tw-gap-2 tw-px-4 tw-py-2 tw-border-t tw-border-base-300 tw-text-sm tw-text-neutral-500"
      data-test="testing-day-start"
    >
      <FontAwesomeIcon :icon="FA.faClock" />
      Testing day starts at {{ testingDayStart }}
    </div>
  </div>
</template>

<script>
import { DateTime } from 'luxon';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faChartLine,
  faClock,
} from '@fortawesome/free-solid-svg-icons';
import Utils from '../shared/Utils';

export default {
  name: 'RecentActivityCard',

  components: {
    FontAwesomeIcon,
  },

  props: {
    buildsLastDay: {
      type: Number,
      required: true,
    },

    buildsLastWeek: {
      type: Number,
      required: true,
    },

    // ISO 8601 submission time of the most recent build, or null if the project has no builds.
    lastSubmissionTime: {
      type: [String, null],
      default: null,
    },

    // A human-readable time and timezone, e.g. "01:00 America/New_York".
    testingDayStart: {
      type: [String, null],
      default: null,
    },
  },

  computed: {
    DateTime() {
      return DateTime;
    },

    Utils() {
      return Utils;
    },

    FA() {
      return {
        faChartLine,
        faClock,
      };
    },
  },
};
</script>
