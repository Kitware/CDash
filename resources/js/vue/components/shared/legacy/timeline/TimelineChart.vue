<template>
  <div>
    <img
      v-if="loading"
      id="spinner"
      src="img/loading.gif"
    >
    <div
      v-if="error"
      data-test="timeline-error"
    >
      <b>Error loading timeline: {{ error }}</b>
    </div>
    <div v-show="!loading && !error && timeline?.data?.length">
      <div
        id="timechart"
        class="row"
        data-test="timeline-chart"
      >
        <div class="col-md-12">
          <svg
            ref="svg"
            width="100%"
            style="height:175px"
          />
        </div>
      </div>
      <div class="row">
        <div class="text-center">
          <button
            type="submit"
            class="btn btn-default"
            data-test="timeline-update"
            @click="$emit('update', selection)"
          >
            Update
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { legacyRequest } from '../legacyRequest';
import { drawTimelineChart } from './timelineChart';

/**
 * The timeline of a page's results, from timeline.php, which can be used to choose a range of days.  The
 * page decides what to do with the range when the Update button is clicked.
 *
 * Remove this once the timeline is drawn from GraphQL data.
 */
export default {
  name: 'TimelineChart',

  props: {
    projectName: {
      type: String,
      required: true,
    },

    /** The build group whose results to show, if the page shows one. */
    buildGroup: {
      type: String,
      default: null,
    },

    /**
     * The page's `filterdata` from its legacy API, which tells the timeline API which page's results
     * to show, how to filter them, and which colors to use.
     */
    filterdata: {
      type: Object,
      required: true,
    },

    /** The page's date range, or its date if it doesn't have a range. */
    begin: {
      type: String,
      default: null,
    },

    end: {
      type: String,
      default: null,
    },

    date: {
      type: String,
      default: null,
    },
  },

  emits: {
    /** Emitted when the Update button is clicked, with the first and last selected days. */
    update: (selection) => typeof selection?.begin === 'string' && typeof selection?.end === 'string',
  },

  data() {
    return {
      loading: true,
      error: null,
      timeline: null,
      selection: null,
    };
  },

  async mounted() {
    const params = {
      project: this.projectName,
      // The API expects JSON.
      filterdata: JSON.stringify(this.filterdata),
    };
    if (this.buildGroup !== null) {
      params.buildgroup = this.buildGroup;
    }
    if (this.begin !== null && this.end !== null) {
      params.begin = this.begin;
      params.end = this.end;
    } else {
      params.date = this.date;
    }

    try {
      // Fetch the charting libraries alongside the data they will display.
      const [timeline, { d3, nv }] = await Promise.all([
        legacyRequest('GET', 'timeline.php', { params }),
        import('./nvd3'),
      ]);
      this.timeline = timeline;
      this.loading = false;
      if (!timeline?.data?.length) {
        return;
      }
      // Draw the chart once it's visible, so it can be measured.
      await this.$nextTick();
      drawTimelineChart({
        svg: this.$refs.svg,
        timeline,
        d3,
        nv,
        onSelect: (selection) => {
          this.selection = selection;
        },
      });
    } catch (error) {
      this.error = error.data?.error ?? error.data?.message ?? error.message;
      this.loading = false;
    }
  },
};
</script>
