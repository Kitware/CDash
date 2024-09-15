<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <filter-builder
      filter-type="BuildTestsFiltersMultiFilterInput"
      primary-record-name="tests"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @changeFilters="filters => changedFilters = filters"
    />
    <loading-indicator :is-loading="$apollo.loading">
      <data-table
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
            expand: true,
          },
          {
            name: 'status',
            displayName: 'Status',
          },
        ]"
        :rows="formattedTestRows"
        :full-width="true"
        initial-sort-column="status"
      />
    </loading-indicator>
  </div>
</template>

<script>

import DataTable from './shared/DataTable.vue';
import gql from 'graphql-tag';
import { useQuery } from '@vue/apollo-composable';
import FilterBuilder from './shared/FilterBuilder.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';

export default {
  name: 'BuildTestsPage',

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
      default() {
        return {};
      },
    },
  },

  apollo: {
    build: {
      query: gql`
        query($buildid: ID, $filters: BuildTestsFiltersMultiFilterInput, $after: String) {
          build(id: $buildid) {
            tests(filters: $filters, after: $after) {
              edges {
                node {
                  id
                  name
                  status
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
    },
  },

  data() {
    return {
      changedFilters: [],
    };
  },

  computed: {
    executeQueryLink() {
      return `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
    },

    formattedTestRows() {
      return this.build.tests.edges?.map(edge => {
        return {
          name: edge.node.name,
          status: edge.node.status,
        };
      });
    },
  },
};
</script>
