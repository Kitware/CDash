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
          ...pinnedMeasurementColumns,
          {
            name: 'details',
            displayName: 'Details',
          },
          {
            name: 'status',
            displayName: 'Status',
          },
          ...(showTestTimeStatus ? [{
            name: 'timeStatus',
            displayName: 'Time Status',
          }] : []),
          {
            name: 'history',
            displayName: 'History',
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
import {DateTime} from 'luxon';

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

    showTestTimeStatus: {
      type: Boolean,
      required: true,
    },

    projectName: {
      type: String,
      required: true,
    },

    buildTime: {
      type: String,
      required: true,
    },

    initialFilters: {
      type: Object,
      required: true,
    },

    /** A list of measurements to display, ordered by position. */
    pinnedMeasurements: {
      type: Array,
      required: true,
    },
  },

  apollo: {
    tests: {
      query: gql`
        query(
          $buildid: ID,
          $filters: BuildTestsFiltersMultiFilterInput,
          $measurementFilters: TestTestMeasurementsFiltersMultiFilterInput,
        ) {
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
                  timeStatusCategory
                  testMeasurements(filters: $measurementFilters, first: 100) {
                    edges {
                      node {
                        id
                        name
                        value
                      }
                    }
                  }
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
                        timeStatusCategory
                        testMeasurements(filters: $measurementFilters, first: 100) {
                          edges {
                            node {
                              id
                              name
                              value
                            }
                          }
                        }
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
          measurementFilters: {
            any: this.pinnedMeasurements.map((name) => ({
              eq: {
                name: name,
              },
            })),
          },
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

    pinnedMeasurementColumns() {
      return this.pinnedMeasurements.map((name) => ({
        name: name,
        displayName: name,
      }));
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
          timeStatus: {
            value: edge.node.timeStatusCategory,
            text: this.humanReadableTestStatus(edge.node.timeStatusCategory),
            href: `${this.$baseURL}/tests/${edge.node.id}?graph=time`,
            classes: [this.testStatusToColorClass(edge.node.timeStatusCategory)],
          },
          history: {
            value: '',
            text: 'History',
            href: `${this.$baseURL}/queryTests.php?project=${this.projectName}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=${edge.node.name}&date=${DateTime.fromISO(this.buildTime).toISODate()}`,
          },
          ...this.pinnedMeasurements.reduce((acc, name) => ({
            ...acc,
            [name]: edge.node.testMeasurements.edges.find((measurementEdge) => measurementEdge.node.name === name)?.node.value ?? '',
          }), {}),
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
