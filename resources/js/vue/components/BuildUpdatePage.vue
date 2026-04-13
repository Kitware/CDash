<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="update"
  >
    <section class="tw-flex tw-flex-col tw-w-full tw-gap-4">
      <build-summary-card :build-id="buildId" />

      <loading-indicator :is-loading="!update">
        <div>
          <div v-if="update.revision">
            <b>Revision: </b>
            <tt v-if="repository">
              <a
                class="tw-link tw-link-hover tw-link-info"
                :href="revisionUrl"
              >{{ update.revision }}</a>
            </tt>
            <tt v-else>
              {{ update.revision }}
            </tt>
          </div>
          <div v-if="update.priorRevision">
            <b>Prior Revision: </b>
            <tt v-if="repository">
              <a
                class="tw-link tw-link-hover tw-link-info"
                :href="repository?.getCommitUrl(update.priorRevision) ?? ''"
              >{{ update.priorRevision }}</a>
            </tt>
            <tt v-else>
              {{ update.priorRevision }}
            </tt>
          </div>

          <a
            class="tw-link tw-link-hover tw-link-info"
            @click="toggleGraph()"
          >
            <span v-text="showGraph ? 'Hide Activity Graph' : 'Show Activity Graph'" />
          </a>
          <div v-if="graphLoading">
            <img
              id="spinner"
              :src="$baseURL + '/img/loading.gif'"
            >
          </div>
          <div v-show="graphLoaded && showGraph">
            <div id="graphoptions" />
            <div id="graph" />
            <div
              id="graph_holder"
              class="center-text"
            />
          </div>
        </div>

        <h3
          v-if="update.status"
          class="error"
        >
          {{ update.status }}
        </h3>
      </loading-indicator>

      <loading-indicator :is-loading="!updateFiles">
        <div class="tw-flex tw-flex-col tw-gap-4">
          <CommitCard
            v-for="commitFiles in commits"
            :key="commitFiles[0].revision"
            :commit-files="commitFiles"
            :repository="repository"
          />
        </div>
      </loading-indicator>
    </section>
  </BuildSidebar>
</template>

<script>
import $ from 'jquery';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import {getRepository} from './shared/RepositoryIntegrations';
import gql from 'graphql-tag';
import { DateTime } from 'luxon';
import CommitCard from './BuildUpdate/CommitCard.vue';

export default {
  name: 'BuildUpdate',
  components: {CommitCard, LoadingIndicator, BuildSummaryCard, BuildSidebar},

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    repositoryType: {
      type: String,
      required: true,
    },

    repositoryUrl: {
      type: String,
      required: true,
    },
  },

  data() {
    return {
      // Booleans controlling whether a section should be displayed or not.
      showGraph: false,

      // Graph data.
      graphLoading: false,
      graphLoaded: false,
      graphData: [],
      graphRendered: {
        'time': false,
        'errors': false,
        'warnings': false,
        'tests': false,
      },
    };
  },

  apollo: {
    update: {
      query: gql`
        query($buildId: ID) {
          build(id: $buildId) {
            id
            updateStep {
              id
              command
              type
              status
              revision
              priorRevision
              path
            }
          }
        }
      `,
      update: data => data?.build?.updateStep,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
    },

    updateFiles: {
      query: gql`
        query($buildId: ID) {
          build(id: $buildId) {
            id
            updateStep {
              id
              updateFiles(first: 100000) {
                edges {
                  node {
                    id
                    fileName
                    authorName
                    authorEmail
                    committerName
                    committerEmail
                    checkinDate
                    log
                    revision
                    priorRevision
                    status
                  }
                }
              }
            }
          }
        }
      `,
      update: data => data?.build?.updateStep?.updateFiles?.edges,
      variables() {
        return {
          buildId: this.buildId,
        };
      },
    },
  },

  computed: {
    repository() {
      return getRepository(this.repositoryType, this.repositoryUrl);
    },

    revisionUrl() {
      if (this.update.priorRevision) {
        return this.repository?.getComparisonUrl(this.update.revision, this.update.priorRevision) ?? '';
      }
      else {
        return this.repository?.getCommitUrl(this.update.revision) ?? '';
      }
    },

    commits() {
      if (!this.updateFiles) {
        return [];
      }

      // Group by revision
      const groups = {};
      for (const edge of this.updateFiles) {
        const file = edge.node;
        const revision = file.revision || 'Unknown Revision';
        if (!groups[revision]) {
          groups[revision] = [];
        }
        groups[revision].push(file);
      }

      const commits = Object.values(groups);

      // Sort files within each commit
      commits.forEach(commitFiles => {
        commitFiles.sort((a, b) => a.fileName.localeCompare(b.fileName));
      });

      // Sort commits by max checkin date (descending)
      commits.sort((a, b) => {
        const maxDateA = a.reduce((max, file) => {
          if (!file.checkinDate) {
            return max;
          }
          const dt = DateTime.fromISO(file.checkinDate);
          return dt > max ? dt : max;
        }, DateTime.fromMillis(0));

        const maxDateB = b.reduce((max, file) => {
          if (!file.checkinDate) {
            return max;
          }
          const dt = DateTime.fromISO(file.checkinDate);
          return dt > max ? dt : max;
        }, DateTime.fromMillis(0));

        return maxDateB.toMillis() - maxDateA.toMillis();
      });

      return commits;
    },
  },

  async mounted() {
    // Ensure jQuery is globally available before loading plugins
    window.jQuery = $;
    await import('flot/dist/es5/jquery.flot');
  },

  methods: {
    toggleGraph: function() {
      this.showGraph = !this.showGraph;
      if (!this.graphLoaded) {
        this.loadGraph();
      }
    },

    loadGraph: function() {
      this.graphLoading = true;
      this.$axios
        .get(`/api/v1/buildUpdateGraph.php?buildid=${this.buildId}`)
        .then(response => {
          this.initializeGraph(response.data);
          this.graphLoaded = true;
        })
        .finally(() => this.graphLoading = false);
    },

    initializeGraph: function(data) {
      const options = {
        lines: {show: true},
        points: {show: true},
        xaxis: {
          mode: 'time',
          timeformat: '%Y/%m/%d %H:%M',
          timeBase: 'milliseconds',
        },
        grid: {
          backgroundColor: '#fffaff',
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4,
        },
        selection: {mode: 'x'},
        colors: ['#0000FF', '#dba255', '#919733'],
      };

      let plot = $.plot($('#graph_holder'), [{label: 'Number of changed files', data: data.data}], options);

      $('#graph_holder').bind('selected', (event, area) => {
        plot = $.plot($('#graph_holder'), [{
          label: 'Number of changed files',
          data: data.data,
        }], $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
      });

      const baseURL = this.$baseURL;
      $('#graph_holder').bind('plotclick', (e, pos, item) => {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          window.location = `${baseURL}/builds/${data.buildids[item.datapoint[0]]}`;
        }
      });
    },
  },
};
</script>
