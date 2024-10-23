<template>
  <nav id="headermenu">
    <ul id="navigation">
      <li v-if="hasProject">
        <a
          class="cdash-link"
          :href="indexUrl"
        >Dashboard</a>
        <ul>
          <li v-if="showSubProjects">
            <a
              class="cdash-link"
              :href="subProjectsUrl"
            >SubProjects</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="overviewUrl"
            >Overview</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="buildsUrl"
            >Builds</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="testsUrl"
            >Tests</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="testQueryUrl"
            >Tests Query</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="statisticsUrl"
            >Statistics</a>
          </li>
          <li class="endsubmenu">
            <a
              class="cdash-link"
              :href="sitesUrl"
            >Sites</a>
          </li>
        </ul>
      </li>
      <li
        v-if="showBack"
        id="Back"
      >
        <a
          class="cdash-link"
          :href="backUrl"
        >Up</a>
      </li>
      <li v-if="showCalendar">
        <a
          id="cal"
          href=""
          :click="toggleCalendar()"
        >Calendar</a>
        <span
          id="date_now"
          style="display:none;"
        >{{ date }}</span>
      </li>
      <li v-if="hasProject">
        <a
          id="project_nav"
          href="#"
        >Project</a>
        <ul>
          <li>
            <a
              class="cdash-link"
              :href="homeUrl"
            >Home</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="docUrl"
            >Documentation</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="vcsUrl"
            >Repository</a>
          </li>
          <li :class="{ endsubmenu: !showSubscribe }">
            <a
              class="cdash-link"
              :href="bugUrl"
            >Bug Tracker</a>
          </li>
          <li
            v-if="showSubscribe"
            class="endsubmenu"
          >
            <a
              class="cdash-link"
              :href="subscribeUrl"
            >Subscribe</a>
          </li>
        </ul>
      </li>
      <li
        v-if="showAdmin"
        id="admin"
      >
        <a
          class="cdash-link"
          href="#"
        >Settings</a>
        <ul>
          <li>
            <a
              class="cdash-link"
              :href="projectSettingsUrl"
            >Project</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="userSettingsUrl"
            >Users</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="groupSettingsUrl"
            >Groups</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="coverageSettingsUrl"
            >Coverage</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="bannerSettingsUrl"
            >Banner</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="measurementSettingsUrl"
            >Measurements</a>
          </li>
          <li>
            <a
              class="cdash-link"
              :href="subProjectSettingsUrl"
            >SubProjects</a>
          </li>
          <li class="endsubmenu">
            <a
              class="cdash-link"
              :href="overviewSettingsUrl"
            >Overview</a>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
</template>

<script>
import ApiLoader from '../shared/ApiLoader';
export default {
  name: 'HeaderMenu',
  props: {
    date: {
      type: String,
      default: '',
    },
  },

  data () {
    return {
      hasProject: false,
      showAdmin: false,
      showBack: false,
      showCalendar: false,
      showSubscribe: false,
      showSubProjects: false,

      backUrl: '',
      bannerSettingsUrl: '',
      bugUrl: '',
      buildsUrl: '',
      coverageSettingsUrl: '',
      docUrl: '',
      groupSettingsUrl: '',
      homeUrl: '',
      indexUrl: '',
      measurementSettingsUrl: '',
      overviewSettingsUrl: '',
      overviewUrl: '',
      projectSettingsUrl: '',
      sitesUrl: '',
      statisticsUrl: '',
      subProjectSettingsUrl: '',
      subProjectsUrl: '',
      subscribeUrl: '',
      testQueryUrl: '',
      testsUrl: '',
      userSettingsUrl: '',
      vcsUrl: '',
    };
  },

  mounted() {
    ApiLoader.$on('api-loaded', cdash => {
      this.handleApiResponse(cdash);
    });
  },

  methods: {
    handleApiResponse: function (cdash) {
      let extraurl = '';
      if (cdash.extraurl) {
        extraurl = cdash.extraurl;
      }
      let extrafilterurl = '';
      if (cdash.extrafilterurl) {
        extrafilterurl = cdash.extrafilterurl;
        cdash.querytestfilters = extrafilterurl;
      }

      if (cdash.menu && cdash.menu.back) {
        this.showBack = true;
        this.backUrl = `${this.$baseURL}/${cdash.menu.back}${extrafilterurl}`;
      }
      if (cdash.showcalendar) {
        this.showCalendar = true;
      }

      if (!cdash.projectname_encoded) {
        return;
      }
      this.hasProject = true;

      this.indexUrl = `${this.$baseURL}/index.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      if (cdash.menu && cdash.menu.subprojects) {
        this.showSubProjects = true;
        this.subProjectsUrl = `${this.$baseURL}/viewSubProjects.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      }

      this.overviewUrl = `${this.$baseURL}/overview.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      this.buildsUrl = `${this.$baseURL}/buildOverview.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}`;
      this.testsUrl = `${this.$baseURL}/testOverview.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}`;

      if (cdash.parentid > 0) {
        this.testQueryUrl = `${this.$baseURL}/queryTests.php?project=${cdash.projectname_encoded}&parentid=${cdash.parentid}${extraurl}${cdash.querytestfilters}`;
      }
      else {
        this.testQueryUrl = `${this.$baseURL}/queryTests.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}${cdash.querytestfilters}`;
      }

      this.statisticsUrl = `${this.$baseURL}/userStatistics.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      this.sitesUrl = `${this.$baseURL}/viewMap.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}`;

      if (cdash.home.startsWith('index.php?project=')) {
        this.homeUrl = `${this.$baseURL}/${cdash.home}`;
      }
      else {
        this.homeUrl = cdash.home;
      }
      this.docUrl = cdash.documentation;
      this.vcsUrl = cdash.vcs;
      this.bugUrl = cdash.bugtracker;
      if (!cdash.projectrole) {
        this.showSubscribe = true;
        this.subscribeUrl = `${this.$baseURL}/subscribeProject.php?projectid=${cdash.projectid}`;
      }

      // eslint-disable-next-line eqeqeq
      if (cdash.user.admin == 1) {
        this.showAdmin = true;
        this.projectSettingsUrl = `${this.$baseURL}/project/${cdash.projectid}/edit`;
        this.userSettingsUrl = `${this.$baseURL}/manageProjectRoles.php?projectid=${cdash.projectid}`;
        this.groupSettingsUrl = `${this.$baseURL}/manageBuildGroup.php?projectid=${cdash.projectid}`;
        this.coverageSettingsUrl = `${this.$baseURL}/manageCoverage.php?projectid=${cdash.projectid}`;
        this.bannerSettingsUrl = `${this.$baseURL}/manageBanner.php?projectid=${cdash.projectid}`;
        this.measurementSettingsUrl = `${this.$baseURL}/project/${cdash.projectid}/testmeasurements`;
        this.subProjectSettingsUrl = `${this.$baseURL}/manageSubProject.php?projectid=${cdash.projectid}`;
        this.overviewSettingsUrl = `${this.$baseURL}/manageOverview.php?projectid=${cdash.projectid}`;
      }
    },
  },
};
</script>

<style scoped>
    nav {
        display: block;
        float: right;
        height: 100%;
    }
</style>
