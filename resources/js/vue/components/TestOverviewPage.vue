<template>
  <div>
    <template v-if="!loading">
      <div
        v-if="cdash.error"
        data-test="error-message"
      >
        {{ cdash.error }}
      </div>
      <div
        v-else
        class="container"
      >
        <TimelineChart
          data-test="timeline"
          :project-name="cdash.projectname"
          :filterdata="cdash.filterdata"
          :begin="cdash.begin ?? null"
          :end="cdash.end ?? null"
          :date="cdash.date ?? null"
          @update="updateSelection"
        />
      </div>

      <div class="pad4">
        <div id="labelshowfilters">
          <a
            id="label_showfilters"
            class="cdash-link"
            data-test="show-filters-toggle"
            @click="toggleFilters"
          >
            {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
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

        <template v-if="!cdash.error">
          <!-- The selection is applied by the timeline's Update button. -->
          <div class="form-inline pull-right">
            <select
              v-model="selectedGroupId"
              class="form-group"
              data-test="group-select"
            >
              <option
                v-for="group in groups"
                :key="group.id"
                :value="group.id"
              >
                {{ group.name }}
              </option>
            </select>
            <div class="checkbox">
              <label>
                <input
                  v-model="showPassed"
                  type="checkbox"
                  name="showpassed"
                  data-test="show-passed"
                >
                Include passing tests
              </label>
            </div>
          </div>

          <span data-test="test-summary">
            {{ summary }}
          </span>
        </template>
      </div>

      <template v-if="!cdash.error && tests.length > 0">
        <table
          width="100%"
          class="tabb"
          cellspacing="0"
          data-test="tests-table"
        >
          <thead>
            <tr class="table-heading">
              <SortableHeader
                v-for="column in columns"
                :key="column.field"
                class="header text-center"
                :title="column.tooltip"
                :data-test="`header-${column.name}`"
                :direction="direction(column.field)"
                @sort="(append) => sort(column.field, append)"
              >
                {{ column.title }}
              </SortableHeader>
            </tr>
          </thead>

          <tbody class="test-table">
            <tr
              v-for="(test, index) in pageTests"
              :key="test.link"
              :class="index % 2 === 0 ? 'odd' : 'even'"
              data-test="test-row"
            >
              <td
                v-if="cdash.hassubprojects"
                data-test="subproject"
              >
                {{ test.subproject }}
              </td>
              <td data-test="test-name">
                <a
                  class="cdash-link"
                  :href="test.link"
                >{{ test.name }}</a>
              </td>
              <td
                class="text-center"
                data-test="failure"
              >
                {{ test.failpercent }}%
              </td>
              <td
                class="text-center"
                data-test="timeout"
              >
                {{ test.timeoutpercent }}%
              </td>
              <td
                class="text-center"
                data-test="total-runs"
              >
                {{ test.totalruns }}
              </td>
              <td
                class="text-center"
                data-test="time"
              >
                {{ test.prettytime }}
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="tests.length > 10">
          <TablePagination
            v-if="pageCount > 1"
            v-model:page="page"
            :page-count="pageCount"
            data-test="pagination"
          />
          <div>
            <ItemsPerPage
              v-model="numPerPage"
              :options="[10, 50, 100]"
              :cookie="NUM_PER_PAGE_COOKIE"
            />
          </div>
        </div>
      </template>

      <!-- The previous, latest and next days. -->
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
import ApiLoader from './shared/ApiLoader';
import HeaderNav from './page-header/HeaderNav.vue';
import { queryParams } from './shared/legacy/legacyRequest';
import { getCookie } from './shared/legacy/cookies';
import { filterString, showFiltersUrl } from './shared/legacy/filters/filterUrls';
import LegacyFilters from './shared/legacy/filters/LegacyFilters.vue';
import orderBy from './shared/legacy/sorting/angularOrderBy';
import { initialSortFields } from './shared/legacy/sorting/sortFields';
import { useSortFields } from './shared/legacy/sorting/useSortFields';
import SortableHeader from './shared/legacy/sorting/SortableHeader.vue';
import { pageCount, pageRows, savedNumPerPage } from './shared/legacy/pagination/paging';
import TablePagination from './shared/legacy/pagination/TablePagination.vue';
import ItemsPerPage from './shared/legacy/pagination/ItemsPerPage.vue';
import TimelineChart from './shared/legacy/timeline/TimelineChart.vue';
import LegacyApi from './TestOverviewPage/legacy/LegacyApi';

const SORT_COOKIE = 'cdash_test_overview_sort';
const NUM_PER_PAGE_COOKIE = 'testOverview_num_per_page';

/**
 * How often each of a project's tests failed or timed out during a day or range of days
 * (testOverview.php), optionally including the tests which passed, in one build group or all the
 * non-Experimental builds, filtered with the legacy filters.
 */
export default {
  name: 'TestOverviewPage',

  components: {
    HeaderNav,
    ItemsPerPage,
    LegacyFilters,
    SortableHeader,
    TablePagination,
    TimelineChart,
  },

  setup() {
    // The order is given in the URL, or remembered in a cookie.  The tests which fail most often are
    // listed first by default.
    const { sortFields, direction, sort } = useSortFields(SORT_COOKIE, initialSortFields({
      urlSort: queryParams(window.location.search).sort,
      cookie: getCookie(SORT_COOKIE),
      defaultSort: ['-failpercent'],
    }));
    return { sortFields, direction, setSortFields: sort, NUM_PER_PAGE_COOKIE };
  },

  data() {
    return {
      loading: true,
      cdash: {},
      tests: [],
      showFilters: false,
      selectedGroupId: 0,
      showPassed: false,
      page: 1,
      numPerPage: savedNumPerPage(NUM_PER_PAGE_COOKIE),
    };
  },

  computed: {
    query() {
      return queryParams(window.location.search);
    },

    /** "Non-Experimental Builds", followed by the project's build groups. */
    groups() {
      return orderBy(this.cdash.groups ?? [], 'position');
    },

    summary() {
      const count = this.tests.length;
      const tests = count === 0 ? 'No tests' : `${count} ${count === 1 ? 'test' : 'tests'}`;
      return `${tests} ${Number(this.cdash.showpassed) === 1 ? 'ran' : 'failed'} during this time period.`;
    },

    /** The sortable columns. */
    columns() {
      return [
        [Boolean(this.cdash.hassubprojects), 'subproject', 'subproject', 'SubProject'],
        [true, 'test-name', 'name', 'Test Name'],
        [true, 'failure', 'failpercent', 'Failure'],
        [true, 'timeout', 'timeoutpercent', 'Timeout'],
        [true, 'total-runs', 'totalruns', 'Total Runs'],
        [true, 'time', 'time', 'Time', 'Maximum amount of time that this test took to run'],
      ]
        .filter(([shown]) => shown)
        .map(([, name, field, title, tooltip]) => ({ name, field, title, tooltip }));
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
      this.cdash = await LegacyApi.testOverview(params);
    } catch (error) {
      this.cdash = { ...error.data, error: error.message };
    }
    this.cdash.endpoint = `${this.$baseURL}/api/v1/testOverview.php${window.location.search}`;
    this.tests = orderBy(this.cdash.tests ?? [], this.sortFields);
    this.showFilters = Number(this.cdash.filterdata?.showfilters ?? this.query.showfilters) === 1;
    this.showPassed = Number(this.cdash.showpassed) === 1;
    // An unknown group is shown as the first one.
    const groupId = Number(this.cdash.groupid);
    this.selectedGroupId = this.groups.some((group) => group.id === groupId) ? groupId : (this.groups[0]?.id ?? 0);
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
     * View the days selected in the timeline, with the selected build group and whether to include the
     * passing tests.
     *
     * @param {{begin: string, end: string}} dates
     */
    updateSelection({ begin, end }) {
      let url = `//${window.location.host}${window.location.pathname}?project=${this.cdash.projectname_encoded}`;
      url += begin === end ? `&date=${begin}` : `&begin=${begin}&end=${end}`;
      if (this.selectedGroupId > 0) {
        url += `&group=${this.selectedGroupId}`;
      }
      if (this.showPassed) {
        url += '&showpassed=1';
      }
      url += filterString(window.location.href);
      window.location = url;
    },
  },
};
</script>
