<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <filter-builder
      filter-type="BuildTestsFiltersMultiFilterInput"
      primary-record-name="tests"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @changeFilters="filters => changedFilters = filters"
    />
    <loading-indicator :is-loading="!build">
      <data-table
        :columns="[
          {
            name: 'name',
            displayName: 'Name',
            expand: true,
          },
          {
            name: 'time',
            displayName: 'Time',
          },
          {
            name: 'details',
            displayName: 'Details',
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
import FilterBuilder from './shared/FilterBuilder.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';

export default {
  name: 'BuildTestsPage',

  components: {
    BuildSummaryCard,
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
        query($buildid: ID, $filters: BuildTestsFiltersMultiFilterInput, $after: String) {
          build(id: $buildid) {
            tests(filters: $filters, after: $after, first: 100) {
              edges {
                node {
                  id
                  name
                  status
                  details
                  runningTime
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
        if (data && data.build.tests.pageInfo.hasNextPage) {
          this.$apollo.queries.build.fetchMore({
            variables: {
              after: data.build.tests.pageInfo.endCursor,
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

    formattedTestRows() {
      return this.build.tests.edges?.map(edge => {
        return {
          name: {
            value: edge.node.name,
            text: edge.node.name,
            href: `${this.$baseURL}/tests/${edge.node.id}`,
          },
          time: {
            value: edge.node.runningTime,
            text: `${edge.node.runningTime}s`,
          },
          details: edge.node.details,
          status: {
            // TODO: An integer value could be provided to provide better sorting in the future
            value: edge.node.status,
            text: this.humanReadableTestStatus(edge.node.status),
            href: `${this.$baseURL}/tests/${edge.node.id}`,
            classes: [this.testStatusToColorClass(edge.node.status)],
          },
        };
      });
    },
  },

  methods: {
    testStatusToColorClass(status) {
      switch (status) {
      case 'PASSED':
        return 'normal';
      case 'FAILED':
        return 'error';
      case 'NOT_RUN':
        return 'warning';
      case 'TIMEOUT':
        return 'error';
      case 'DISABLED':
        return '';
      default:
        return '';
      }
    },

    humanReadableTestStatus(status) {
      switch (status) {
      case 'PASSED':
        return 'Passed';
      case 'FAILED':
        return 'Failed';
      case 'NOT_RUN':
        return 'Not Run';
      case 'TIMEOUT':
        return 'Timeout';
      case 'DISABLED':
        return 'Disabled';
      default:
        return status;
      }
    },
  },
};
</script>
