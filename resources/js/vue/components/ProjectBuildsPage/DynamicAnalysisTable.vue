<template>
  <div>
    <a
      id="DynamicAnalysis"
      class="cdash-link"
    />
    <table
      id="dynamicanalysistable"
      border="0"
      cellpadding="4"
      cellspacing="0"
      width="100%"
      class="tabb"
    >
      <thead>
        <tr class="table-heading3">
          <td
            colspan="1"
            class="nob"
          >
            <h3>
              <a
                href="#DynamicAnalysis"
                class="grouptrigger cdash-link"
              >
                Dynamic Analysis
              </a>
            </h3>
          </td>
          <td
            colspan="5"
            align="right"
            class="nob"
          />
        </tr>

        <tr class="table-heading">
          <SortableHeader
            v-if="childView"
            class="column-header"
            data-test="header-subproject"
            :direction="direction('label')"
            @sort="(append) => sort('label', append)"
          >
            SubProject
          </SortableHeader>
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
            data-test="header-checker"
            :direction="direction('checker')"
            @sort="(append) => sort('checker', append)"
          >
            Checker
          </SortableHeader>
          <SortableHeader
            class="column-header"
            data-test="header-defect-count"
            :direction="direction('defectcount')"
            @sort="(append) => sort('defectcount', append)"
          >
            Defect Count
          </SortableHeader>
          <SortableHeader
            class="column-header nob"
            data-test="header-date"
            :direction="direction('datefull')"
            @sort="(append) => sort('datefull', append)"
          >
            Date
          </SortableHeader>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="(analysis, index) in sortedAnalyses"
          :key="rowKey(analysis)"
          :class="index % 2 === 1 ? 'odd' : 'even'"
          data-test="dynamic-analysis-row"
        >
          <td
            v-if="childView"
            class="paddt"
            align="left"
            data-test="subproject"
          >
            {{ analysis.label }}
          </td>
          <template v-else>
            <td
              class="paddt"
              align="left"
              data-test="site"
            >
              <a
                class="cdash-link"
                :href="`sites/${analysis.siteid}?project=${projectId}&currenttime=${unixTimestamp}`"
              >
                {{ analysis.site }}
              </a>
            </td>
            <td
              class="paddt"
              align="left"
              data-test="build-name"
            >
              <a
                class="cdash-link"
                :href="`builds/${analysis.buildid}`"
              >
                {{ analysis.buildname }}
              </a>
            </td>
          </template>
          <td
            class="center-text paddt"
            data-test="checker"
          >
            {{ analysis.checker }}
          </td>
          <td
            class="paddt"
            align="center"
            data-test="defect-count"
            :class="{ error: analysis.defectcount > 0, normal: analysis.defectcount < 1 }"
          >
            <!-- Parent builds without dynamic analysis of their own link to their children's. -->
            <a
              class="cdash-link"
              :href="analysis.childlink ?? `builds/${analysis.buildid}/dynamic_analysis`"
            >
              {{ analysis.defectcount }}
            </a>
          </td>
          <td
            class="paddt"
            align="center"
            data-test="date"
            :class="{ nob: !displayLabels }"
          >
            <span
              class="builddateelapsed"
              :title="analysis.date"
            >
              {{ analysis.dateelapsed }}
            </span>
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
import { rowKey } from './rowKey';

/**
 * The number of defects found by each build's dynamic analysis, from the legacy index API.
 */
export default {
  name: 'DynamicAnalysisTable',

  components: {
    SortableHeader,
  },

  props: {
    analyses: {
      type: Array,
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

  computed: {
    sortedAnalyses() {
      return orderBy(this.analyses, this.sortFields);
    },
  },

  methods: {
    rowKey,
  },
};
</script>
