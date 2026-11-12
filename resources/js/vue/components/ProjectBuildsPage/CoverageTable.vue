<template>
  <div>
    <a
      id="Coverage"
      class="cdash-link"
    />
    <table
      id="coveragetable"
      border="0"
      cellpadding="4"
      cellspacing="0"
      width="100%"
      class="tabb"
    >
      <thead>
        <tr class="table-heading2">
          <td
            colspan="1"
            class="nob"
          >
            <h3>
              <a
                href="#Coverage"
                class="grouptrigger cdash-link"
              >
                Coverage
              </a>
            </h3>
          </td>
          <td
            colspan="6"
            align="right"
            class="nob"
          />
        </tr>

        <tr class="table-heading">
          <template v-if="childView">
            <SortableHeader
              class="column-header"
              data-test="header-subproject"
              :direction="direction('label')"
              @sort="(append) => sort('label', append)"
            >
              SubProject
            </SortableHeader>
          </template>
          <template v-else>
            <SortableHeader
              class="column-header"
              data-test="header-site"
              :direction="direction('site')"
              @sort="(append) => sort('site', append)"
            >
              Site
            </SortableHeader>
            <SortableHeader
              class="column-header"
              data-test="header-build-name"
              :direction="direction('buildname')"
              @sort="(append) => sort('buildname', append)"
            >
              Build Name
            </SortableHeader>
          </template>
          <SortableHeader
            class="column-header"
            data-test="header-percentage"
            :direction="direction('percentage')"
            @sort="(append) => sort('percentage', append)"
          >
            Percentage
          </SortableHeader>
          <SortableHeader
            class="column-header"
            data-test="header-loc-tested"
            :direction="direction('loctested')"
            @sort="(append) => sort('loctested', append)"
          >
            LOC Tested
          </SortableHeader>
          <SortableHeader
            class="column-header"
            data-test="header-loc-untested"
            :direction="direction('locuntested')"
            @sort="(append) => sort('locuntested', append)"
          >
            LOC Untested
          </SortableHeader>
          <SortableHeader
            class="column-header"
            :class="{ nob: !displayLabels && coverageGroups?.length === 0 }"
            data-test="header-date"
            :direction="direction('datefull')"
            @sort="(append) => sort('datefull', append)"
          >
            Date
          </SortableHeader>
          <th
            v-if="displayLabels && !grouped"
            class="column-header nob"
            data-test="header-labels"
          >
            Labels
          </th>
        </tr>
      </thead>

      <!-- With SubProject groups, each group has a section, starting with the group's total. -->
      <template v-if="grouped">
        <tbody
          v-for="group in sortedGroups"
          :key="group.id"
          class="coveragebody"
          data-test="coverage-group"
        >
          <tr
            class="parent_row"
            data-test="coverage-group-summary"
          >
            <td
              class="paddt"
              align="left"
              data-test="subproject"
            >
              <b>{{ group.label }}</b>
              <div
                v-if="group.label !== 'Total'"
                class="glyphicon"
                :class="hiddenGroups.includes(group.id) ? 'glyphicon-chevron-right' : 'glyphicon-chevron-down'"
                data-test="coverage-group-toggle"
                @click="toggleGroup(group)"
              />
            </td>
            <td
              align="center"
              data-test="percentage"
              :class="status(group.percentage, group)"
            >
              {{ group.percentage }}%
            </td>
            <td
              class="center-text"
              data-test="loc-tested"
            >
              {{ group.loctested }}
            </td>
            <td
              class="center-text"
              data-test="loc-untested"
            >
              {{ group.locuntested }}
            </td>
            <td class="center-text" />
          </tr>

          <tr
            v-for="(coverage, index) in sorted(group.coverages ?? [])"
            v-show="!hiddenGroups.includes(group.id)"
            :key="rowKey(coverage)"
            class="child_row"
            :class="index % 2 === 1 ? 'odd' : 'even'"
            data-test="coverage-row"
          >
            <td
              align="left"
              class="paddt"
              data-test="subproject"
            >
              {{ coverage.label }}
            </td>
            <td
              align="center"
              data-test="percentage"
              :class="status(coverage.percentage, group)"
            >
              <a
                class="cdash-link"
                :href="`builds/${coverage.buildid}/coverage`"
              >
                {{ coverage.percentage }}%
              </a>
              <CoverageDiff
                :diff="coverage.percentagediff"
                suffix="%"
              />
            </td>
            <td
              class="center-text"
              data-test="loc-tested"
            >
              {{ coverage.loctested }}
              <CoverageDiff :diff="coverage.loctesteddiff" />
            </td>
            <td
              class="center-text"
              data-test="loc-untested"
            >
              {{ coverage.locuntested }}
              <CoverageDiff :diff="coverage.locuntesteddiff" />
            </td>
            <td
              align="center"
              data-test="date"
              :class="{ nob: !displayLabels }"
            >
              <span
                class="builddateelapsed"
                :title="coverage.date"
              >
                {{ coverage.dateelapsed }}
              </span>
            </td>
          </tr>
        </tbody>
      </template>

      <tbody
        v-else
        class="coveragebody"
      >
        <tr
          v-for="(coverage, index) in sorted(coverages)"
          :key="rowKey(coverage)"
          class="child_row"
          :class="index % 2 === 1 ? 'odd' : 'even'"
          data-test="coverage-row"
        >
          <template v-if="childView">
            <td
              align="left"
              class="paddt"
              data-test="subproject"
            >
              {{ coverage.label }}
            </td>
          </template>
          <template v-else>
            <td
              align="left"
              class="paddt"
              data-test="site"
            >
              <a
                class="cdash-link"
                :href="`sites/${coverage.siteid}?project=${projectId}&currenttime=${unixTimestamp}`"
              >
                {{ coverage.site }}
              </a>
            </td>
            <td
              align="left"
              class="paddt"
              data-test="build-name"
            >
              <a
                class="cdash-link"
                :href="`builds/${coverage.buildid}`"
              >
                {{ coverage.buildname }}
              </a>
            </td>
          </template>
          <td
            align="center"
            data-test="percentage"
            :class="status(coverage.percentage, thresholds)"
          >
            <!-- Parent builds without coverage of their own link to their children's coverage. -->
            <a
              class="cdash-link"
              :href="coverage.childlink ?? `builds/${coverage.buildid}/coverage`"
            >
              {{ coverage.percentage }}%
            </a>
            <CoverageDiff
              :diff="coverage.percentagediff"
              suffix="%"
            />
          </td>
          <td
            class="center-text"
            data-test="loc-tested"
          >
            {{ coverage.loctested }}
            <CoverageDiff :diff="coverage.loctesteddiff" />
          </td>
          <td
            class="center-text"
            data-test="loc-untested"
          >
            {{ coverage.locuntested }}
            <CoverageDiff :diff="coverage.locuntesteddiff" />
          </td>
          <td
            align="center"
            data-test="date"
            :class="{ nob: !displayLabels }"
          >
            <span
              class="builddateelapsed"
              :title="coverage.date"
            >
              {{ coverage.dateelapsed }}
            </span>
          </td>
          <td
            v-if="displayLabels"
            class="nob"
            align="left"
            data-test="labels"
          >
            {{ coverage.label }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import orderBy from '../shared/legacy/sorting/angularOrderBy';
import { useSortFields } from '../shared/legacy/sorting/useSortFields';
import SortableHeader from '../shared/legacy/sorting/SortableHeader.vue';
import CoverageDiff from './CoverageDiff.vue';
import { rowKey } from './rowKey';

/**
 * The coverage of the builds, from the legacy index API.  A parent build's children are grouped by
 * their SubProjects' groups, if the project has any.
 */
export default {
  name: 'CoverageTable',

  components: {
    CoverageDiff,
    SortableHeader,
  },

  props: {
    coverages: {
      type: Array,
      required: true,
    },

    /** The coverage of a parent build's children, by SubProject group. */
    coverageGroups: {
      type: Array,
      default: undefined,
    },

    /** The project's coverage thresholds, as `thresholdgreen` and `thresholdyellow`. */
    thresholds: {
      type: Object,
      required: true,
    },

    childView: {
      type: Boolean,
      required: true,
    },

    displayLabels: {
      type: Boolean,
      required: true,
    },

    projectId: {
      type: Number,
      required: true,
    },

    unixTimestamp: {
      type: Number,
      required: true,
    },

    /** The cookie which remembers how the table is sorted. */
    sortCookie: {
      type: String,
      required: true,
    },
  },

  setup(props) {
    return useSortFields(() => props.sortCookie);
  },

  data() {
    return {
      hiddenGroups: [],
    };
  },

  computed: {
    grouped() {
      return (this.coverageGroups?.length ?? 0) > 0;
    },

    sortedGroups() {
      return orderBy(this.coverageGroups, 'position');
    },
  },

  methods: {
    rowKey,

    sorted(coverages) {
      return orderBy(coverages, this.sortFields);
    },

    status(percentage, { thresholdgreen, thresholdyellow }) {
      if (percentage >= thresholdgreen) {
        return 'normal';
      }
      return percentage >= thresholdyellow ? 'warning' : 'error';
    },

    toggleGroup(group) {
      this.hiddenGroups = this.hiddenGroups.includes(group.id)
        ? this.hiddenGroups.filter((id) => id !== group.id)
        : [...this.hiddenGroups, group.id];
    },
  },
};
</script>
