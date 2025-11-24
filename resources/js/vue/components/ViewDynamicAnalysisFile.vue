<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <build-summary-card :build-id="buildid" />

    <loading-indicator :is-loading="loading">
      <br>
      <a
        class="tw-link tw-link-hover"
        :href="$baseURL + '/' + cdash.dynamicanalysis.href"
      >
        {{ cdash.dynamicanalysis.filename }}
      </a>

      <span :class="cdash.dynamicanalysis.status">
        {{ cdash.dynamicanalysis.status }}
      </span>

      <code-box :text="cdash.dynamicanalysis.log" />
    </loading-indicator>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import CodeBox from './shared/CodeBox.vue';

export default {
  components: {CodeBox, BuildSummaryCard, LoadingIndicator},

  props: {
    buildid: {
      type: Number,
      required: true,
    },
    fileid: {
      type: Number,
      required: true,
    },
  },

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
    };
  },

  mounted () {
    ApiLoader.loadPageData(this, `/api/v1/viewDynamicAnalysisFile.php?id=${this.fileid}`);
  },
};
</script>
