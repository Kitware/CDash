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
      <!-- Display link to create bug tracker issue if supported. -->
      <div v-if="cdash.newissueurl">
        <a :href="cdash.newissueurl">
          <b>Create {{ cdash.bugtracker }} issue for this build</b>
        </a>
        <br>
      </div>

      <!-- Build log for a single submission -->
      <br><br>
      <b>Site Name: </b>
      <a
        id="site_link"
        :href="$baseURL + '/viewSite.php?siteid=' + cdash.build.siteid"
      >
        {{ cdash.build.site }}
      </a>
      <br>

      <b>Build Name: </b>{{ cdash.build.name }}
      <div v-if="cdash.build.note">
        (<a :href="$baseURL + '/build/' + cdash.build.id + '/notes'">view notes</a>)
      </div>
      <br>

      <b>Stamp: </b>{{ cdash.build.stamp }}
      <br>

      <b>Time: </b>{{ cdash.build.time }}
      <br>

      <b>Type: </b>{{ cdash.build.type }}
      <br>

      <!-- Display Operating System information  -->
      <div v-if="cdash.build.osname">
        <br><b>OS Name: </b>{{ cdash.build.osname }}
      </div>
      <div v-if="cdash.build.osplatform">
        <br><b>OS Platform: </b>{{ cdash.build.osplatform }}
      </div>
      <div v-if="cdash.build.osrelease">
        <br><b>OS Release: </b>{{ cdash.build.osrelease }}
      </div>
      <div v-if="cdash.build.osversion">
        <br><b>OS Version: </b>{{ cdash.build.osversion }}
      </div>

      <!-- Display Compiler information  -->
      <div v-if="cdash.build.compilername">
        <br><b>Compiler Name: </b>{{ cdash.build.compilername }}
      </div>
      <div v-if="cdash.build.compilerversion">
        <br><b>Compiler Version: </b>{{ cdash.build.compilerversion }}
      </div>

      <div v-if="cdash.build.generator">
        <br><b>CTest version: </b>{{ cdash.build.generator }}
      </div>

      <div v-if="cdash.build.lastsubmitbuild > 0">
        <p /><b>Last submission: </b>
        <a :href="$baseURL + '/build/' + cdash.build.lastsubmitbuild">
          {{ cdash.build.lastsubmitdate }}
        </a>
      </div>
      <br><br>

      <table>
        <tr>
          <td>
            <table class="dart">
              <tr class="table-heading">
                <th colspan="3">
                  Current Build
                </th>
              </tr>
              <tr class="table-heading">
                <th>Stage</th>
                <th>Errors</th>
                <th>Warnings</th>
              </tr>
              <tr class="tr-odd">
                <td>
                  <a
                    v-if="cdash.hasupdate"
                    href="#Update"
                  >
                    <b>Update</b>
                  </a>
                  <span v-if="!cdash.hasupdate">
                    <b>Update</b>
                  </span>
                </td>
                <td
                  align="right"
                  :class="cdash.update.nerrors > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewUpdate.php?buildid=' + cdash.build.id">
                      {{ cdash.update.nerrors }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.update.nwarnings > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewUpdate.php?buildid=' + cdash.build.id">
                      {{ cdash.update.nwarnings }}
                    </a>
                  </b>
                </td>
              </tr>
              <tr
                v-if="cdash.hasconfigure"
                class="tr-even"
              >
                <td>
                  <a href="#Configure">
                    <b>Configure</b>
                  </a>
                </td>
                <td
                  align="right"
                  :class="cdash.configure.nerrors > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/build/' + cdash.build.id + '/configure'">
                      {{ cdash.configure.nerrors }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.configure.nwarnings > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/build/' + cdash.build.id + '/configure'">
                      {{ cdash.configure.nwarnings }}
                    </a>
                  </b>
                </td>
              </tr>
              <tr class="tr-odd">
                <td>
                  <a href="#Build">
                    <b>Build</b>
                  </a>
                </td>
                <td
                  align="right"
                  :class="cdash.build.nerrors > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewBuildError.php?buildid=' + cdash.build.id">
                      {{ cdash.build.nerrors }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.build.nwarnings > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewBuildError.php?type=1&buildid=' + cdash.build.id">
                      {{ cdash.build.nwarnings }}
                    </a>
                  </b>
                </td>
              </tr>
              <tr class="tr-even">
                <td><a href="#Test"><b>Test</b></a></td>
                <td
                  align="right"
                  :class="cdash.test.nfailed > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewTest.php?onlyfailed&buildid=' + cdash.build.id">
                      {{ cdash.test.nfailed }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.test.nnotrun > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewTest.php?onlynotrun&buildid=' + cdash.build.id">
                      {{ cdash.test.nnotrun }}
                    </a>
                  </b>
                </td>
              </tr>
            </table>
          </td>
          <td>
            <!-- Previous build -->
            <table
              v-if="cdash.previousbuild"
              class="dart"
            >
              <tr class="table-heading">
                <th colspan="3">
                  <a :href="$baseURL + '/build/' + cdash.previousbuild.buildid">
                    Previous Build
                  </a>
                </th>
              </tr>
              <tr class="table-heading">
                <th>Stage</th>
                <th>Errors</th>
                <th>Warnings</th>
              </tr>
              <tr class="tr-odd">
                <td><b>Update</b></td>
                <td
                  align="right"
                  :class="cdash.previousbuild.nupdateerrors > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewUpdate.php?buildid=' + cdash.previousbuild.buildid">
                      {{ cdash.previousbuild.nupdateerrors }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.previousbuild.nupdatewarnings > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewUpdate.php?buildid=' + cdash.previousbuild.buildid">
                      {{ cdash.previousbuild.nupdatewarnings }}
                    </a>
                  </b>
                </td>
              </tr>

              <tr
                v-if="cdash.hasconfigure"
                class="tr-even"
              >
                <td><b>Configure</b></td>
                <td
                  align="right"
                  :class="cdash.previousbuild.nconfigureerrors > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/build/' + cdash.previousbuild.buildid + '/configure'">
                      {{ cdash.previousbuild.nconfigureerrors }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.previousbuild.nconfigurewarnings > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/build/' + cdash.previousbuild.buildid + '/configure'">
                      {{ cdash.previousbuild.nconfigurewarnings }}
                    </a>
                  </b>
                </td>
              </tr>

              <tr class="tr-odd">
                <td><b>Build</b></td>
                <td
                  align="right"
                  :class="cdash.previousbuild.nerrors > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewBuildError.php?buildid=' + cdash.previousbuild.buildid">
                      {{ cdash.previousbuild.nerrors }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.previousbuild.nwarnings > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewBuildError.php?type=1&buildid=' + cdash.previousbuild.buildid">
                      {{ cdash.previousbuild.nwarnings }}
                    </a>
                  </b>
                </td>
              </tr>

              <tr class="tr-even">
                <td><b>Test</b></td>
                <td
                  align="right"
                  :class="cdash.previousbuild.ntestfailed > 0 ? 'error' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewTest.php?onlyfailed&buildid=' + cdash.previousbuild.buildid">
                      {{ cdash.previousbuild.ntestfailed }}
                    </a>
                  </b>
                </td>
                <td
                  align="right"
                  :class="cdash.previousbuild.ntestnotrun > 0 ? 'warning' : 'normal'"
                >
                  <b>
                    <a :href="$baseURL + '/viewTest.php?onlynotrun&buildid=' + cdash.previousbuild.buildid">
                      {{ cdash.previousbuild.ntestnotrun }}
                    </a>
                  </b>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
      <br>

      <!-- Display the history table -->
      <div class="title-divider">
        History
      </div>
      <a
        id="toggle_history_graph"
        @click="toggleHistoryGraph()"
      >
        Show Build History
      </a>
      <br>

      <a
        id="history_link"
        :href="$baseURL + '/index.php?project=' + cdash.projectname_encoded + '&filtercount=4&showfilters=1&filtercombine=and&field1=site&compare1=61&value1=' + cdash.build.sitename_encoded + '&field2=buildname&compare2=61&value2=' + cdash.build.name + '&field3=buildtype&compare3=61&value3=' + cdash.build.type + '&field4=buildstarttime&compare4=84&value4=' + cdash.build.starttime"
      >
        Build History Filter
      </a>
      <br>

      <div>
        <img
          v-show="showHistoryGraph && graphLoading"
          :src="$baseURL + '/img/loading.gif'"
        >
        <table
          v-show="showHistoryGraph && !graphLoading"
          id="historyGraph"
          width="100%"
          border="0"
        >
          <tr>
            <th>Start Time</th>
            <th>Updated Files</th>
            <th>Configure Errors</th>
            <th>Configure Warnings</th>
            <th>Build Errors</th>
            <th>Build Warnings</th>
            <th>Failed Tests</th>
          </tr>
          <tr
            v-for="(build, index) in cdash.buildhistory"
            :key="build.id"
            :class="{'even': index % 2 === 0, 'odd': index % 2 !== 0 }"
          >
            <td>
              <a :href="$baseURL + '/build/' + build.id">
                {{ build.starttime }}
              </a>
            </td>
            <td>
              {{ build.nfiles }}
            </td>
            <td :class="build.configureerrors > 0 ? 'error' : 'normal'">
              {{ build.configureerrors }}
            </td>
            <td :class="build.configurewarnings > 0 ? 'warning' : 'normal'">
              {{ build.configurewarnings }}
            </td>
            <td :class="build.builderrors > 0 ? 'error' : 'normal'">
              {{ build.builderrors }}
            </td>
            <td :class="build.buildwarnings > 0 ? 'warning' : 'normal'">
              {{ build.buildwarnings }}
            </td>
            <td :class="build.testfailed > 0 ? 'error' : 'normal'">
              {{ build.testfailed }}
            </td>
          </tr>
        </table>
      </div>
      <br>

      <!-- Display notes for that build -->
      <div
        v-if="cdash.notes.length > 0 || cdash.user.id > 0"
        class="title-divider"
      >
        Notes
      </div>

      <div v-if="cdash.notes.length > 0">
        <div class="title-divider">
          Users notes ({{ cdash.notes.length }})
        </div>
        <div v-for="note in cdash.notes">
          <b>{{ note.status }}</b> by <b>{{ note.user }}</b> at {{ note.date }}
          <pre>{{ note.text }}</pre>
          <hr>
        </div>
      </div>


      <div v-if="cdash.user.id > 0">
        <!-- Add Notes -->
        <a
          id="toggle_note"
          @click="toggleNote()"
        >
          Add a Note to this Build
        </a>
        <div
          v-show="showNote"
          id="new_note_div"
        >
          <table>
            <tr>
              <td><b>Note:</b></td>
              <td>
                <textarea
                  id="note_text"
                  v-model="cdash.noteText"
                  cols="50"
                  rows="5"
                />
              </td>
            </tr>
            <tr>
              <td><b>Status:</b></td>
              <td>
                <select
                  id="note_status"
                  v-model="cdash.noteStatus"
                >
                  <option value="0">
                    Simple Note
                  </option>
                  <option value="1">
                    Fix in progress
                  </option>
                  <option value="2">
                    Fixed
                  </option>
                </select>
              </td>
            </tr>
            <tr>
              <td />
              <td>
                <input
                  id="add_note"
                  type="submit"
                  value="Add Note"
                  :disabled="!cdash.noteText"
                  @click="addNote()"
                >
              </td>
            </tr>
          </table>
        </div>
        <br>
      </div>

      <!-- Graphs -->
      <div class="title-divider">
        Graphs
      </div>

      <img
        :src="$baseURL + '/img/graph.png'"
        title="graph"
      >
      <a
        id="toggle_time_graph"
        @click="toggleTimeGraph()"
      >
        Show Build Time Graph
      </a>
      <center>
        <img
          v-show="showTimeGraph && graphLoading"
          :src="$baseURL + '/img/loading.gif'"
        >
        <div
          v-show="showTimeGraph"
          id="buildtimegrapholder"
          class="graph_holder"
        />
      </center>

      <img
        :src="$baseURL + '/img/graph.png'"
        title="graph"
      >
      <a
        id="toggle_error_graph"
        @click="toggleErrorGraph()"
      >
        Show Build Errors Graph
      </a>
      <center>
        <img
          v-show="showErrorGraph && graphLoading"
          :src="$baseURL + '/img/loading.gif'"
        >
        <div
          v-show="showErrorGraph"
          id="builderrorsgrapholder"
          class="graph_holder"
        />
      </center>

      <img
        :src="$baseURL + '/img/graph.png'"
        title="graph"
      >
      <a
        id="toggle_warning_graph"
        @click="toggleWarningGraph()"
      >
        Show Build Warnings Graph
      </a>
      <center>
        <img
          v-show="showWarningGraph && graphLoading"
          :src="$baseURL + '/img/loading.gif'"
        >
        <div
          v-show="showWarningGraph"
          id="buildwarningsgrapholder"
          class="graph_holder"
        />
      </center>

      <img
        :src="$baseURL + '/img/graph.png'"
        title="graph"
      >
      <a
        id="toggle_test_graph"
        @click="toggleTestGraph()"
      >
        Show Build Tests Failed Graph
      </a>
      <center>
        <img
          v-show="showTestGraph && graphLoading"
          :src="$baseURL + '/img/loading.gif'"
        >
        <div
          v-show="showTestGraph"
          id="buildtestsfailedgrapholder"
          class="graph_holder"
        />
      </center>
      <br>

      <!-- Relationships -->
      <div v-if="cdash.hasrelationships">
        <div class="title-divider">
          Relationships
        </div>
        <div
          v-for="from in cdash.relationships_from"
          :key="from.relatedid"
        >
          This build {{ from.relationship }} <a :href="$baseURL + '/build/' + from.relatedid">{{ from.name }}</a>.
        </div>
        <div
          v-for="to in cdash.relationships_to"
          :key="to.buildid"
        >
          <a :href="$baseURL + '/build/' + to.buildid">{{ to.name }}</a> {{ to.relationship }} this build.
        </div>
      </div>

      <!-- Update -->
      <div v-if="cdash.hasupdate">
        <div
          id="Update"
          class="title-divider"
        >
          Stage: Update ({{ cdash.update.nerrors }} errors, {{ cdash.update.nwarnings }} warnings)
        </div>
        <br>

        <b>Start Time: </b>{{ cdash.update.starttime }}
        <br>

        <b>End Time: </b>{{ cdash.update.endtime }}
        <br>

        <b>Update Command: </b> {{ cdash.update.command }}
        <br>

        <b>Update Type: </b> {{ cdash.update.type }}
        <br>

        <b>Number of Updates: </b>
        <a
          id="update_link"
          :href="$baseURL + '/viewUpdate.php?buildid=' + cdash.build.id"
        >
          {{ cdash.update.nupdates }}
        </a>
        <div v-if="cdash.update.status">
          <br>
          <b>Update Status: </b>{{ cdash.update.status }}
        </div>
        <br>
        <br>
      </div>

      <!-- Configure -->
      <div v-if="cdash.hasconfigure">
        <div
          id="Configure"
          class="title-divider"
        >
          Configure ({{ cdash.configure.nerrors }} errors, {{ cdash.configure.nwarnings }} warnings)
        </div>
        <br>

        <b>Start Time: </b>{{ cdash.configure.starttime }}
        <br>

        <b>End Time: </b>{{ cdash.configure.endtime }}
        <br>

        <b>Configure Command: </b> {{ cdash.configure.command }}
        <br>

        <b>Configure Return Value: </b> {{ cdash.configure.status }}
        <br>

        <b>Configure Output: </b>
        <br>

        <pre>{{ cdash.configure.output }}</pre>
        <br>

        <a
          id="configure_link"
          :href="$baseURL + '/build/' + cdash.build.id + '/configure'"
        >
          View Configure Summary
        </a>
        <br>
        <br>
      </div>

      <!-- Build -->
      <div
        id="Build"
        class="title-divider"
      >
        Build ({{ cdash.build.nerrors }} errors, {{ cdash.build.nwarnings }} warnings)
      </div>
      <br>

      <b>Build command: </b><tt>{{ cdash.build.command }}</tt>
      <br>

      <b>Start Time: </b>{{ cdash.build.starttime }}
      <br>

      <b>End Time: </b>{{ cdash.build.endtime }}
      <br>
      <br>

      <!-- Show the errors -->
      <div v-for="error in cdash.build.errors">
        <div v-if="error.sourceline > 0">
          <hr>
          <h3>
            <a>Build Log line {{ error.logline }}</a>
          </h3>
          <br>
          File: <b>{{ error.sourcefile }}</b>
          Line: <b>{{ error.sourceline }}</b>
        </div>
        <pre>{{ error.precontext }}</pre>
        <pre>{{ error.text }}</pre>
        <pre>{{ error.postcontext }}</pre>

        <div v-if="error.stdoutput || error.stderror">
          <br>
          <b>{{ error.sourcefile }}</b>
          <pre v-if="error.stdoutput">{{ error.stdoutput }}</pre>
          <pre v-if="error.stderror">{{ error.stderror }}</pre>
        </div>
      </div>

      <a
        id="errors_link"
        :href="$baseURL + '/viewBuildError.php?buildid=' + cdash.build.id"
      >
        View Errors Summary
      </a>
      <br>
      <br>

      <!--  Warnings -->
      <div
        id="Warnings"
        class="title-divider"
      >
        Build Warnings ({{ cdash.build.nwarnings }})
      </div>

      <div v-for="warning in cdash.build.warnings">
        <div v-if="warning.sourceline > 0">
          <hr>
          <h3><a>Build Log line {{ warning.logline }}</a></h3>
          <br>
          File: <b>{{ warning.sourcefile }}</b>
          Line: <b>{{ warning.sourceline }}</b>
        </div>
        <pre>{{ warning.precontext }}</pre>
        <pre>{{ warning.text }}</pre>
        <pre>{{ warning.postcontext }}</pre>

        <div v-if="warning.stdoutput || warning.stderror">
          <br>
          <b>{{ warning.sourcefile }}</b>
          <pre v-if="warning.stdoutput">{{ warning.stdoutput }}</pre>
          <pre v-if="warning.stderror">{{ warning.stderror }}</pre>
        </div>
      </div>
      <br>

      <a
        id="warnings_link"
        :href="$baseURL + '/viewBuildError.php?type=1&buildid=' + cdash.build.id"
      >
        View Warnings Summary
      </a>
      <br>
      <br>

      <!-- Test -->
      <div
        id="Test"
        class="title-divider"
      >
        Test ({{ cdash.test.npassed }}  passed, {{ cdash.test.nfailed }} failed, {{ cdash.test.nnotrun }} not run)
      </div>
      <a
        id="tests_link"
        :href="$baseURL + '/viewTest.php?buildid=' + cdash.build.id"
      >
        View Tests Summary
      </a>
      <br>
      <br>

      <!-- Coverage -->
      <div v-if="cdash.hascoverage">
        <div
          id="Coverage"
          class="title-divider"
        >
          Coverage ({{ cdash.coverage }}%)
        </div>
        <a
          id="coverage_link"
          :href="$baseURL + '/viewCoverage.php?buildid=' + cdash.build.id"
        >
          View Coverage Summary
        </a>
        <br>
        <br>
      </div>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
export default {
  name: "BuildSummary",

  data () {
    return {
      // API results.
      buildid: null,
      cdash: {},
      loading: true,
      errored: false,

      // Booleans controlling whether a section should be displayed or not.
      showErrorGraph: false,
      showHistoryGraph: false,
      showTestGraph: false,
      showTimeGraph: false,
      showWarningGraph: false,
      showNote: false,

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

  mounted () {
    this.buildid = window.location.pathname.split("/").pop();
    var endpoint_path = '/api/v1/buildSummary.php?buildid=' + this.buildid;
    ApiLoader.loadPageData(this, endpoint_path);
  },

  methods: {
    postSetup: function (response) {
      this.cdash.noteStatus = "0";
    },

    toggleHistoryGraph: function () {
      this.showHistoryGraph = !this.showHistoryGraph;
      this.loadGraphData();
    },

    loadGraphData: function(graphType) {
      this.graphLoading = true;
      this.$axios
        .get('/api/v1/getPreviousBuilds.php?buildid=' + this.buildid)
        .then(response => {
          this.cdash.buildtimes = [];
          this.cdash.builderrors = [];
          this.cdash.buildwarnings = [];
          this.cdash.testfailed = [];
          this.cdash.buildids = [];
          this.cdash.buildhistory = [];

          // Isolate data for each graph.
          var builds = response.data['builds'];
          for (var i = 0, len = builds.length; i < len; i++) {
            var build = builds[i];
            var t = build['timestamp'];

            this.cdash.buildtimes.push([t, build['time'] / 60]);
            this.cdash.builderrors.push([t, build['builderrors']]);
            this.cdash.buildwarnings.push([t, build['buildwarnings']]);
            this.cdash.testfailed.push([t, build['testfailed']]);
            this.cdash.buildids[t] = build['id'];

            var history_build = [];
            history_build['id'] = build['id'];
            history_build['nfiles'] = build['nfiles'];
            history_build['configureerrors'] = build['configureerrors'];
            history_build['configurewarnings'] = build['configurewarnings'];
            history_build['builderrors'] = build['builderrors'];
            history_build['buildwarnings'] = build['buildwarnings'];
            history_build['testfailed'] = build['testfailed'];
            history_build['starttime'] = build['starttime'];
            this.cdash.buildhistory.push(history_build);
          }
          this.graphLoaded = true;
          if (graphType) {
            // Render the graph that triggered this call.
            this.renderGraph(graphType);
          }
        })
        .finally(() => this.graphLoading = false)
    },

    renderGraph: function (graphType) {
      if (this.graphRendered[graphType]) {
        // Already rendered, abort early.
        return;
      }

      // Options shared by all four graphs.
      var data, element, label;
      var options = {
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
      };

      switch (graphType) {
      case 'time':
        options['colors'] = ["#41A317"];
        options['yaxis'] = {
          tickFormatter: function (v, axis) {
            return v.toFixed(axis.tickDecimals) + " mins"}
        };
        data = this.cdash.buildtimes;
        element = "#buildtimegrapholder";
        label = "Build Time";
        break;
      case 'errors':
        options['colors'] = ["#FF0000"];
        options['yaxis'] = {minTickSize: 1};
        data = this.cdash.builderrors;
        element = "#builderrorsgrapholder";
        label = "# errors";
        break;
      case 'warnings':
        options['colors'] = ["#FDD017"];
        options['yaxis'] = {minTickSize: 1};
        data = this.cdash.buildwarnings;
        element = "#buildwarningsgrapholder";
        label = "# warnings";
        break;
      case 'tests':
        options['colors'] = ["#0000FF"];
        options['yaxis'] = {minTickSize: 1};
        data = this.cdash.testfailed;
        element = "#buildtestsfailedgrapholder";
        label = "# tests failed";
        break;
      default:
        return;
      }

      // Render the graph.
      var plot = $.plot($(element), [{label: label, data: data}],
        options);

      $(element).bind("selected", function (event, area) {
        // Set axis range to highlighted section and redraw plot.
        var axes = plot.getAxes(),
          xaxis = axes.xaxis.options;
        xaxis.min = area.x1;
        xaxis.max = area.x2;
        plot.clearSelection();
        plot.setupGrid();
        plot.draw();
      });

      var vm = this;
      $(element).bind("plotclick", function (e, pos, item) {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          var buildid = vm.cdash.buildids[item.datapoint[0]];
          window.location = vm.$baseURL + "/build/" + buildid;
        }
      });

      $(element).bind('dblclick', function(event) {
        // Set axis range to null.  This makes all data points visible.
        var axes = plot.getAxes(),
          xaxis = axes.xaxis.options,
          yaxis = axes.yaxis.options;
        xaxis.min = null;
        xaxis.max = null;
        yaxis.min = null;
        yaxis.max = null;

        // Redraw the plot.
        plot.setupGrid();
        plot.draw();
      });

      this.graphRendered[graphType] = true;
    },

    // Show/hide our various history graphs.
    toggleTimeGraph: function() {
      this.showTimeGraph = !this.showTimeGraph;
      // Use a 1 ms timeout before loading graph data.
      // This gives the holder div a chance to become visible before the graph
      // is drawn.  Otherwise flot has trouble drawing the graph with the
      // correct dimensions.
      setTimeout(function () {
        if (!this.graphLoaded) {
          this.loadGraphData('time');
        } else {
          this.renderGraph('time');
        }
      }.bind(this), 1);
    },

    toggleErrorGraph: function() {
      this.showErrorGraph = !this.showErrorGraph;
      setTimeout(function () {
        if (!this.graphLoaded) {
          this.loadGraphData('errors');
        } else {
          this.renderGraph('errors');
        }
      }.bind(this), 1);
    },

    toggleWarningGraph: function() {
      this.showWarningGraph = !this.showWarningGraph;
      setTimeout(function () {
        if (!this.graphLoaded) {
          this.loadGraphData('warnings');
        } else {
          this.renderGraph('warnings');
        }
      }.bind(this), 1);
    },

    toggleTestGraph: function() {
      this.showTestGraph = !this.showTestGraph;
      setTimeout(function () {
        if (!this.graphLoaded) {
          this.loadGraphData('tests');
        } else {
          this.renderGraph('tests');
        }
      }.bind(this), 1);
    },

    toggleNote: function() {
      this.showNote = !this.showNote;
    },

    addNote: function() {
      this.$axios
        .post('api/v1/addUserNote.php', {
          buildid: this.cdash.build.id,
          Status: this.cdash.noteStatus,
          AddNote: this.cdash.noteText
        })
        .then(response => {
        // Add the newly created note to our list.
          this.cdash.notes.push(response.data.note);
        })
        .catch(error => {
        // Display the error.
          this.cdash.error = error;
          console.log(error)
        });
    },

  },
}
</script>

<style scoped>
.dart th, .dart td {
  padding: 3px 7px;
}
</style>
