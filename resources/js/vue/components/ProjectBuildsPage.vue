<template>
  <div>
    <template v-if="!loading">
      <div
        v-if="cdash.error"
        data-test="error-message"
      >
        {{ cdash.error }}
      </div>

      <div id="index_top">
        <div
          v-for="(banner, index) in cdash.banners ?? []"
          :key="index"
          name="banner"
          class="h3 text-center bg-info"
          data-test="banner"
        >
          {{ banner }}
        </div>

        <SettingsMenu
          :advanced-view="advancedView"
          :auto-refresh="autoRefresh"
          :colorblind="colorblind"
          :show-filters="showFilters"
          @toggle-advanced-view="toggleAdvancedView"
          @toggle-auto-refresh="toggleAutoRefresh"
          @toggle-colorblind="toggleColorblind"
          @toggle-filters="toggleFilters"
        />

        <LegacyFilters
          :show="showFilters"
          :page-id="filterPageId"
          :date="cdash.date ?? ''"
          :date-set="Boolean(cdash.date_set)"
          :initial-begin="cdash.begin ?? ''"
          :initial-end="cdash.end ?? ''"
        />
      </div>

      <!-- The timeline is shown when viewing a single build group. -->
      <div
        v-if="buildGroupName !== null && cdash.filterdata"
        class="container"
        style="margin-top: 30px; margin-bottom: 20px;"
      >
        <TimelineChart
          data-test="timeline"
          :project-name="cdash.projectname"
          :build-group="buildGroupName"
          :filterdata="cdash.filterdata"
          :begin="cdash.begin ?? null"
          :end="cdash.end ?? null"
          :date="cdash.date ?? null"
          @update="viewDates"
        />
      </div>

      <table
        v-if="cdash.testingdataurl"
        width="100%"
        cellpadding="11"
        cellspacing="0"
      >
        <tbody>
          <tr>
            <td
              height="25"
              align="left"
            >
              Testing data for this project can be found at:
              <a
                class="cdash-link"
                data-test="testing-data-url"
                :href="cdash.testingdataurl"
              >
                {{ cdash.testingdataurl }}
              </a>
            </td>
          </tr>
        </tbody>
      </table>

      <ParentBuildInfo
        v-if="settings.childView"
        :cdash="cdash"
        :advanced-view="advancedView"
      />

      <SubProjectTable
        v-if="cdash.subproject?.dependencies?.length > 0"
        title="SubProject Dependencies"
        :subprojects="cdash.subproject.dependencies"
        :project-name-encoded="cdash.projectname_encoded"
        :date="cdash.date"
        :show-last-submission="Boolean(cdash.showlastsubmission)"
        :sort-cookie="`cdash_${cdash.projectname}_subproject_sort`"
      />

      <BuildGroupTable
        v-for="group in buildGroups"
        :key="group.id"
        class="buildgroup"
        :data-test="`build-group-${group.name}`"
        :group="group"
        :settings="settings"
        :initial-sort="initialSort(group)"
        :sort-cookie="sortCookie(group)"
        :initial-num-per-page="numBuildsPerPage"
      />

      <CoverageTable
        v-if="cdash.coverages?.length > 0 || cdash.coveragegroups?.length > 0"
        class="buildgroup"
        data-test="coverage"
        :coverages="cdash.coverages ?? []"
        :coverage-groups="cdash.coveragegroups"
        :thresholds="{ thresholdgreen: cdash.thresholdgreen, thresholdyellow: cdash.thresholdyellow }"
        :child-view="settings.childView"
        :display-labels="Number(cdash.displaylabels) === 1"
        :project-id="cdash.projectid"
        :unix-timestamp="cdash.unixtimestamp"
        :sort-cookie="`cdash_${cdash.projectname}_coverage_sort`"
      />

      <DynamicAnalysisTable
        v-if="cdash.dynamicanalyses?.length > 0"
        class="buildgroup"
        data-test="dynamic-analysis"
        :analyses="cdash.dynamicanalyses"
        :child-view="settings.childView"
        :display-labels="Number(cdash.displaylabels) === 1"
        :project-id="cdash.projectid"
        :unix-timestamp="cdash.unixtimestamp"
        :sort-cookie="`cdash_${cdash.projectname}_DA_sort`"
      />

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
import ApiLoader from './shared/ApiLoader';
import HeaderNav from './page-header/HeaderNav.vue';
import { queryParams } from './shared/legacy/legacyRequest';
import { getCookie, removeCookie, setCookie } from './shared/legacy/cookies';
import { filterString, showFiltersUrl } from './shared/legacy/filters/filterUrls';
import LegacyFilters from './shared/legacy/filters/LegacyFilters.vue';
import orderBy from './shared/legacy/sorting/angularOrderBy';
import { savedNumPerPage } from './shared/legacy/pagination/paging';
import TimelineChart from './shared/legacy/timeline/TimelineChart.vue';
import LegacyApi from './ProjectBuildsPage/legacy/LegacyApi';
import { buildSortCookieName, initialBuildSort } from './ProjectBuildsPage/legacy/buildSorting';
import BuildGroupTable from './ProjectBuildsPage/BuildGroupTable.vue';
import CoverageTable from './ProjectBuildsPage/CoverageTable.vue';
import DynamicAnalysisTable from './ProjectBuildsPage/DynamicAnalysisTable.vue';
import ParentBuildInfo from './ProjectBuildsPage/ParentBuildInfo.vue';
import SettingsMenu from './ProjectBuildsPage/SettingsMenu.vue';
import SubProjectTable from './ProjectBuildsPage/SubProjectTable.vue';

const AUTO_REFRESH_COOKIE = 'cdash_refresh';

/**
 * The builds submitted to a project on a day (index.php), the builds in one of its build groups with a
 * timeline (viewBuildGroup.php), or a parent build's children (index.php?parentid=...).
 *
 * Everything is loaded from the legacy index API.  The code which depends on the legacy APIs, or on
 * the sort orders and settings saved in users' cookies, is in ProjectBuildsPage/legacy and, where
 * other pages can use it too, shared/legacy.
 */
export default {
  name: 'ProjectBuildsPage',

  components: {
    BuildGroupTable,
    CoverageTable,
    DynamicAnalysisTable,
    HeaderNav,
    LegacyFilters,
    ParentBuildInfo,
    SettingsMenu,
    SubProjectTable,
    TimelineChart,
  },

  data() {
    return {
      loading: true,
      cdash: {},
      showFilters: false,
      autoRefresh: getCookie(AUTO_REFRESH_COOKIE) === 'true',
    };
  },

  computed: {
    query() {
      return queryParams(window.location.search);
    },

    /** A parent build's children can be filtered on different fields. */
    filterPageId() {
      return window.location.pathname.endsWith('/index.php') && 'parentid' in this.query ? 'indexchildren.php' : null;
    },

    /** The build group being viewed by itself, if any. */
    buildGroupName() {
      return this.query.buildgroup ?? null;
    },

    advancedView() {
      return Number(getCookie(`cdash_${this.cdash.projectname}_advancedview`)) === 1;
    },

    colorblind() {
      return Boolean(Number(this.cdash.filterdata?.colorblind));
    },

    numBuildsPerPage() {
      return savedNumPerPage('num_builds_per_page');
    },

    buildGroups() {
      return orderBy(this.cdash.buildgroups ?? [], 'position');
    },

    /**
     * The settings which the build groups' tables share.
     */
    settings() {
      const cdash = this.cdash;
      const childView = Number(cdash.childview) === 1;
      const showStartTime = Boolean(cdash.showstarttime);
      // The steps' durations aren't shown for builds which built all their SubProjects at once, since
      // they are the same for every SubProject.  Those builds don't have start times.
      const showTimeColumns = this.advancedView && showStartTime;
      const showTestTime = Boolean(Number(cdash.showtesttime)) || showTimeColumns;
      const showProcTime = this.advancedView && Boolean(cdash.showProcTime);
      const extraTestColumns = (showTestTime ? 1 : 0) + (showProcTime ? 1 : 0);
      return {
        projectId: cdash.projectid,
        projectName: cdash.projectname,
        projectNameEncoded: cdash.projectname_encoded,
        date: cdash.date,
        filterUrl: cdash.filterurl ?? '',
        unixTimestamp: cdash.unixtimestamp,
        subprojectTestsQuery: cdash.subprojecttestsquery || '',
        childView,
        admin: Boolean(Number(cdash.user?.admin)),
        allBuildGroups: cdash.all_buildgroups ?? [],
        updateType: cdash.updatetype,
        showTimeColumns,
        showTestTime,
        showProcTime,
        showStartTime,
        showOrder: Boolean(cdash.showorder),
        showLabels: !childView && Number(cdash.displaylabels) === 1,
        extraTestColumns,
        numColumns: 14 + extraTestColumns + (Number(cdash.displaylabels) || 0),
      };
    },
  },

  async mounted() {
    if (this.autoRefresh) {
      setTimeout(() => window.location.reload(), 5000);
    }

    const start = new Date().getTime();
    const params = { ...this.query, filterstring: filterString(window.location.href) };
    try {
      this.cdash = await LegacyApi.index(params);
    } catch (error) {
      this.cdash = error.data ?? {};
    }
    if ('redirect' in this.cdash) {
      window.location = this.cdash.redirect;
      return;
    }
    this.cdash.endpoint = `${this.$baseURL}/api/v1/index.php${window.location.search}`;
    this.showFilters = Number(this.cdash.filterdata?.showfilters) === 1;
    this.loading = false;

    await this.$nextTick();
    ApiLoader.showInFooter(this.cdash, start);
    this.scrollToAnchor();
  },

  methods: {
    initialSort(group) {
      return initialBuildSort({
        group,
        childView: this.settings.childView,
        urlSort: this.query.sort ?? null,
        cookie: getCookie(this.sortCookie(group)),
      });
    },

    sortCookie(group) {
      return buildSortCookieName(this.cdash.projectname, this.settings.childView, group.name);
    },

    menuUrl(link) {
      const url = this.cdash.menu?.[link];
      return url ? `${url}${this.cdash.filterurl ?? ''}` : null;
    },

    /**
     * The browser can't scroll to an anchor in the URL until the page has been rendered.  Bookmarked
     * links may put "##" or "#!#" before the anchor.
     */
    scrollToAnchor() {
      const anchor = window.location.hash.replace(/^[#!]+/, '');
      if (anchor !== '') {
        document.getElementById(anchor)?.scrollIntoView();
      }
    },

    toggleAdvancedView() {
      setCookie(`cdash_${this.cdash.projectname}_advancedview`, this.advancedView ? 0 : 1);
      window.location.reload();
    },

    toggleAutoRefresh() {
      if (this.autoRefresh) {
        removeCookie(AUTO_REFRESH_COOKIE);
      } else {
        setCookie(AUTO_REFRESH_COOKIE, 'true');
      }
      window.location.reload();
    },

    toggleColorblind() {
      // The server chooses the stylesheet.
      setCookie('colorblind', this.colorblind ? 0 : 1, { expires: 365 });
      window.location.reload();
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
     * View the build group on the days selected in the timeline.
     *
     * @param {{begin: string, end: string}} dates
     */
    viewDates({ begin, end }) {
      let url = `//${window.location.host}${window.location.pathname}?project=${encodeURIComponent(this.cdash.projectname)}`
        + `&buildgroup=${encodeURIComponent(this.buildGroupName)}`;
      url += begin === end ? `&date=${begin}` : `&begin=${begin}&end=${end}`;
      window.location = url;
    },
  },
};
</script>
