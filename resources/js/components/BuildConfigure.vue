<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div
      v-else
      id="main_content"
    >
      <br>
      <table
        v-if="!cdash.build.hassubprojects"
        border="0"
      >
        <tr>
          <td align="left">
            <b>Site: </b>
            <a :href="$baseURL + '/viewSite.php?siteid=' + cdash.build.siteid">
              {{ cdash.build.site }}
            </a>
          </td>
        </tr>
        <tr>
          <td align="left">
            <b>Build: </b>
            <a :href="$baseURL + '/build/' + buildid">
              {{ cdash.build.buildname }}
            </a>
          </td>
        </tr>
        <tr>
          <td align="left">
            <b>Configure Command: </b>{{ cdash.configures[0].command }}
          </td>
        </tr>
        <tr>
          <td align="left">
            <b>Configure Return Value: </b>{{ cdash.configures[0].status }}
          </td>
        </tr>
        <tr>
          <td align="left">
            <b>Configure Output:</b>
          </td>
        </tr>
        <tr>
          <td align="left">
            <pre>{{ cdash.configures[0].output }}</pre>
          </td>
        </tr>
      </table>

      <table
        v-if="cdash.build.hassubprojects"
        style="width:100%"
      >
        <thead>
          <tr class="table-heading">
            <th
              align="left"
              width="15%"
            >
              Subproject
            </th>
            <th
              align="left"
              width="5%"
            >
              Error
            </th>
            <th
              align="left"
              width="5%"
            >
              Warn
            </th>
            <th align="left">
              Configure
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="configure in cdash.configures">
            <td style="vertical-align:top">
              {{ configure.subprojectname }}
            </td>
            <td style="vertical-align:top">
              {{ configure.configureerrors }}
            </td>
            <td style="vertical-align:top">
              {{ configure.configurewarnings }}
            </td>
            <td>
              <a :click="configure.show = !configure.show">
                <span v-show="!configure.show">View</span>
                <span v-show="configure.show">Hide</span>
              </a>
              <table
                v-show="configure.show"
                class="configure tabb"
                border="0"
                cellpadding="4"
                cellspacing="0"
                width="100%"
              >
                <tr>
                  <td align="left">
                    <b>Site: </b>
                    <a :href="$baseURL + '/viewSite.php?siteid=' + cdash.build.siteid">
                      {{ cdash.build.site }}
                    </a>
                  </td>
                </tr>
                <tr>
                  <td align="left">
                    <b>Build Name: </b>{{ cdash.build.buildname }}
                  </td>
                </tr>
                <tr>
                  <td align="left">
                    <b>Configure Command: </b>{{ configure.command }}
                  </td>
                </tr>
                <tr>
                  <td align="left">
                    <b>Configure Return Value: </b>{{ configure.status }}
                  </td>
                </tr>
                <tr>
                  <td align="left">
                    <b>Configure Output:</b>
                  </td>
                </tr>
                <tr>
                  <td align="left">
                    <pre>{{ configure.output }}</pre>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
      <br>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
export default {
  name: 'BuildConfigure',

  data () {
    return {
      // API results.
      buildid: null,
      cdash: {},
      loading: true,
      errored: false,
    };
  },

  mounted () {
    const path_parts = window.location.pathname.split('/');
    this.buildid = path_parts[path_parts.length - 2];
    const endpoint_path = `/api/v1/viewConfigure.php?buildid=${this.buildid}`;
    ApiLoader.loadPageData(this, endpoint_path);
  },
};
</script>
