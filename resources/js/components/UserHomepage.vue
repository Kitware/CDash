<template>
  <loading-indicator :is-loading="loading">
    <!-- Message -->
    <table v-if="cdash.message">
      <tr>
        <td width="95">
          <div align="right" />
        </td>
        <td>
          <div style="color: green;">
            {{ cdash.message }}
          </div>
        </td>
      </tr>
    </table>

    <!-- My Projects -->
    <div v-if="cdash.projects.length > 0">
      <table
        border="0"
        cellpadding="4"
        cellspacing="0"
        width="100%"
        class="tabb"
      >
        <tbody>
          <tr class="table-heading1">
            <td
              id="nob"
              colspan="7"
            >
              <h3>My Projects</h3>
            </td>
          </tr>
          <tr class="table-heading">
            <td
              align="center"
              width="100px"
              class="botl"
            >
              Project Name
            </td>
            <td
              align="center"
              width="240px"
              class="botl"
            >
              Actions
            </td>
            <td
              align="center"
              width="130px"
              class="botl"
            >
              Builds
            </td>
            <td
              align="center"
              width="130px"
              class="botl"
            >
              Builds per day
            </td>
            <td
              align="center"
              width="130px"
              class="botl"
            >
              Success Last 24h
            </td>
            <td
              align="center"
              width="130px"
              class="botl"
            >
              Errors Last 24h
            </td>
            <td
              align="center"
              width="130px"
              class="botl"
            >
              Warnings Last 24h
            </td>
          </tr>
          <tr
            v-for="project in cdash.projects"
            class="table-heading"
          >
            <td align="center">
              <a :href="$baseURL + '/index.php?project=' + project.name_encoded">{{ project.name }}</a>
            </td>
            <td
              align="center"
              bgcolor="#DDDDDD"
              class="icon-row"
            >
              <a
                title="Edit subscription"
                :href="$baseURL + '/subscribeProject.php?projectid=' + project.id + '&edit=1'"
              >
                <font-awesome-icon icon="fa-solid fa-bell"/>
              </a>
              <a
                v-if="project.role > 0"
                title="Claim sites"
                :href="$baseURL + '/editSite.php?projectid=' + project.id"
              >
                <font-awesome-icon icon="fa-solid fa-computer"/>
              </a>
              <a
                v-if="project.role > 1"
                title="Edit project"
                :href="$baseURL + '/project/' + project.id + '/edit'"
              >
                <font-awesome-icon icon="fa-solid fa-pencil"/>
              </a>
              <a
                v-if="project.role > 1"
                title="Manage subprojects"
                :href="$baseURL + '/manageSubProject.php?projectid=' + project.id"
              >
                <font-awesome-icon icon="fa-solid fa-folder-tree"/>
              </a>
              <a
                v-if="project.role > 1"
                title="Manage project groups"
                :href="$baseURL + '/manageBuildGroup.php?projectid=' + project.id"
              >
                <font-awesome-icon icon="fa-solid fa-layer-group"/>
              </a>
              <a
                v-if="project.role > 1"
                title="Manage project users"
                :href="$baseURL + '/manageProjectRoles.php?projectid=' + project.id"
              >
                <font-awesome-icon icon="fa-solid fa-user-pen"/>
              </a>
              <a
                v-if="project.role > 1"
                title="Manage project coverage"
                :href="$baseURL + '/manageCoverage.php?projectid=' + project.id"
              >
                <font-awesome-icon icon="fa-solid fa-chart-line"/>
              </a>
            </td>
            <td
              align="center"
              bgcolor="#DDDDDD"
            >
              {{ project.nbuilds }}
            </td>
            <td
              align="center"
              bgcolor="#DDDDDD"
            >
              {{ project.average_builds }}
            </td>
            <td
              align="center"
              bgcolor="#DDDDDD"
              :class="{'normal': project.success > 0}"
            >
              {{ project.success }}
            </td>
            <td
              align="center"
              bgcolor="#DDDDDD"
              :class="{'error': project.error > 0}"
            >
              {{ project.error }}
            </td>
            <td
              align="center"
              bgcolor="#DDDDDD"
              :class="{'warning': project.warning > 0}"
            >
              {{ project.warning }}
            </td>
          </tr>
        </tbody>
      </table>
      <br>
    </div>

    <!-- My Sites -->
    <div v-if="cdash.claimedsites.length > 0">
      <table
        border="0"
        cellpadding="4"
        cellspacing="0"
        width="100%"
        class="tabb table-striped"
      >
        <thead>
          <tr class="table-heading1">
            <td colspan="10">
              <h3>My Sites</h3>
            </td>
          </tr>
        </thead>
        <tbody>
          <!-- header of the matrix -->
          <tr class="table-heading">
            <td align="center">
              <b>Site</b>
            </td>
            <td
              v-for="project in cdash.claimedsiteprojects"
              align="center"
            >
              <a :href="$baseURL + '/index.php?project=' + project.name_encoded">
                {{ project.name }}
              </a>
            </td>
          </tr>
          <!-- Fill in the information -->
          <tr
            v-for="site in cdash.claimedsites"
          >
            <td align="center">
              <a :href="$baseURL + '/editSite.php?siteid=' + site.id">
                {{ site.name }}
              </a>
              <img
                v-if="site.outoforder == 1"
                border="0"
                :src="$baseURL + '/img/flag.png'"
                title="flag"
              >
            </td>
            <td
              v-for="project in site.projects"
              align="center"
            >
              <table
                width="100%"
                border="0"
              >
                <tr
                  v-if="project.nightly.NA == 0"
                  class="table-heading"
                >
                  <td align="center">
                    <b>N</b>
                  </td>
                  <td
                    align="center"
                    :class="project.nightly.updateclass"
                  >
                    {{ project.nightly.update }}
                  </td>
                  <td
                    align="center"
                    :class="project.nightly.configureclass"
                  >
                    {{ project.nightly.configure }}
                  </td>
                  <td
                    align="center"
                    :class="project.nightly.errorclass"
                  >
                    {{ project.nightly.error }}
                  </td>
                  <td
                    align="center"
                    :class="project.nightly.testfailclass"
                  >
                    {{ project.nightly.testfail }}
                  </td>
                  <td
                    align="center"
                    :class="project.nightly.dateclass"
                  >
                    {{ project.nightly.date }}
                  </td>
                </tr>
                <tr
                  v-if="project.continuous.NA == 0"
                  class="table-heading"
                >
                  <td align="center">
                    <b>C</b>
                  </td>
                  <td
                    align="center"
                    :class="project.continuous.updateclass"
                  >
                    {{ project.continuous.update }}
                  </td>
                  <td
                    align="center"
                    :class="project.continuous.configureclass"
                  >
                    {{ project.continuous.configure }}
                  </td>
                  <td
                    align="center"
                    :class="project.continuous.errorclass"
                  >
                    {{ project.continuous.error }}
                  </td>
                  <td
                    align="center"
                    :class="project.continuous.testfailclass"
                  >
                    {{ project.continuous.testfail }}
                  </td>
                  <td
                    align="center"
                    :class="project.continuous.dateclass"
                  >
                    {{ project.continuous.date }}
                  </td>
                </tr>
                <tr
                  v-if="project.experimental.NA == 0"
                  class="table-heading"
                >
                  <td align="center">
                    <b>E</b>
                  </td>
                  <td
                    align="center"
                    :class="project.experimental.updateclass"
                  >
                    {{ project.experimental.update }}
                  </td>
                  <td
                    align="center"
                    :class="project.experimental.configureclass"
                  >
                    {{ project.experimental.configure }}
                  </td>
                  <td
                    align="center"
                    :class="project.experimental.errorclass"
                  >
                    {{ project.experimental.error }}
                  </td>
                  <td
                    align="center"
                    :class="project.experimental.testfailclass"
                  >
                    {{ project.experimental.testfail }}
                  </td>
                  <td
                    align="center"
                    :class="project.experimental.dateclass"
                  >
                    {{ project.experimental.date }}
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
      <br>
    </div>

    <!-- Public Project -->
    <div v-if="cdash.publicprojects.length > 0">
      <table
        border="0"
        cellpadding="4"
        cellspacing="0"
        width="100%"
        class="tabb table-striped"
      >
        <thead>
          <tr class="table-heading1">
            <td
              colspan="3"
            >
              <h3>Public projects</h3>
            </td>
          </tr>
        </thead>
        <tbody>
          <tr v-for="project in cdash.publicprojects">
            <td align="center">
              <a :href="$baseURL + '/index.php?project=' + project.name">{{ project.name }}</a>
            </td>
            <td>
              <a :href="$baseURL + '/subscribeProject.php?projectid=' + project.id">
                Subscribe to this project
              </a>
            </td>
          </tr>
        </tbody>
      </table>
      <br>
    </div>

    <div>
      <table
        border="0"
        cellpadding="4"
        cellspacing="0"
        width="100%"
        class="tabb table-striped"
      >
        <thead>
          <tr class="table-heading1">
            <td
              id="nob"
              colspan="4"
            >
              <h3>My Authentication Tokens</h3>
            </td>
          </tr>
          <tr
            v-if="cdash.authtokens.length > 0"
            class="table-heading"
          >
            <td
              align="center"
              class="botl"
            >
              Description
            </td>
            <td
              align="center"
              class="botl"
            >
              Scope
            </td>
            <td
              align="center"
              class="botl"
            >
              Expires
            </td>
            <td
              align="center"
              class="botl"
            >
              Revoke
            </td>
          </tr>
        </thead>
        <tbody>
          <!-- The <template> tag gets removed during the compilation process -->
          <template v-for="authtoken in cdash.authtokens">
            <tr>
              <td align="center">
                {{ authtoken.description }}
              </td>
              <td
                v-if="authtoken.scope !== 'submit_only'"
                align="center"
              >
                Full Access
              </td>
              <td
                v-if="authtoken.scope === 'submit_only'"
                align="center"
              >
                Submit Only{{ authtoken.projectname && authtoken.projectname.length > 0 ? ' (' + authtoken.projectname + ')' : '' }}
              </td>
              <td align="center">
                {{ authtoken.expires }}
              </td>
              <td align="center">
                <span
                  class="glyphicon glyphicon-trash"
                  tooltip-popup-delay="1500"
                  tooltip-append-to-body="true"
                  uib-tooltip="Revoke this token"
                  @click="revokeToken(authtoken)"
                />
              </td>
            </tr>
            <tr
              v-if="authtoken.raw_token !== undefined"
            >
              <td align="center">
                Token for <strong>{{ authtoken.description }}</strong>: <code>{{ authtoken.raw_token }}</code>
              </td>
              <td
                align="center"
                colspan="2"
              >
                <strong
                  v-show="!authtoken.copied"
                  class="animate-show"
                >
                  Copy this token. It cannot be retrieved later if you leave this page!
                </strong>
              </td>
              <td align="center">
                <button
                  class="btn btn-default"
                  @click="copyTokenSuccess(authtoken)"
                >
                  Copy
                </button>
                <span
                  class="glyphicon"
                  :class="authtoken.showcheck ? 'glyphicon-ok' : 'glyphicon-none'"
                />
              </td>
            </tr>
          </template>
        </tbody>
        <tfoot>
          <tr>
            <td id="tokenDescriptionCell">
              <label
                id="tokenDescriptionlabel"
                for="tokenDescription"
              >New Token:</label>
              <input
                id="tokenDescription"
                v-model="cdash.tokendescription"
                type="text"
                name="tokenDescription"
                class="form-control"
                placeholder="Description (site, project, etc)"
              >
            </td>
            <td align="center">
              <select
                v-model="tokenscope"
                class="form-select"
              >
                <option
                  v-if="cdash.allow_full_access_tokens"
                  value="full_access"
                >
                  Full Access
                </option>
                <option
                  v-if="cdash.allow_submit_only_tokens"
                  value="submit_only"
                >
                  All Projects (Submit Only)
                </option>
                <option
                  v-for="project in cdash.projects"
                  :value="project.id"
                >
                  {{ project.name }} (Submit Only)
                </option>
              </select>
            </td>
            <td />
            <td align="center">
              <button
                class="btn btn-default"
                :disabled="!cdash.tokendescription"
                @click="generateToken()"
              >
                Generate Token
              </button>
            </td>
          </tr>
        </tfoot>
      </table>
      <br>
    </div>

    <!-- If we allow user to create new projects -->
    <div v-if="cdash.user_can_create_projects == 1 && cdash.user_is_admin ==0">
      <table
        border="0"
        cellpadding="4"
        cellspacing="0"
        width="100%"
        class="tabb"
      >
        <tbody>
          <tr class="table-heading1">
            <td id="nob">
              <h3>Administration</h3>
            </td>
          </tr>
          <tr class="trodd">
            <td id="nob">
              <a href="project/new">Start a new project</a>
            </td>
          </tr>
        </tbody>
      </table>
      <br>
    </div>

    <!-- Global Administration -->
    <table
      v-if="cdash.user_is_admin == 1"
      border="0"
      cellpadding="4"
      cellspacing="0"
      width="100%"
      class="tabb table-striped"
    >
      <thead>
        <tr class="table-heading1">
          <th>
            <h3>Administration</h3>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <a :href="$baseURL + '/project/new'">Create new project</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/manageProjectRoles.php'">Manage project roles</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/manageSubProject.php'">Manage subproject</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/manageBuildGroup.php'">Manage project groups</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/manageCoverage.php'">Manage project coverage</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/manageBanner.php'">Manage banner message</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/manageUsers.php'">Manage users</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/authtokens/manage'">Manage authentication tokens</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/upgrade.php'">Maintenance</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/sites'">Site Statistics</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/userStatistics.php'">User Statistics</a>
          </td>
        </tr>
        <tr>
          <td>
            <a :href="$baseURL + '/removeBuilds.php'">Remove Builds</a>
          </td>
        </tr>
        <tr v-if="cdash.show_monitor">
          <td>
            <a :href="$baseURL + '/monitor'">Monitor / Processing Statistics</a>
          </td>
        </tr>
      </tbody>
    </table>
  </loading-indicator>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import LoadingIndicator from "./shared/LoadingIndicator.vue";
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
export default {
  name: "UserHomepage",
  components: { FontAwesomeIcon, LoadingIndicator },

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
      tokenscope: 'full_access',
    }
  },

  mounted () {
    ApiLoader.loadPageData(this, '/api/v1/user.php');
  },

  methods: {
    generateToken: function () {
      const parameters = {
        description: this.cdash.tokendescription,
        scope: this.tokenscope === 'full_access' ? 'full_access' : 'submit_only',
        projectid: this.tokenscope === 'full_access' || this.tokenscope === 'submit_only' ? -1 : this.tokenscope
      };
      this.$axios.post('/api/authtokens/create', parameters)
        .then((response) => {
          const authtoken = response.data.token;
          authtoken.copied = false;
          authtoken.raw_token = response.data.raw_token;

          this.cdash.projects.forEach(project => {
            if (project.id == authtoken.projectid) {
              authtoken.projectname = project.name;
            }
          });

          // A terrible hack to format the date the same way the DB returns them on initial page load
          authtoken.expires = authtoken.expires.replace('T', ' ');

          this.cdash.authtokens.push(authtoken);
        })
        .catch(error => {
          this.errored = true;
          this.error = error.error;
        });
    },

    revokeToken: function (authtoken) {
      this.$axios.delete(`/api/authtokens/delete/${authtoken.hash}`)
        .then(() => {
          // Remove this token from our list.
          let index = -1;
          for (let i = 0, len = this.cdash.authtokens.length; i < len; i++) {
            if (this.cdash.authtokens[i].hash === authtoken.hash) {
              index = i;
              break;
            }
          }
          if (index > -1) {
            this.cdash.authtokens.splice(index, 1);
          }
        })
        .catch(error => {
          this.errored = true;
          this.error = error.error;
        });
    },

    copyTokenSuccess: function (token) {
      try {
        navigator.clipboard.writeText(token.raw_token);
        token.copied = true;
        token.showcheck = true;
        setTimeout(() => {
          token.showcheck = false;
        }, 2000);
      } catch(error) {
        this.errored = true;
        this.error = error.toString();
      }
    },
  },
}
</script>

<style>
#tokenDescriptionCell {
  display: flex;
  justify-content: space-between;
  white-space: nowrap;
  align-items: center;
}

#tokenDescriptionlabel {
  margin: 0 0.5em 0 0;
}

.icon-row > a {
  padding: 0 0.3em;
}
</style>
