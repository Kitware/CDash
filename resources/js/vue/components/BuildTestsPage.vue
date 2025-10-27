<template>
  <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
    <BuildSummaryCard :build-id="buildId" />

    <filter-builder
      filter-type="BuildTestsFiltersMultiFilterInput"
      primary-record-name="tests"
      :initial-filters="initialFilters"
      :execute-query-link="executeQueryLink"
      @change-filters="filters => changedFilters = filters"
    />
    <loading-indicator :is-loading="!tests">
      <data-table
        :columns="[
          ...(hasSubProjects ? [{
            name: 'subProject',
            displayName: 'SubProject',
          }] : []),
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
        test-id="tests-table"
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
    tests: {
      query: gql`
        query($buildid: ID, $filters: BuildTestsFiltersMultiFilterInput) {
          build(id: $buildid) {
            id
            tests(filters: $filters, first: 1000000) {
              edges {
                node {
                  id
                  name
                  status
                  details
                  runningTime
                }
              }
            }
            children(first: 100000) {
              edges {
                node {
                  id
                  tests(filters: $filters, first: 1000000) {
                    edges {
                      node {
                        id
                        name
                        status
                        details
                        runningTime
                      }
                    }
                  }
                  subProject {
                    id
                    name
                  }
                }
              }
            }
          }
        }
      `,
      update: (data) => {
        let tests = [...data.build.tests.edges];
        data.build.children.edges.forEach((child) => {
          tests = tests.concat(
            child.node.tests.edges.map((test) => ({
              ...test,
              subProject: child.node.subProject.name,
            })),
          );
        });
        return tests;
      },
      variables() {
        return {
          buildid: this.buildId,
          filters: this.initialFilters,
        };
      },
    },
  },

  data() {
    return {
      changedFilters: JSON.parse(JSON.stringify(this.initialFilters)),
    };
  },

  computed: {
    hasSubProjects() {
      return this.tests?.some((element) => element.subProject) ?? false;
    },

    executeQueryLink() {
      return `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
    },

    formattedTestRows() {
      return this.tests?.map(edge => {
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
          subProject: edge.subProject ?? '',
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
      default:
        return status;
      }
    },
  },
};
</script>
