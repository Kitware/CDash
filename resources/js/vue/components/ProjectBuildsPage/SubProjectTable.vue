<template>
  <table
    id="subproject"
    border="0"
    cellpadding="4"
    cellspacing="0"
    width="100%"
    class="tabb"
    data-test="subproject-table"
  >
    <thead>
      <tr class="table-heading1">
        <td
          colspan="11"
          align="left"
          class="nob"
        >
          <h3>{{ title }}</h3>
        </td>
      </tr>
      <tr class="table-heading">
        <SortableHeader
          rowspan="2"
          style="cursor: pointer; text-align: center;"
          data-test="header-subproject"
          :direction="direction('name')"
          @sort="(append) => sort('name', append)"
        >
          <b>SubProject</b>
        </SortableHeader>

        <td
          colspan="3"
          style="text-align: center;"
        >
          <b>Configure</b>
        </td>
        <td
          colspan="3"
          style="text-align: center;"
        >
          <b>Build</b>
        </td>
        <td
          colspan="3"
          style="text-align: center;"
        >
          <b>Test</b>
        </td>

        <SortableHeader
          v-if="showLastSubmission"
          rowspan="2"
          class="nob"
          style="cursor: pointer; text-align: center;"
          data-test="header-start-time"
          :direction="direction('lastsubmission')"
          @sort="(append) => sort('lastsubmission', append)"
        >
          <b>Start Time</b>
        </SortableHeader>
      </tr>
      <tr class="table-heading">
        <SortableHeader
          v-for="column in COLUMNS"
          :key="column.field"
          style="cursor: pointer; text-align: center;"
          :data-test="`header-${column.name}`"
          :direction="direction(column.field)"
          @sort="(append) => sort(column.field, append)"
        >
          <b>{{ column.title }}</b>
        </SortableHeader>
      </tr>
    </thead>

    <tbody>
      <tr
        v-for="(subproject, index) in sortedSubProjects"
        :key="subproject.name"
        :class="index % 2 === 1 ? 'odd' : 'even'"
        data-test="subproject-row"
      >
        <td
          align="center"
          data-test="subproject"
        >
          <a
            class="cdash-link"
            :href="`index.php?subproject=${subproject.name_encoded}&project=${projectNameEncoded}&date=${date}`"
          >
            {{ subproject.name }}
          </a>
        </td>

        <td
          v-for="column in COLUMNS"
          :key="column.field"
          align="center"
          :data-test="column.name"
          :class="statusClass(subproject, column)"
        >
          <span v-show="hasResults(subproject, column.section)">
            {{ subproject[column.field] }}
          </span>
        </td>

        <td
          v-if="showLastSubmission"
          align="center"
          class="nob"
          data-test="start-time"
        >
          {{ subproject.starttime }}
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script>
import orderBy from '../shared/legacy/sorting/angularOrderBy';
import { showEmptySubProjectsLast, subProjectHasResults } from './legacy/showEmptyLast';
import { useSortFields } from '../shared/legacy/sorting/useSortFields';
import SortableHeader from '../shared/legacy/sorting/SortableHeader.vue';

const COLUMNS = [
  { name: 'configure-errors', field: 'nconfigureerror', section: 'configure', title: 'Error', problem: 'error' },
  { name: 'configure-warnings', field: 'nconfigurewarning', section: 'configure', title: 'Warning', problem: 'warning' },
  { name: 'configure-passed', field: 'nconfigurepass', section: 'configure', title: 'Pass', problem: null },
  { name: 'build-errors', field: 'nbuilderror', section: 'build', title: 'Error', problem: 'error' },
  { name: 'build-warnings', field: 'nbuildwarning', section: 'build', title: 'Warning', problem: 'warning' },
  { name: 'build-passed', field: 'nbuildpass', section: 'build', title: 'Pass', problem: null },
  { name: 'tests-not-run', field: 'ntestnotrun', section: 'test', title: 'Not Run', problem: 'warning' },
  { name: 'tests-failed', field: 'ntestfail', section: 'test', title: 'Fail', problem: 'error' },
  { name: 'tests-passed', field: 'ntestpass', section: 'test', title: 'Pass', problem: null },
];

/**
 * The number of builds with and without problems for each of a set of SubProjects, e.g. the
 * dependencies of the SubProject being viewed.
 */
export default {
  name: 'SubProjectTable',

  components: {
    SortableHeader,
  },

  props: {
    title: {
      type: String,
      required: true,
    },

    /** SubProjects from the legacy index API. */
    subprojects: {
      type: Array,
      required: true,
    },

    projectNameEncoded: {
      type: String,
      required: true,
    },

    date: {
      type: String,
      required: true,
    },

    showLastSubmission: {
      type: Boolean,
      default: false,
    },

    /** The cookie which remembers how the table is sorted. */
    sortCookie: {
      type: String,
      required: true,
    },
  },

  setup(props) {
    return { COLUMNS, ...useSortFields(() => props.sortCookie) };
  },

  computed: {
    sortedSubProjects() {
      return showEmptySubProjectsLast(orderBy(this.subprojects, this.sortFields), this.sortFields);
    },
  },

  methods: {
    hasResults(subproject, section) {
      return subProjectHasResults(subproject, section);
    },

    /**
     * Problems are highlighted, and sections without results aren't colored.
     */
    statusClass(subproject, column) {
      const hasResults = this.hasResults(subproject, column.section);
      if (column.problem !== null && subproject[column.field] > 0) {
        return column.problem;
      }
      return hasResults ? 'normal' : '';
    },
  },
};
</script>
