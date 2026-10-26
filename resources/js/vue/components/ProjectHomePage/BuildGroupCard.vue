<template>
  <div
    class="tw-flex tw-flex-col tw-bg-base-100 tw-border tw-border-base-300 tw-rounded-lg tw-min-w-0"
    data-test="build-group-card"
  >
    <div class="tw-px-3 tw-pt-2">
      <div
        class="tw-font-semibold tw-truncate"
        :title="name"
        data-test="build-group-name"
      >
        <a
          :href="url"
          class="tw-link tw-link-hover"
          data-test="build-group-link"
        >{{ name }}</a>
      </div>
      <div
        v-if="description"
        class="tw-text-xs tw-text-neutral-500 tw-truncate"
        :title="description"
        data-test="build-group-description"
      >
        {{ description }}
      </div>
    </div>
    <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-3 tw-gap-2 tw-px-3 tw-py-2">
      <div>
        <div class="tw-text-xs tw-text-neutral-500">
          Last 24 hours
        </div>
        <div
          class="tw-font-semibold"
          data-test="group-builds-last-day"
        >
          {{ buildsLastDay.toLocaleString() }}
        </div>
      </div>
      <div>
        <div class="tw-text-xs tw-text-neutral-500">
          Last 7 days
        </div>
        <div
          class="tw-font-semibold"
          data-test="group-builds-last-week"
        >
          {{ buildsLastWeek.toLocaleString() }}
        </div>
      </div>
      <div class="tw-col-span-2 sm:tw-col-span-1 tw-min-w-0">
        <div class="tw-text-xs tw-text-neutral-500 tw-truncate">
          Last submission
        </div>
        <div
          v-if="lastSubmissionTime"
          class="tw-font-semibold tw-truncate"
          :title="DateTime.fromISO(lastSubmissionTime).toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)"
          data-test="group-last-submission"
        >
          {{ Utils.formatRelativeTimestamp(lastSubmissionTime) }}
        </div>
        <div
          v-else
          class="tw-font-semibold tw-truncate"
          data-test="group-last-submission"
        >
          More than {{ HISTOGRAM_DAYS }} days ago
        </div>
      </div>
    </div>
    <div class="tw-px-3 tw-pb-2">
      <BuildHistogramChart
        :histogram="histogram"
        :load-failed="histogramLoadFailed"
        class="tw-h-28"
      />
    </div>
  </div>
</template>

<script>
import { DateTime } from 'luxon';
import Utils from '../shared/Utils';
import BuildHistogramChart from './BuildHistogramChart.vue';
import { HISTOGRAM_DAYS } from './BuildHistogram';

export default {
  name: 'BuildGroupCard',

  components: {
    BuildHistogramChart,
  },

  props: {
    name: {
      type: String,
      required: true,
    },

    description: {
      type: [String, null],
      default: null,
    },

    buildsLastDay: {
      type: Number,
      required: true,
    },

    buildsLastWeek: {
      type: Number,
      required: true,
    },

    // ISO 8601 submission time of the most recent build, or null if the group has no builds in the histogram window.
    lastSubmissionTime: {
      type: [String, null],
      default: null,
    },

    // The build group's page on the dashboard.
    url: {
      type: String,
      required: true,
    },

    /**
     * Passing and failing builds for each testing day, and the page each bar links to, or null while loading.
     *
     * @type {Array<{date: import('luxon').DateTime, passed: number, failed: number, url: string}>|null}
     */
    histogram: {
      type: [Array, null],
      required: true,
    },

    histogramLoadFailed: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    HISTOGRAM_DAYS() {
      return HISTOGRAM_DAYS;
    },

    DateTime() {
      return DateTime;
    },

    Utils() {
      return Utils;
    },
  },
};
</script>
