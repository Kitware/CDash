<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else-if="!loading">
    <h1
      v-if="cdash.projects.length === 0"
      class="text-info text-center"
    >
      No Projects Found
    </h1>

    <!-- Main table -->
    <table
      v-if="cdash.projects.length > 0"
      id="indexTable"
      border="0"
      cellpadding="4"
      cellspacing="0"
      width="100%"
      class="tabb striped"
    >
      <thead>
        <tr class="table-heading1">
          <td
            colspan="6"
            align="left"
            class="nob"
          >
            <h3>Dashboards</h3>
          </td>
        </tr>

        <tr class="table-heading">
          <th
            id="sort_0"
            align="center"
            width="10%"
          >
            <b>Project</b>
          </th>
          <td
            align="center"
            width="65%"
          >
            <b>Description</b>
          </td>
          <th
            id="sort_2"
            align="center"
            class="nob"
            width="13%"
          >
            <b>Last activity</b>
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="project in cdash.projects"
        >
          <td align="center">
            <a :href="project.link">
              {{ project.name }}
            </a>
          </td>
          <td align="left">
            {{ project.description }}
          </td>
          <td
            align="center"
            class="nob"
          >
            <span
              class="sorttime"
              style="display: none;"
            >
              {{ project.lastbuilddatefull }}
            </span>
            <a
              class="builddateelapsed"
              :href="project.link + '&date=' + project.lastbuilddate"
            >
              {{ project.lastbuild_elapsed }}
            </a>
            <img
              :src="$baseURL + '/img/cleardot.gif'"
              :class="'activity-level-' + project.activity"
            >
          </td>
        </tr>
      </tbody>
    </table>

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
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
export default {
  name: 'AllProjects',

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
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
}
</script>
