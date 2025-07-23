<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <BuildSummaryCard :build-id="buildid" />

    <loading-indicator :is-loading="loading">
      <div class="buildgroup">
        <table
          cellspacing="0"
          class="tabb striped"
          width="100%"
        >
          <thead>
            <tr class="table-heading1">
              <td
                class="center-text botl"
                colspan="100"
              >
                Dynamic Analysis
              </td>
            </tr>
            <tr class="table-heading">
              <th class="column-header">
                Name
              </th>
              <th class="column-header">
                Status
              </th>
              <th
                v-for="defecttype in cdash.defecttypes"
                class="column-header"
              >
                {{ defecttype.type }}
              </th>
              <th
                v-if="cdash.displaylabels"
                class="column-header"
              >
                Labels
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="DA in cdash.dynamicanalyses"
              align="center"
            >
              <td align="left">
                <a
                  class="cdash-link"
                  :href="$baseURL + '/viewDynamicAnalysisFile.php?id=' + DA.id"
                >
                  {{ DA.name }}
                </a>
              </td>

              <td :class="DA.status === 'Passed' ? 'normal' : 'error'">
                {{ DA.status }}
              </td>

              <!-- Show how many defects of each type were found for this test -->
              <td
                v-for="numdefects in DA.defects"
                :class="{warning: numdefects > 0}"
              >
                <span v-if="numdefects > 0">
                  {{ numdefects }}
                </span>
              </td>

              <!-- Labels -->
              <td v-if="cdash.displaylabels">
                {{ DA.labels }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </loading-indicator>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';

export default {
  name: 'ViewDynamicAnalysis',
  components: {BuildSummaryCard, LoadingIndicator},

  props: {
    buildid: {
      type: Number,
      default: -1,
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
    ApiLoader.loadPageData(this, `/api/v1/viewDynamicAnalysis.php?buildid=${this.buildid}`);
  },
};
</script>
