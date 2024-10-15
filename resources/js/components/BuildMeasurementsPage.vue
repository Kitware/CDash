<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <filter-builder
      filter-type="BuildMeasurementsFiltersMultiFilterInput"
      primary-record-name="build measurements"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @changeFilters="filters => changedFilters = filters"
    />
    <loading-indicator :is-loading="!build">
      <data-table
        :columns="columns"
        :rows="formattedMeasurementRows"
        :full-width="true"
        initial-sort-column="source"
      />
    </loading-indicator>
  </div>
</template>

<script>

import DataTable from './shared/DataTable.vue';
import gql from 'graphql-tag';
import FilterBuilder from './shared/FilterBuilder.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';

export default {
  components: {
    LoadingIndicator,
    FilterBuilder,
    DataTable,
  },

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    initialFilters: {
      type: Object,
      required: true,
    },
  },

  apollo: {
    build: {
      query: gql`
        query($buildid: ID, $filters: BuildMeasurementsFiltersMultiFilterInput, $after: String) {
          build(id: $buildid) {
            measurements(filters: $filters, after: $after, first: 100) {
              edges {
                node {
                  id
                  name
                  source
                  type
                  value
                }
              }
              pageInfo {
                hasNextPage
                hasPreviousPage
                startCursor
                endCursor
              }
            }
          }
        }
      `,
      variables() {
        return {
          buildid: this.buildId,
          filters: this.initialFilters,
          after: '',
        };
      },
      result({data}) {
        if (data && data.build.measurements.pageInfo.hasNextPage) {
          this.$apollo.queries.build.fetchMore({
            variables: {
              after: data.build.measurements.pageInfo.endCursor,
            },
          });
        }
      },
    },
  },

  data() {
    return {
      changedFilters: JSON.parse(JSON.stringify(this.initialFilters)),
    };
  },

  computed: {
    executeQueryLink() {
      return `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
    },

    columns() {
      const uniqueMeasurements = [...new Set(this.build.measurements.edges.map(edge => {
        return edge.node.name;
      }))];

      const columns = [
        {
          name: 'source',
          displayName: 'Source',
          expand: true,
        },
        {
          name: 'type',
          displayName: 'Type',
        },
      ];

      uniqueMeasurements.forEach(element => {
        columns.push({
          name: `measurement_${element}`,
          displayName: element,
        });
      });

      return columns;
    },

    formattedMeasurementRows() {
      // A mapping of the form: source_type => {row object}
      const source_type_pairs = {};

      this.build.measurements.edges.forEach(edge => {
        const key = `${edge.node.source}_${edge.node.type}`;
        if (!source_type_pairs.hasOwnProperty(key)) {
          source_type_pairs[key] = {
            source: edge.node.source,
            type: edge.node.type,
          };
        }

        source_type_pairs[key][`measurement_${edge.node.name}`] = {
          value: isNaN(edge.node.value) ? edge.node.value : Number(edge.node.value),
          text: edge.node.value,
        };
      });

      return Object.values(source_type_pairs);
    },
  },
};
</script>
