<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div v-else>
      <h4 v-if="cdash.build.site">
        Files changed on <a :href="$baseURL + '/viewSite.php?siteid=' + cdash.build.siteid">{{ cdash.build.site }}</a>
        ({{ cdash.build.buildname }}) as of {{ cdash.build.buildtime }}
      </h4>

      <div v-if="cdash.update.revision">
        <b>Revision: </b>
        <tt>
          <a
            v-if="cdash.update.revisionurl.length > 0"
            :href="cdash.update.revisionurl"
          >{{ cdash.update.revision }}</a>
        </tt>
        <tt v-if="cdash.update.revisionurl.length === 0">
          {{ cdash.update.revision }}
        </tt>
      </div>
      <div v-if="cdash.update.priorrevision">
        <b>Prior Revision: </b>
        <tt>
          <a
            v-if="cdash.update.revisiondiff.length > 0"
            :href="cdash.update.revisiondiff"
          >{{ cdash.update.priorrevision }}</a>
        </tt>
        <tt
          v-if="cdash.update.revisiondiff.length === 0"
          :href="cdash.update.revisiondiff"
        >
          {{ cdash.update.priorrevision }}
        </tt>
      </div>

      <a @click="toggleGraph()">
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
      <br>

      <h3
        v-if="cdash.update.status"
        class="error"
      >
        {{ cdash.update.status }}
      </h3>

      <div v-for="group in cdash.updategroups">
        <div class="container-fluid">
          <div class="row">
            <div
              class="col-md-12"
              @click="group.hidden = !group.hidden; $forceUpdate()"
            >
              <span
                class="glyphicon"
                :class="group.hidden ? 'glyphicon-chevron-right' : 'glyphicon-chevron-down'"
              />
              <b>{{ group.description }}</b>
            </div>
          </div>
          <div
            v-for="directory in group.directories"
            v-show="!group.hidden"
            class="animate-show"
          >
            <div class="row">
              <div
                class="col-md-12 col-md-offset-1"
                @click="directory.hidden = !directory.hidden; $forceUpdate()"
              >
                <span
                  class="glyphicon"
                  :class="directory.hidden ? 'glyphicon-chevron-right' : 'glyphicon-chevron-down'"
                />
                <tt>{{ directory.name }}</tt>
              </div>
            </div>
            <div
              v-for="file in directory.files"
              v-show="!directory.hidden"
              class="animate-show"
            >
              <div class="row">
                <div class="col-md-12 col-md-offset-2">
                  <a
                    v-if="file.diffurl"
                    :href="file.diffurl"
                  >
                    <tt>{{ file.filename }}</tt> Revision: <tt>{{ file.revision }}</tt>
                  </a>
                  <span v-else>
                    <tt>{{ file.filename }}</tt> Revision: <tt>{{ file.revision }}</tt>
                  </span>
                  <span v-if="file.author">
                    by
                    <a
                      v-if="file.email"
                      :href="'mailto:' + file.email"
                    >
                      {{ file.author }}
                    </a>
                    <span v-else>
                      {{ file.author }}
                    </span>
                  </span>
                </div>
              </div>
              <div class="row spacer-bottom">
                <pre
                  class="col-md-10 col-md-offset-2"
                  style="white-space: pre-wrap;"
                >{{ file.log }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';

export default {
  name: "BuildUpdate",

  data() {
    return {
      // API results.
      buildid: null,
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
        'tests': false
      },
    }
  },

  mounted() {
    this.buildid = window.location.pathname.split("/").at(-2);
    const endpoint_path = '/api/v1/viewUpdate.php?buildid=' + this.buildid;
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
        .get('/api/v1/buildUpdateGraph.php?buildid=' + this.buildid)
        .then(response => {
          this.initializeGraph(response.data);
          this.graphLoaded = true;
        })
        .finally(() => this.graphLoading = false)
    },

    initializeGraph: function(data) {
      const options = {
        lines: {show: true},
        points: {show: true},
        xaxis: {mode: "time"},
        grid: {
          backgroundColor: "#fffaff",
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4
        },
        selection: {mode: "x"},
        colors: ["#0000FF", "#dba255", "#919733"],
      };

      let plot = $.plot($("#graph_holder"), [{label: "Number of changed files", data: data.data}], options);

      $("#graph_holder").bind("selected", function (event, area) {
        plot = $.plot($("#graph_holder"), [{
          label: "Number of changed files",
          data: data.data
        }], $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
      });

      let baseURL = this.$baseURL;
      $("#graph_holder").bind("plotclick", function (e, pos, item) {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          window.location = baseURL + '/build/' + data.buildids[item.datapoint[0]];
        }
      });
    },

    toggleGroup: function(group_index) {
      this.cdash.updategroups[group_index].hidden = !this.cdash.updategroups[group_index].hidden;
      console.log(this.cdash.updategroups[group_index].hidden);
    },
  },
}
</script>

<style scoped>

</style>
