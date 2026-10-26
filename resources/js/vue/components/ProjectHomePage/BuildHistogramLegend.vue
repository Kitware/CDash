<template>
  <div
    class="tw-flex tw-items-center tw-gap-3 tw-text-xs tw-text-neutral-500"
    data-test="build-histogram-legend"
  >
    <span>Last {{ HISTOGRAM_DAYS }} days</span>
    <span class="tw-flex tw-items-center tw-gap-1">
      <span
        class="tw-inline-block tw-w-2.5 tw-h-2.5 tw-rounded-sm"
        :style="{ background: PASSED_COLOR }"
      />
      Passed
    </span>
    <span class="tw-flex tw-items-center tw-gap-1">
      <!-- Matches the chart's striped decal. -->
      <span
        class="tw-inline-block tw-w-2.5 tw-h-2.5 tw-rounded-sm"
        :style="{ background: `repeating-linear-gradient(-45deg, rgba(0, 0, 0, 0.2) 0 1.5px, transparent 1.5px 4px), ${FAILED_COLOR}` }"
      />
      Failed
    </span>
  </div>
</template>

<script>
import {
  FAILED_COLOR,
  HISTOGRAM_DAYS,
  PASSED_COLOR,
} from './BuildHistogram';

export default {
  name: 'BuildHistogramLegend',

  computed: {
    HISTOGRAM_DAYS() {
      return HISTOGRAM_DAYS;
    },

    PASSED_COLOR() {
      return PASSED_COLOR;
    },

    FAILED_COLOR() {
      return FAILED_COLOR;
    },
  },
};
</script>
