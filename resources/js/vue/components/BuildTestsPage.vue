<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="tests"
  >
    <div class="tw-flex tw-flex-col tw-w-full tw-gap-4">
      <BuildSummaryCard :build-id="buildId" />

      <details
        v-if="hasTestStartTimes"
        class="tw-collapse tw-collapse-plus tw-w-full tw-bg-base-100 tw-rounded-lg tw-border tw-border-gray-200"
        data-test="test-timeline"
      >
        <summary class="tw-collapse-title tw-text-xl tw-font-bold">
          <FontAwesomeIcon
            :icon="FA.faChartGantt"
            class="tw-mr-1"
          />
          Test Execution Timeline
        </summary>
        <div class="tw-collapse-content">
          <TestFlameChart :tests="testTimelineData" />
        </div>
      </details>

      <FilterBuilder
        filter-type="BuildTestsFiltersMultiFilterInput"
        primary-record-name="tests"
        :initial-filters="initialFilters"
        :execute-query-link="executeQueryLink"
        @change-filters="filters => changedFilters = filters"
      />
      <div
        v-if="hasChildFilters"
        class="tw-self-center"
        data-test="subproject-filter-notice"
      >
        {{ isSubProjectInclude ? 'Showing only tests for SubProjects:' : 'Hiding tests for SubProjects:' }}
        <b>{{ filteredSubProjectNames.join(', ') }}</b>
        (<a
          class="tw-link tw-link-hover tw-link-info"
          :href="showAllSubProjectsLink"
          data-test="show-all-subprojects-link"
        >show all</a>)
      </div>
      <LoadingIndicator :is-loading="!tests || (onlyDelta && !previousTests && previousBuildId !== null)">
        <div
          v-if="onlyDelta && tests && filteredTests.length === 0"
          class="tw-self-center"
        >
          No tests with changed state for this build.
        </div>
        <DataTable
          v-if="!onlyDelta || filteredTests.length > 0"
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
      </LoadingIndicator>
    </div>
  </BuildSidebar>
</template>

<script>

import DataTable from './shared/DataTable.vue';
import gql from 'graphql-tag';
import FilterBuilder from './shared/FilterBuilder.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import TestFlameChart from './shared/TestFlameChart.vue';
import Utils from './shared/Utils';
import { DateTime, Duration } from 'luxon';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faChartGantt } from '@fortawesome/free-solid-svg-icons';

const TEST_QUERY = gql`
  query(
    $buildid: ID,
    $filters: BuildTestsFiltersMultiFilterInput,
    $childFilters: BuildChildrenFiltersMultiFilterInput,
    $skipParentTests: Boolean = false,
    $measurementFilters: TestTestMeasurementsFiltersMultiFilterInput,
  ) {
    build(id: $buildid) {
      id
      tests(filters: $filters, first: 1000000) @skip(if: $skipParentTests) {
        edges {
          node {
            id
            name
            status
            details
            runningTime
            startTime
            timeStatusCategory
            testMeasurements(filters: $measurementFilters) {
              id
              name
              type
              value
            }
          }
        }
      }
      children(filters: $childFilters, first: 100000) {
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
                  startTime
                  timeStatusCategory
                  testMeasurements(filters: $measurementFilters) {
                    id
                    name
                    type
                    value
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
`;

function mapTestsQueryResult(data) {
  let tests = (data.build.tests?.edges ?? []).map((test) => ({
    ...test,
    subProject: '',
  }));
  data.build.children.edges.forEach((child) => {
    tests = tests.concat(
      child.node.tests.edges.map((test) => ({
        ...test,
        subProject: child.node.subProject.name,
      })),
    );
  });
  return tests;
}

export default {
  name: 'BuildTestsPage',

  components: {
    BuildSummaryCard,
    LoadingIndicator,
    FilterBuilder,
    TestFlameChart,
    DataTable,
    BuildSidebar,
    FontAwesomeIcon,
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

    /** Filters applied to the child builds, used to restrict results to a set of SubProjects. */
    initialChildFilters: {
      type: Object,
      required: false,
      default: () => ({ all: [] }),
    },

    /** A list of measurements to display, ordered by position. */
    pinnedMeasurements: {
      type: Array,
      required: true,
    },

    onlyDelta: {
      type: Boolean,
      required: false,
      default: false,
    },

    previousBuildId: {
      type: [Number, null],
      required: false,
      default: null,
    },
  },

  apollo: {
    tests: {
      query: TEST_QUERY,
      update: mapTestsQueryResult,
      variables() {
        return {
          buildid: this.buildId,
          filters: this.initialFilters,
          childFilters: this.initialChildFilters,
          skipParentTests: this.skipParentTests,
          measurementFilters: this.measurementFilters,
        };
      },
    },

    // Keep filtered-out tests on the timeline, grayed out rather than hidden.
    allTests: {
      query: TEST_QUERY,
      update: mapTestsQueryResult,
      variables() {
        return {
          buildid: this.buildId,
          filters: {},
          childFilters: this.initialChildFilters,
          skipParentTests: this.skipParentTests,
          measurementFilters: this.measurementFilters,
        };
      },
    },

    previousTests: {
      query: TEST_QUERY,
      update: mapTestsQueryResult,
      variables() {
        return {
          buildid: this.previousBuildId,
          filters: this.initialFilters,
          childFilters: this.initialChildFilters,
          skipParentTests: this.skipParentTests,
          measurementFilters: this.measurementFilters,
        };
      },
      skip() {
        return !this.onlyDelta || this.previousBuildId === null;
      },
    },
  },

  data() {
    return {
      changedFilters: JSON.parse(JSON.stringify(this.initialFilters)),
    };
  },

  computed: {
    FA() {
      return {
        faChartGantt,
      };
    },

    // Fetch the pinned measurement columns and numeric measurements used by the
    // test execution timeline and its tooltips. Unpinned text and file measurements
    // aren't displayed on this page.
    measurementFilters() {
      return {
        any: [
          ...this.pinnedMeasurements.map((name) => ({ eq: { name: name } })),
          { contains: { type: 'numeric/' } },
        ],
      };
    },

    filteredTests() {
      if (!this.onlyDelta) {
        return this.tests;
      }

      if (!this.tests) {
        return [];
      }

      if (!this.previousTests) {
        return [];
      }

      const previousTestsMap = new Map(this.previousTests.map((test) => [
        `${test.subProject}:${test.node.name}`,
        test.node.status,
      ]));

      return this.tests.filter((test) => {
        const key = `${test.subProject}:${test.node.name}`;
        const previousStatus = previousTestsMap.get(key);
        return previousStatus !== test.node.status;
      });
    },

    hasSubProjects() {
      return this.filteredTests?.some((element) => element.subProject) ?? false;
    },

    visibleTestIds() {
      return new Set((this.filteredTests ?? []).map((test) => test.node.id));
    },

    executedTests() {
      return (this.allTests ?? []).filter((test) => test.node.status !== 'NOT_RUN');
    },

    hasTestStartTimes() {
      return this.executedTests.some((test) => test.node.startTime);
    },

    testTimelineData() {
      if (!this.hasTestStartTimes) {
        return [];
      }

      return this.executedTests.filter((test) => test.node.startTime).map((test) => ({
        id: test.node.id,
        name: test.node.name,
        startTime: DateTime.fromISO(test.node.startTime),
        duration: Duration.fromObject({ seconds: test.node.runningTime }),
        status: test.node.status,
        subProject: test.subProject,
        disabled: !this.visibleTestIds.has(test.node.id),
        measurements: Utils.numericMeasurements(test.node.testMeasurements),
      }));
    },

    pinnedMeasurementColumns() {
      return this.pinnedMeasurements.map((name) => ({
        name: name,
        displayName: name,
      }));
    },

    hasChildFilters() {
      return (this.initialChildFilters.any ?? this.initialChildFilters.all ?? []).length > 0;
    },

    // Include filters are combined with "any", exclude filters with "all".
    isSubProjectInclude() {
      return (this.initialChildFilters.any ?? []).length > 0;
    },

    // Tests attached directly to the parent build don't belong to any SubProject,
    // so they can never match an include filter.
    skipParentTests() {
      return this.isSubProjectInclude;
    },

    filteredSubProjectNames() {
      return (this.initialChildFilters.any ?? this.initialChildFilters.all ?? [])
        .map((filter) => {
          const subProjectFilter = filter.has?.subProject;
          return subProjectFilter?.eq?.name ?? subProjectFilter?.ne?.name;
        })
        .filter((name) => name !== undefined);
    },

    executeQueryLink() {
      let link = this.showAllSubProjectsLink;
      if (this.hasChildFilters) {
        link += `&childFilters=${encodeURIComponent(JSON.stringify(this.initialChildFilters))}`;
      }
      return link;
    },

    showAllSubProjectsLink() {
      let link = `${window.location.origin}${window.location.pathname}?filters=${encodeURIComponent(JSON.stringify(this.changedFilters))}`;
      if (this.onlyDelta) {
        link += '&onlydelta';
      }
      return link;
    },

    formattedTestRows() {
      return this.filteredTests?.map((edge) => {
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
            href: `${this.$baseURL}/queryTests.php?project=${this.projectName}&date=${DateTime.fromISO(this.buildTime).toISODate()}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=${edge.node.name}`,
          },
          ...this.pinnedMeasurements.reduce((acc, name) => ({
            ...acc,
            [name]: edge.node.testMeasurements.find((measurementEdge) => measurementEdge.name === name)?.value ?? '',
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
