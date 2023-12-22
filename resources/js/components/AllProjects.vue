<template>
  <loading-indicator :is-loading="loading">
    <h1
      v-if="cdash.projects.length === 0"
      class="text-info text-center"
    >
      No Projects Found
    </h1>

    <DataTable
      :columns="[
        {
          name: 'project',
          displayName: 'Project',
        },
        {
          name: 'description',
          displayName: 'Description',
          expand: true,
        },
        {
          name: 'activity',
          displayName: 'Last Activity',
        }
      ]"
      :rows="tableRows"
      class="projects-table"
    >
      <template #activity="activity" >
        <a
          class="builddateelapsed"
          :href="activity.props.link + '&date=' + activity.props.lastbuilddate"
        >
          {{ activity.props.lastbuild_elapsed }}
        </a>
        <img
          alt="Activity level"
          style="margin-left: 0.5em;"
          :src="$baseURL + '/img/cleardot.gif'"
          :class="'activity-level-' + activity.props.activity"
        >
      </template>
    </DataTable>

    <table
      v-if="cdash.projects.length > 0"
      width="100%"
      cellspacing="0"
      cellpadding="0"
    >
      <tr>
        <td
          height="1"
          colspan="14"
          align="left"
          bgcolor="#888888"
        />
      </tr>
      <tr>
        <td
          height="1"
          colspan="14"
          align="right"
        >
          <div
            v-if="cdash.showoldtoggle"
            id="showold"
          >
            <a
              v-show="!cdash.allprojects"
              :href="$baseURL + '/projects?allprojects=1'"
            >
              Show all {{ cdash.nprojects }} projects
            </a>
            <a
              v-show="cdash.allprojects"
              :href="$baseURL + '/projects'"
            >
              Hide old projects
            </a>
          </div>
        </td>
      </tr>
    </table>
  </loading-indicator>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from "./shared/LoadingIndicator.vue";
import DataTable from "./shared/DataTable.vue";
export default {
  name: 'AllProjects',
  components: {DataTable, LoadingIndicator},

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
      tableRows: [],
    }
  },

  mounted () {
    let queryParams = '';
    const allprojects = (new URL(location.href)).searchParams.get('allprojects');
    if (allprojects) {
      queryParams = '?allprojects=1';
    }
    ApiLoader.loadPageData(this, '/api/v1/viewProjects.php' + queryParams);
  },

  methods: {
    postSetup: function () {
      this.tableRows = this.cdash.projects.map((project) => {
        return {
          project: {
            value: project.name,
            href: project.link
          },
          description: project.description,
          activity: project,
        };
      });
    }
  }
}
</script>

<style scoped>

.projects-table {
  width: 100%;
}

</style>
