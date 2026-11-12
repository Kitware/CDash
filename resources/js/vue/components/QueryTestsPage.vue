<template>
  <div>
    <template v-if="!loading">
      <div
        v-if="cdash.error"
        data-test="error-message"
      >
        {{ cdash.error }}
      </div>

      <h3
        v-if="!cdash.error"
        id="numtests"
        data-test="heading"
      >
        Query Tests: <span data-test="test-count">{{ tests.length }}</span> matches
      </h3>

      <div id="labelshowfilters">
        <a
          id="label_showfilters"
          class="cdash-link"
          data-test="show-filters-toggle"
          @click="toggleFilters"
        >
          {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
        </a>
        <a
          v-if="cdash.filterontestoutput"
          class="cdash-link"
          style="display: block;"
          data-test="matching-output-toggle"
          @click="showMatchingOutput = !showMatchingOutput"
        >
          {{ showMatchingOutput ? 'Hide Matching Output' : 'Show Matching Output' }}
        </a>
      </div>

      <!-- If the tests couldn't be loaded, the dates come from the URL, so the filters can still be fixed. -->
      <LegacyFilters
        :show="showFilters"
        :date="cdash.date ?? query.date ?? ''"
        :date-set="Boolean(cdash.date_set ?? query.date)"
        :initial-begin="cdash.begin ?? query.begin ?? ''"
        :initial-end="cdash.end ?? query.end ?? ''"
      />

      <table
        v-if="!cdash.error"
        id="queryTestsTable"
        cellspacing="0"
        cellpadding="3"
        class="tabb test-table"
        data-test="tests-table"
      >
        <thead>
          <tr class="table-heading1">
            <SortableHeader
              v-for="column in columns"
              :key="column.field"
              class="header"
              :title="column.tooltip"
              :data-test="`header-${column.name}`"
              :direction="direction(column.field)"
              @sort="(append) => sort(column.field, append)"
            >
              {{ column.title }}
            </SortableHeader>
            <th
              v-if="cdash.filterontestoutput"
              v-show="showMatchingOutput"
              data-test="header-matching-output"
            >
              Matching Output
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(test, index) in pageTests"
            :key="test.key"
            :class="index % 2 === 0 ? 'odd' : 'even'"
            data-test="test-row"
          >
            <td data-test="site">
              <a
                class="cdash-link"
                :href="test.siteLink"
              >{{ test.site }}</a>
            </td>
            <td data-test="build-name">
              <a
                class="cdash-link"
                :href="test.buildSummaryLink"
              >{{ test.buildName }}</a>
            </td>
            <td data-test="test-name">
              {{ test.testname }}
            </td>
            <td
              align="center"
              :class="test.statusclass"
              data-test="status"
            >
              <a
                class="cdash-link"
                :href="test.testDetailsLink"
              >{{ test.status }}</a>
            </td>
            <td
              v-if="showTestTime"
              align="center"
              :class="test.timestatusclass"
              data-test="time-status"
            >
              <a
                class="cdash-link"
                :href="test.testDetailsLink"
              >{{ test.timestatus }}</a>
            </td>
            <td data-test="time">
              {{ test.prettyTime }}
            </td>
            <td
              v-if="cdash.hasprocessors"
              data-test="proc-time"
            >
              {{ test.prettyProcTime }}
            </td>
            <td data-test="details">
              {{ test.details }}
            </td>
            <td data-test="labels">
              {{ test.labels }}
            </td>
            <td data-test="build-time">
              {{ test.buildstarttime }}
            </td>
            <td
              v-if="cdash.hasprocessors"
              data-test="processors"
            >
              {{ test.nprocs }}
            </td>
            <td
              v-for="(measurement, measurementIndex) in test.measurements"
              :key="measurementIndex"
              data-test="measurement"
            >
              {{ measurement }}
            </td>
            <td
              v-if="cdash.filterontestoutput"
              v-show="showMatchingOutput"
              data-test="matching-output"
            >
              <!-- eslint-disable-next-line vue/no-v-html -->
              <pre v-html="terminalHtml(test.matchingoutput)" />
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!cdash.error && tests.length > 25">
        <TablePagination
          v-if="pageCount > 1"
          v-model:page="page"
          :page-count="pageCount"
          data-test="pagination"
        />
        <div>
          <ItemsPerPage
            v-model="numPerPage"
            :options="[25, 50, 100]"
            :cookie="NUM_PER_PAGE_COOKIE"
          />
        </div>
      </div>

      <!-- The previous, latest and next days, or a parent build's previous, latest and next builds. -->
      <Teleport
        v-if="cdash.menu?.previous || cdash.menu?.current || cdash.menu?.next"
        to="#header nav.projectnav"
      >
        <HeaderNav
          :previous-url="menuUrl('previous')"
          :latest-url="menuUrl('current')"
          :next-url="menuUrl('next')"
        />
      </Teleport>
    </template>
  </div>
</template>

<script>
import { AnsiUp } from 'ansi_up';
import ApiLoader from './shared/ApiLoader';
import HeaderNav from './page-header/HeaderNav.vue';
import { queryParams } from './shared/legacy/legacyRequest';
import { filterString, showFiltersUrl } from './shared/legacy/filters/filterUrls';
import LegacyFilters from './shared/legacy/filters/LegacyFilters.vue';
import orderBy from './shared/legacy/sorting/angularOrderBy';
import { useSortFields } from './shared/legacy/sorting/useSortFields';
import SortableHeader from './shared/legacy/sorting/SortableHeader.vue';
import { pageCount, pageRows, savedNumPerPage } from './shared/legacy/pagination/paging';
import TablePagination from './shared/legacy/pagination/TablePagination.vue';
import ItemsPerPage from './shared/legacy/pagination/ItemsPerPage.vue';
import LegacyApi from './QueryTestsPage/legacy/LegacyApi';

const SORT_COOKIE = 'cdash_query_tests_sort';
const NUM_PER_PAGE_COOKIE = 'queryTests_num_per_page';

/**
 * The tests of a project's builds on a day or range of days (queryTests.php), or of a parent build's
 * children (queryTests.php?parentid=...), filtered with the legacy filters.
 *
 * Everything is loaded from the legacy queryTests API.  The code which depends on the legacy APIs is in
 * QueryTestsPage/legacy and shared/legacy.
 */
export default {
  name: 'QueryTestsPage',

  components: {
    HeaderNav,
    ItemsPerPage,
    LegacyFilters,
    SortableHeader,
    TablePagination,
  },

  setup() {
    // The order is remembered in a cookie.  The server's order is used until a column is sorted.
    const { sortFields, direction, sort } = useSortFields(SORT_COOKIE);
    return { sortFields, direction, setSortFields: sort, NUM_PER_PAGE_COOKIE };
  },

  data() {
    return {
      loading: true,
      cdash: {},
      tests: [],
      showFilters: false,
      showMatchingOutput: false,
      page: 1,
      numPerPage: savedNumPerPage(NUM_PER_PAGE_COOKIE),
    };
  },

  computed: {
    query() {
      return queryParams(window.location.search);
    },

    showTestTime() {
      return Number(this.cdash.project?.showtesttime) === 1;
    },

    /** The sortable columns. */
    columns() {
      const hasProcessors = Boolean(this.cdash.hasprocessors);
      const columns = [
        [true, 'site', 'site', 'Site'],
        [true, 'build-name', 'buildName', 'Build Name'],
        [true, 'test-name', 'testname', 'Test Name'],
        [true, 'status', 'status', 'Status'],
        [this.showTestTime, 'time-status', 'timestatus', 'Time Status'],
        [true, 'time', 'time', 'Time'],
        [hasProcessors, 'proc-time', 'procTime', 'Proc Time', 'Elapsed time * number of processors'],
        [true, 'details', 'details', 'Details'],
        [true, 'labels', 'labels', 'Labels'],
        [true, 'build-time', 'buildstarttime', 'Build Time'],
        [hasProcessors, 'processors', 'nprocs', 'Processors'],
      ]
        .filter(([shown]) => shown)
        .map(([, name, field, title, tooltip]) => ({ name, field, title, tooltip }));

      // The project's pinned measurements, apart from Processors.
      const measurements = (this.cdash.extrameasurements ?? []).map((title, index) => ({
        name: `measurement-${index}`,
        field: `measurements[${index}]`,
        title,
      }));

      return [...columns, ...measurements];
    },

    pageCount() {
      return pageCount(this.tests.length, this.numPerPage);
    },

    pageTests() {
      return pageRows(this.tests, this.page, this.numPerPage);
    },
  },

  watch: {
    pageCount(pageCount) {
      this.page = Math.min(this.page, pageCount);
    },
  },

  async mounted() {
    const start = new Date().getTime();
    const params = { ...this.query, filterstring: filterString(window.location.href) };
    try {
      this.cdash = await LegacyApi.queryTests(params);
    } catch (error) {
      this.cdash = { ...error.data, error: error.message };
    }
    this.cdash.endpoint = `${this.$baseURL}/api/v1/queryTests.php${window.location.search}`;
    // A test is listed twice if a filter selects two of its build's groups, so the rows need their own keys.
    const tests = (this.cdash.builds ?? []).map((test, key) => ({ ...test, key }));
    this.tests = orderBy(tests, this.sortFields);
    this.showFilters = Number(this.cdash.filterdata?.showfilters ?? this.query.showfilters) === 1;
    this.loading = false;

    await this.$nextTick();
    ApiLoader.showInFooter(this.cdash, start);
  },

  methods: {
    sort(field, append) {
      this.setSortFields(field, append);
      // Ties keep their previous order.
      this.tests = orderBy(this.tests, this.sortFields);
    },

    menuUrl(link) {
      const url = this.cdash.menu?.[link];
      return url ? `${url}${this.cdash.filterurl ?? ''}` : null;
    },

    toggleFilters() {
      this.showFilters = !this.showFilters;
      // If the URL says whether to show the filters, change it.
      const url = showFiltersUrl(window.location.href, this.showFilters);
      if (url !== null) {
        window.location.href = url;
      }
    },

    /**
     * Test output with its ANSI colors as HTML.  CTest replaces the escape character of the color codes,
     * which XML can't contain.  AnsiUp escapes the text it converts, so its output is safe to render.
     *
     * @param {string|undefined} output
     * @returns {string}
     */
    terminalHtml(output) {
      return new AnsiUp().ansi_to_html(String(output ?? '').replace(/\[NON-XML-CHAR-0x1B\]/g, '\x1B'));
    },
  },
};
</script>
