<template>
  <BuildSidebar
    :build-id="buildid"
    active-tab="dynamic_analysis"
  >
    <section v-if="errored">
      <p>{{ cdash.error }}</p>
    </section>
    <section
      v-else
      class="tw-flex tw-flex-col tw-gap-4"
    >
      <BuildSummaryCard :build-id="buildid" />

      <loading-indicator :is-loading="loading">
        <data-table
          :columns="columns"
          :column-groups="columnGroups"
          :rows="rows"
          :full-width="true"
          initial-sort-column="status"
          test-id="dynamic-analysis-table"
        />
      </loading-indicator>
    </section>
  </BuildSidebar>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import DataTable from './shared/DataTable.vue';

export default {
  name: 'ViewDynamicAnalysis',
  components: {
    BuildSidebar,
    BuildSummaryCard,
    LoadingIndicator,
    DataTable,
  },

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

  computed: {
    columns() {
      if (!this.cdash.defecttypes) {
        return [];
      }
      const columns = [
        {
          name: 'name',
          displayName: 'Name',
          expand: true,
        },
        {
          name: 'status',
          displayName: 'Status',
        },
      ];

      this.cdash.defecttypes.forEach((defecttype, index) => {
        columns.push({
          name: `defect_${index}`,
          displayName: defecttype.type,
        });
      });

      if (this.cdash.displaylabels) {
        columns.push({
          name: 'labels',
          displayName: 'Labels',
        });
      }

      return columns;
    },

    rows() {
      if (!this.cdash.dynamicanalyses) {
        return [];
      }
      return this.cdash.dynamicanalyses.map(DA => {
        const row = {
          name: {
            text: DA.name,
            value: DA.name,
            href: `${this.$baseURL}/viewDynamicAnalysisFile.php?id=${DA.id}`,
          },
          status: {
            text: DA.status,
            value: DA.status,
            classes: [DA.status === 'Passed' ? 'normal' : 'error'],
          },
        };

        DA.defects.forEach((numdefects, index) => {
          row[`defect_${index}`] = {
            text: numdefects > 0 ? numdefects : '',
            value: numdefects,
            classes: numdefects > 0 ? ['warning'] : [],
          };
        });

        if (this.cdash.displaylabels) {
          row.labels = DA.labels;
        }

        return row;
      });
    },

    columnGroups() {
      if (this.columns.length === 0) {
        return [];
      }
      return [
        {
          displayName: 'Dynamic Analysis',
          width: this.columns.length,
        },
      ];
    },
  },

  mounted () {
    ApiLoader.loadPageData(this, `/api/v1/viewDynamicAnalysis.php?buildid=${this.buildid}`);
  },
};
</script>
