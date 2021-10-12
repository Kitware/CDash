<template>
  <nav id="headermenu">
    <ul id="navigation">
      <li v-if="hasProject">
        <a :href="indexUrl">Dashboard</a>
        <ul>
          <li v-if="showSubProjects">
            <a :href="subProjectsUrl">SubProjects</a>
          </li>
          <li>
            <a :href="overviewUrl">Overview</a>
          </li>
          <li>
            <a :href="buildsUrl">Builds</a>
          </li>
          <li>
            <a :href="testsUrl">Tests</a>
          </li>
          <li>
            <a :href="testQueryUrl">Tests Query</a>
          </li>
          <li>
            <a :href="statisticsUrl">Statistics</a>
          </li>
          <li class="endsubmenu">
            <a :href="sitesUrl">Sites</a>
          </li>
        </ul>
      </li>
      <li
        v-if="showBack"
        id="Back"
        v-tooltip.bottom="{
          content: 'Go back up one level in the hierarchy of results',
          delay: 1500,
          placement: 'bottom'
        }"
      >
        <a :href="backUrl">Up</a>
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
            <a :href="homeUrl">Home</a>
          </li>
          <li>
            <a :href="docUrl">Documentation</a>
          </li>
          <li>
            <a :href="vcsUrl">Repository</a>
          </li>
          <li :class="{ endsubmenu: !showSubscribe }">
            <a :href="bugUrl">Bug Tracker</a>
          </li>
          <li
            v-if="showSubscribe"
            class="endsubmenu"
          >
            <a :href="subscribeUrl">Subscribe</a>
          </li>
        </ul>
      </li>
      <li
        v-if="showAdmin"
        id="admin"
      >
        <a href="#">Settings</a>
        <ul>
          <li>
            <a :href="projectSettingsUrl">Project</a>
          </li>
          <li>
            <a :href="userSettingsUrl">Users</a>
          </li>
          <li>
            <a :href="groupSettingsUrl">Groups</a>
          </li>
          <li>
            <a :href="coverageSettingsUrl">Coverage</a>
          </li>
          <li>
            <a :href="bannerSettingsUrl">Banner</a>
          </li>
          <li>
            <a :href="measurementSettingsUrl">Measurements</a>
          </li>
          <li>
            <a :href="subProjectSettingsUrl">SubProjects</a>
          </li>
          <li class="endsubmenu">
            <a :href="overviewSettingsUrl">Overview</a>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
</template>

<script>
export default {
  name: "HeaderMenu",
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
    }
  },

  mounted() {
    this.$root.$on('api-loaded', cdash => {
      var extraurl = '';
      if (cdash.extraurl) {
        extraurl = cdash.extraurl;
      }
      var extrafilterurl = '';
      if (cdash.extrafilterurl) {
        extrafilterurl = cdash.extrafilterurl;
      }

      if (cdash.menu.back) {
        this.showBack = true;
        this.backUrl = `${this.$baseURL}${cdash.menu.back}${extrafilterurl}`;
      }
      if (cdash.showcalendar) {
        this.showCalendar = true;
      }

      if (!cdash.projectname_encoded) {
        return;
      }
      this.hasProject = true;

      this.indexUrl = `${this.$baseURL}/index.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      if (cdash.menu.subprojects == 1) {
        this.showSubProjects = true;
        this.subProjectsUrl = `${this.$baseURL}/viewSubProjects.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      }

      this.overviewUrl = `${this.$baseURL}/overview.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      this.buildsUrl = `${this.$baseURL}/buildOverview.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}`;
      this.testsUrl = `${this.$baseURL}/testOverview.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}`;

      if (cdash.parentid > 0) {
        this.testQueryUrl = `${this.$baseURL}/queryTests.php?project=${cdash.projectname_encoded}&parentid=${cdash.parentid}${extraurl}${extrafilterurl}`;
      } else {
        this.testQueryUrl = `${this.$baseURL}/queryTests.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}${extrafilterurl}`;
      }

      this.statisticsUrl = `${this.$baseURL}/userStatistics.php?project=${cdash.projectname_encoded}&date=${cdash.date}`;
      this.sitesUrl = `${this.$baseURL}/viewMap.php?project=${cdash.projectname_encoded}&date=${cdash.date}${extraurl}`;

      if (cdash.home.startsWith('index.php?project=')) {
        this.homeUrl = `${this.$baseURL}/${cdash.home}`;
      } else {
        this.homeUrl = cdash.home;
      }
      this.docUrl = cdash.documentation;
      this.vcsUrl = cdash.vcs;
      this.bugUrl = cdash.bugtracker;
      if (!cdash.projectrole) {
        this.showSubscribe = true;
        this.subscribeUrl = `${this.$baseURL}/subscribeProject.php?projectid=${cdash.projectid}`;
      }

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
    });
  },

}
</script>

<style scoped>
    nav {
        display: block;
        float: right;
        height: 100%;
        order: 4;
    }
</style>
