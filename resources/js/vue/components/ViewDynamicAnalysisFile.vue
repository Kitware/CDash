<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <loading-indicator :is-loading="loading">
      <h3>Dynamic analysis started on {{ cdash.build.buildtime }}</h3>

      <table border="0">
        <tbody>
          <tr>
            <td align="right">
              <b>Site Name:</b>
            </td>
            <td>
              {{ cdash.build.site }}
            </td>
          </tr>

          <tr>
            <td align="right">
              <b>Build Name:</b>
            </td>
            <td>
              {{ cdash.build.buildname }}
            </td>
          </tr>
        </tbody>
      </table>

      <a
        class="cdash-link"
        :href="$baseURL + '/' + cdash.dynamicanalysis.href"
      >
        {{ cdash.dynamicanalysis.filename }}
      </a>

      <span :class="cdash.dynamicanalysis.status">
        {{ cdash.dynamicanalysis.status }}
      </span>

      <pre>{{ cdash.dynamicanalysis.log }}</pre>
    </loading-indicator>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from './shared/LoadingIndicator.vue';

export default {
  components: {LoadingIndicator},

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
