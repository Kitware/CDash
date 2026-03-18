<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="update"
  >
    <section v-if="errored">
      <p>{{ cdash.error }}</p>
    </section>
    <section
      v-else
      class="tw-flex tw-flex-col tw-w-full tw-gap-4"
    >
      <build-summary-card :build-id="buildId" />

      <loading-indicator :is-loading="loading">
        <div>
          <div v-if="cdash.update.revision">
            <b>Revision: </b>
            <tt v-if="cdash.update.revisionurl.length > 0">
              <a
                class="tw-link tw-link-hover tw-link-info"
                :href="repository?.getComparisonUrl(cdash.update.revision, cdash.update.priorrevision) ?? ''"
              >{{ cdash.update.revision }}</a>
            </tt>
            <tt v-else>
              {{ cdash.update.revision }}
            </tt>
          </div>
          <div v-if="cdash.update.priorrevision">
            <b>Prior Revision: </b>
            <tt v-if="cdash.update.revisiondiff.length > 0">
              <a
                class="tw-link tw-link-hover tw-link-info"
                :href="repository?.getCommitUrl(cdash.update.priorrevision) ?? ''"
              >{{ cdash.update.priorrevision }}</a>
            </tt>
            <tt v-else>
              {{ cdash.update.priorrevision }}
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
          v-if="cdash.update.status"
          class="error"
        >
          {{ cdash.update.status }}
        </h3>

        <div v-for="group in cdash.updategroups">
          <div class="tw-w-full">
            <div @click="group.hidden = !group.hidden; $forceUpdate()">
              <font-awesome-icon :icon="group.hidden ? FA.faChevronRight : FA.faChevronDown" />
              <b>{{ group.description }}</b>
            </div>
            <div class="tw-flex tw-flex-col tw-gap-4 tw-ml-8">
              <div
                v-for="directory in group.directories"
                v-show="!group.hidden"
              >
                <div
                  @click="directory.hidden = !directory.hidden; $forceUpdate()"
                >
                  <font-awesome-icon :icon="directory.hidden ? FA.faChevronRight : FA.faChevronDown" />
                  <tt>{{ directory.name }}</tt>
                </div>
                <div class="tw-flex tw-flex-col tw-gap-4 tw-ml-8">
                  <div
                    v-for="file in directory.files"
                    v-show="!directory.hidden"
                  >
                    <div>
                      <tt>{{ file.filename }}</tt> Revision:
                      <a
                        v-if="file.diffurl"
                        class="tw-link tw-link-hover tw-link-info"
                        :href="file.diffurl"
                      >
                        <tt>{{ file.revision }}</tt>
                      </a>
                      <tt v-else>
                        {{ file.revision }}
                      </tt>
                      <span v-if="file.author">
                        by
                        <a
                          v-if="file.email"
                          class="tw-link tw-link-hover tw-link-info"
                          :href="'mailto:' + file.email"
                        >
                          {{ file.author }}
                        </a>
                        <span v-else>
                          {{ file.author }}
                        </span>
                      </span>
                    </div>
                    <code-box :text="file.log" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </loading-indicator>
    </section>
  </BuildSidebar>
</template>

<script>
import $ from 'jquery';
import ApiLoader from './shared/ApiLoader';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import CodeBox from './shared/CodeBox.vue';
import {faChevronDown, faChevronRight} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {getRepository} from './shared/RepositoryIntegrations';

export default {
  name: 'BuildUpdate',
  components: {FontAwesomeIcon, CodeBox, LoadingIndicator, BuildSummaryCard, BuildSidebar},

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
      // API results.
      cdash: {},
      loading: true,
      errored: false,

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

  computed: {
    FA() {
      return {
        faChevronDown,
        faChevronRight,
      };
    },

    repository() {
      return getRepository(this.repositoryType, this.repositoryUrl);
    },
  },

  async mounted() {
    // Ensure jQuery is globally available before loading plugins
    window.jQuery = $;
    await import('flot/dist/es5/jquery.flot');

    const endpoint_path = `/api/v1/viewUpdate.php?buildid=${this.buildId}`;
    ApiLoader.loadPageData(this, endpoint_path);
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

<style scoped>

</style>
