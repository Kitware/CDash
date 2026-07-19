<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="summary"
  >
    <section v-if="errored">
      <p>{{ cdash.error }}</p>
    </section>
    <section v-else>
      <BuildSummaryCard :build-id="buildId" />

      <LoadingIndicator :is-loading="loading">
        <!-- Display link to create bug tracker issue if supported. -->
        <div v-if="cdash.newissueurl">
          <a
            class="tw-link tw-link-hover"
            :href="cdash.newissueurl"
          >
            <b>Create {{ cdash.bugtracker }} issue for this build</b>
          </a>
          <br>
        </div>
        <br>

        <table>
          <tbody>
            <tr>
              <td>
                <!-- Previous build -->
                <table
                  v-if="cdash.previousbuild"
                  class="tabb striped"
                >
                  <thead>
                    <tr class="table-heading1">
                      <th
                        colspan="3"
                        class="header"
                      >
                        <a
                          class="tw-link tw-link-hover"
                          :href="$baseURL + '/builds/' + cdash.previousbuild.buildid"
                        >
                          <b>Previous Build</b>
                        </a>
                      </th>
                    </tr>
                    <tr class="table-heading">
                      <th>Stage</th>
                      <th>Errors</th>
                      <th>Warnings</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="cdash.hasconfigure">
                      <th>
                        <b>Configure</b>
                      </th>
                      <td
                        align="right"
                        :class="cdash.previousbuild.nconfigureerrors > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.previousbuild.buildid + '/configure'"
                          >
                            {{ cdash.previousbuild.nconfigureerrors }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.previousbuild.nconfigurewarnings > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.previousbuild.buildid + '/configure'"
                          >
                            {{ cdash.previousbuild.nconfigurewarnings }}
                          </a>
                        </b>
                      </td>
                    </tr>

                    <tr>
                      <th>
                        <b>Build</b>
                      </th>
                      <td
                        align="right"
                        :class="cdash.previousbuild.nerrors > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.previousbuild.buildid + '/build'"
                          >
                            {{ cdash.previousbuild.nerrors }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.previousbuild.nwarnings > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.previousbuild.buildid + '/build'"
                          >
                            {{ cdash.previousbuild.nwarnings }}
                          </a>
                        </b>
                      </td>
                    </tr>

                    <tr>
                      <th>
                        <b>Test</b>
                      </th>
                      <td
                        align="right"
                        :class="cdash.previousbuild.ntestfailed > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.previousbuild.buildid + '/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22status%22%3A%22FAILED%22%7D%7D%5D%7D'"
                          >
                            {{ cdash.previousbuild.ntestfailed }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.previousbuild.ntestnotrun > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.previousbuild.buildid + '/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22status%22%3A%22NOT_RUN%22%7D%7D%5D%7D'"
                          >
                            {{ cdash.previousbuild.ntestnotrun }}
                          </a>
                        </b>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>

              <!-- A horrible hack to put some space between these tables... -->
              <!-- TODO: (williamjallen) Why do we have nested tables here to begin with??? -->
              <td>&nbsp;</td>

              <td>
                <!-- Current build -->
                <table class="tabb striped">
                  <thead>
                    <tr class="table-heading1">
                      <th
                        colspan="3"
                        class="header"
                      >
                        This Build
                      </th>
                    </tr>
                    <tr class="table-heading">
                      <th>Stage</th>
                      <th>Errors</th>
                      <th>Warnings</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="cdash.hasconfigure">
                      <th>
                        <a
                          class="tw-link tw-link-hover"
                          href="#Configure"
                        >
                          <b>Configure</b>
                        </a>
                      </th>
                      <td
                        align="right"
                        :class="cdash.configure.nerrors > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.build.id + '/configure'"
                          >
                            {{ cdash.configure.nerrors }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.configure.nwarnings > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.build.id + '/configure'"
                          >
                            {{ cdash.configure.nwarnings }}
                          </a>
                        </b>
                      </td>
                    </tr>
                    <tr>
                      <th>
                        <a
                          class="tw-link tw-link-hover"
                          href="#Build"
                        >
                          <b>Build</b>
                        </a>
                      </th>
                      <td
                        align="right"
                        :class="cdash.build.nerrors > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.build.id + '/build'"
                          >
                            {{ cdash.build.nerrors }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.build.nwarnings > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.build.id + '/build'"
                          >
                            {{ cdash.build.nwarnings }}
                          </a>
                        </b>
                      </td>
                    </tr>
                    <tr>
                      <th>
                        <a
                          class="tw-link tw-link-hover"
                          href="#Test"
                        >
                          <b>Test</b>
                        </a>
                      </th>
                      <td
                        align="right"
                        :class="cdash.test.nfailed > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.build.id + '/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22status%22%3A%22FAILED%22%7D%7D%5D%7D'"
                          >
                            {{ cdash.test.nfailed }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.test.nnotrun > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.build.id + '/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22status%22%3A%22NOT_RUN%22%7D%7D%5D%7D'"
                          >
                            {{ cdash.test.nnotrun }}
                          </a>
                        </b>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>

              <td>&nbsp;</td>

              <td>
                <!-- Next build -->
                <table
                  v-if="cdash.nextbuild"
                  class="tabb striped"
                >
                  <thead>
                    <tr class="table-heading1">
                      <th
                        colspan="3"
                        class="header"
                      >
                        <a
                          class="tw-link tw-link-hover"
                          :href="$baseURL + '/builds/' + cdash.nextbuild.buildid"
                        >
                          <b>Next Build</b>
                        </a>
                      </th>
                    </tr>
                    <tr class="table-heading">
                      <th>Stage</th>
                      <th>Errors</th>
                      <th>Warnings</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="cdash.hasconfigure">
                      <th>
                        <b>Configure</b>
                      </th>
                      <td
                        align="right"
                        :class="cdash.nextbuild.nconfigureerrors > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.nextbuild.buildid + '/configure'"
                          >
                            {{ cdash.nextbuild.nconfigureerrors }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.nextbuild.nconfigurewarnings > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.nextbuild.buildid + '/configure'"
                          >
                            {{ cdash.nextbuild.nconfigurewarnings }}
                          </a>
                        </b>
                      </td>
                    </tr>

                    <tr>
                      <th>
                        <b>Build</b>
                      </th>
                      <td
                        align="right"
                        :class="cdash.nextbuild.nerrors > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.nextbuild.buildid + '/build'"
                          >
                            {{ cdash.nextbuild.nerrors }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.nextbuild.nwarnings > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.nextbuild.buildid + '/build'"
                          >
                            {{ cdash.nextbuild.nwarnings }}
                          </a>
                        </b>
                      </td>
                    </tr>

                    <tr>
                      <th>
                        <b>Test</b>
                      </th>
                      <td
                        align="right"
                        :class="cdash.nextbuild.ntestfailed > 0 ? 'error' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.nextbuild.buildid + '/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22status%22%3A%22FAILED%22%7D%7D%5D%7D'"
                          >
                            {{ cdash.nextbuild.ntestfailed }}
                          </a>
                        </b>
                      </td>
                      <td
                        align="right"
                        :class="cdash.nextbuild.ntestnotrun > 0 ? 'warning' : 'normal'"
                      >
                        <b>
                          <a
                            class="tw-link tw-link-hover"
                            :href="$baseURL + '/builds/' + cdash.nextbuild.buildid + '/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22status%22%3A%22NOT_RUN%22%7D%7D%5D%7D'"
                          >
                            {{ cdash.nextbuild.ntestnotrun }}
                          </a>
                        </b>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
          </tbody>
        </table>
        <br>

        <!-- Graphs -->
        <div class="tw-border tw-border-base-300 tw-rounded-lg tw-overflow-hidden">
          <div class="tw-flex tw-items-center tw-justify-between tw-px-3 tw-py-1 tw-bg-base-200">
            <span class="tw-text-base tw-font-bold">Build History</span>
            <a
              class="tw-btn tw-btn-sm tw-btn-outline"
              data-test="build-history-link"
              :href="$baseURL + '/index.php?project=' + cdash.projectname_encoded + '&filtercount=4&showfilters=1&filtercombine=and&field1=site&compare1=61&value1=' + cdash.build.sitename_encoded + '&field2=buildname&compare2=61&value2=' + cdash.build.name + '&field3=buildtype&compare3=61&value3=' + cdash.build.type + '&field4=buildstarttime&compare4=84&value4=' + cdash.build.starttime"
            >
              <FontAwesomeIcon :icon="FA.faLink" />
              Show History
            </a>
          </div>
          <div class="tw-p-1">
            <LoadingIndicator :is-loading="!buildTimeData">
              <BuildTimeChart :data="buildTimeData" />
            </LoadingIndicator>
          </div>
        </div>
      </LoadingIndicator>
    </section>
  </BuildSidebar>
</template>

<script>
import {
  faQuestionCircle,
  faLink,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import gql from 'graphql-tag';
import Utils from './shared/Utils';
import BuildTimeChart from './BuildSummaryPage/BuildTimeChart.vue';
import { DateTime, Duration } from 'luxon';

export default {
  name: 'BuildSummary',
  components: {BuildTimeChart, BuildSummaryCard, LoadingIndicator, BuildSidebar, FontAwesomeIcon},

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    buildId: {
      type: Number,
      required: true,
    },
    previousBuildId: {
      type: Number,
      default: 0,
    },
    nextBuildId: {
      type: Number,
      default: 0,
    },
    newIssueUrl: {
      type: String,
      default: '',
    },
    bugTracker: {
      type: String,
      default: '',
    },
    userId: {
      type: Number,
      default: 0,
    },
  },

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
    };
  },

  apollo: {
    buildData: {
      query: gql`
        query BuildSummary($buildId: ID!, $prevId: ID, $nextId: ID, $hasPrev: Boolean!, $hasNext: Boolean!) {
          buildData: build(id: $buildId) {
            id
            name
            startTime
            buildType
            configureErrorsCount
            configureWarningsCount
            buildErrorsCount
            buildWarningsCount
            failedTestsCount
            notRunTestsCount
            notRunTestsWarningCount
            site {
              id
              name
            }
            subProject {
              id
            }
            project {
              id
              name
            }
            configure {
              id
            }
          }
          prevBuild: build(id: $prevId) @include(if: $hasPrev) {
            id
            configureErrorsCount
            configureWarningsCount
            buildErrorsCount
            buildWarningsCount
            failedTestsCount
            notRunTestsCount
            notRunTestsWarningCount
          }
          nextBuild: build(id: $nextId) @include(if: $hasNext) {
            id
            configureErrorsCount
            configureWarningsCount
            buildErrorsCount
            buildWarningsCount
            failedTestsCount
            notRunTestsCount
            notRunTestsWarningCount
          }
        }
      `,
      variables() {
        return {
          buildId: this.buildId,
          prevId: this.previousBuildId || null,
          nextId: this.nextBuildId || null,
          hasPrev: !!this.previousBuildId,
          hasNext: !!this.nextBuildId,
        };
      },
      result({ data }) {
        this.loading = false;
        const build = data.buildData;

        this.cdash.newissueurl = this.newIssueUrl;
        this.cdash.bugtracker = this.bugTracker;

        if (data.prevBuild) {
          const prev = data.prevBuild;
          this.cdash.previousbuild = {
            buildid: prev.id,
            nconfigureerrors: Math.max(0, prev.configureErrorsCount),
            nconfigurewarnings: Math.max(0, prev.configureWarningsCount),
            nerrors: Math.max(0, prev.buildErrorsCount),
            nwarnings: Math.max(0, prev.buildWarningsCount),
            ntestfailed: Math.max(0, prev.failedTestsCount),
            ntestnotrun: Math.max(0, prev.notRunTestsWarningCount),
          };
        }
        else {
          this.cdash.previousbuild = null;
        }

        if (data.nextBuild) {
          const next = data.nextBuild;
          this.cdash.nextbuild = {
            buildid: next.id,
            nconfigureerrors: Math.max(0, next.configureErrorsCount),
            nconfigurewarnings: Math.max(0, next.configureWarningsCount),
            nerrors: Math.max(0, next.buildErrorsCount),
            nwarnings: Math.max(0, next.buildWarningsCount),
            ntestfailed: Math.max(0, next.failedTestsCount),
            ntestnotrun: Math.max(0, next.notRunTestsWarningCount),
          };
        }
        else {
          this.cdash.nextbuild = null;
        }

        this.cdash.hasconfigure = !!build.configure;

        this.cdash.build = {
          id: build.id,
          nerrors: Math.max(0, build.buildErrorsCount),
          nwarnings: Math.max(0, build.buildWarningsCount),
          name: build.name,
          type: build.buildType,
          starttime: build.startTime,
          sitename_encoded: encodeURIComponent(build.site.name),
        };

        this.cdash.configure = {
          nerrors: Math.max(0, build.configureErrorsCount),
          nwarnings: Math.max(0, build.configureWarningsCount),
        };

        this.cdash.test = {
          nfailed: Math.max(0, build.failedTestsCount),
          nnotrun: Math.max(0, build.notRunTestsWarningCount),
        };

        this.cdash.projectname_encoded = encodeURIComponent(build.project.name);
        this.cdash.user = {
          id: this.userId,
        };
      },
      error(error) {
        this.errored = true;
        this.cdash.error = error;
        this.loading = false;
      },
    },
    buildHistory: {
      query: gql`
        query($projectId: ID, $filters: ProjectBuildsFiltersMultiFilterInput, $onlyParents: Boolean) {
          project(id: $projectId) {
            id
            builds(
              first: 100,
              orderBy: [{column: START_TIME, order: DESC}],
              filters: $filters
              onlyParents: $onlyParents
            ) {
              edges {
                node {
                  id
                  startTime
                  configureDuration
                  buildDuration
                  testDuration
                  configureErrorsCount
                  buildErrorsCount
                  failedTestsCount
                }
              }
            }
          }
        }
      `,
      update: data => data?.project?.builds.edges,
      variables() {
        return {
          projectId: this.projectId,
          filters: this.buildHistoryFilters,
          onlyParents: this.isParentBuild,
        };
      },
      skip() {
        return !this.buildData;
      },
    },
  },

  computed: {
    FA() {
      return {
        faQuestionCircle,
        faLink,
      };
    },

    Utils() {
      return Utils;
    },

    isParentBuild() {
      return !(this.buildData && this.buildData.subProject);
    },

    buildHistoryFilters() {
      if (!this.buildData) {
        return null;
      }
      const build = this.buildData;
      const conditions = [
        { eq: { name: build.name } },
        { eq: { buildType: build.buildType } },
        { has: { site: { eq: { id: build.site.id } } } },
        { le: { startTime: build.startTime } },
      ];
      if (build.subProject) {
        conditions.push({ has: { subProject: { eq: { id: build.subProject.id } } } });
      }
      return { all: conditions };
    },

    buildTimeData() {
      if (!this.buildHistory) {
        return [];
      }
      return [...this.buildHistory].reverse().map(edge => {
        const build = edge.node;
        return {
          buildId: build.id,
          configureTime: Duration.fromObject({ seconds: Math.max(build.configureDuration, 0) }),
          buildTime: Duration.fromObject({ seconds: Math.max(build.buildDuration, 0) }),
          testTime: Duration.fromObject({ seconds: Math.max(build.testDuration, 0) }),
          startTimestamp: DateTime.fromISO(build.startTime),
          configureFailed: (build.configureErrorsCount ?? 0) > 0,
          buildFailed: (build.buildErrorsCount ?? 0) > 0,
          testFailed: (build.failedTestsCount ?? 0) > 0,
        };
      });
    },
  },

  methods: {
  },
};
</script>
