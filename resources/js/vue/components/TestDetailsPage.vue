<template>
  <BuildSidebar
    :build-id="buildId"
    active-tab="tests"
  >
    <build-summary-card :build-id="buildId" />

    <loading-indicator :is-loading="!build || !test">
      <div
        id="executiontime"
        class="tw-flex tw-flex-row tw-gap-1"
      >
        <img
          :src="$baseURL + '/img/clock.png'"
          :title="'Average: ' + test.meanRunningTime + ', SD: ' + test.stdDevRunningTime"
        >
        <span class="builddateelapsed">
          {{ runningTime }}
        </span>
      </div>
      <br>

      <b>Test: </b>
      <a
        id="summary_link"
        class="tw-link tw-link-hover"
        :href="`${$baseURL}/queryTests.php?project=${build.project.name}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=${test.name}&date=${testingDay}`"
      >
        {{ test.name }}
      </a>
      <b :class="testStatusColorClass">
        ({{ testStatus }})
      </b>
      <br>

      <div v-if="build.updateStep?.revision">
        <b>Repository revision: </b>
        <a
          id="revision_link"
          class="tw-link tw-link-hover"
          :href="revisionUrl"
        >
          {{ build.updateStep.revision }}
        </a>
        <br>
      </div>

      <div v-if="test.details">
        <b>Test Details: </b>
        {{ test.details }}
        <br>
      </div>

      <div v-if="build.project.enableTestTiming">
        <br>
        <b>Test Timing: </b>
        <b :class="testTimeStatusColorClass">
          {{ testTimeStatus }}
        </b>
        <div v-if="test.timeStatusCategory !== 'PASSED'">
          This test took longer to complete ({{ runningTime }}) than the threshold allows ({{ runningTimeThreshold }}).
        </div>
      </div>

      <div v-if="test.labels.edges.length > 0">
        <div class="tw-flex tw-flex-row tw-flex-wrap tw-gap-2">
          <b>Labels:</b>
          <span
            v-for="{ node: label } in test.labels.edges"
            class="tw-badge tw-badge-outline tw-text-xs tw-text-neutral-500"
          >
            {{ label.text }}
          </span>
        </div>
      </div>

      <br>

      <!-- Display the measurements -->
      <table id="test_measurement_table">
        <tr v-if="compareImages.length > 0">
          <th class="measurement">
            Interactive Image
          </th>
          <td>
            <div class="je_compare">
              <img
                v-for="(image, index) in compareImages"
                :src="image.url"
                :alt="image.role"
                @load="index === 0 && initializeJeCompare()"
              >
            </div>
          </td>
        </tr>

        <tr v-for="{ node: image } in test.testImages.edges">
          <th class="measurement">
            {{ image.role }}
          </th>
          <td>
            <img
              :src="image.url"
              :alt="image.role"
            >
          </td>
        </tr>

        <tr v-for="measurement in measurements">
          <th class="measurement">
            {{ measurement.name }}
          </th>
          <td>
            {{ measurement.value }}
          </td>
        </tr>
        <tr v-for="file in files">
          <th class="measurement">
            {{ file.name }}
          </th>
          <td>
            <a
              class="cdash-link"
              :href="$baseURL + '/api/v1/testDetails.php?buildtestid=' + testId + '&fileid=' + file.fileid"
            >
              <img :src="$baseURL + '/img/package.png'">
            </a>
          </td>
        </tr>
        <tr v-for="link in links">
          <td>
            <a
              class="cdash-link"
              :href="link.value"
            >{{ link.name }}</a>
          </td>
        </tr>
      </table>
      <br>

      <!-- Show command line -->
      <div class="tw-flex tw-flex-row tw-gap-1">
        <img
          width="20"
          height="20"
          :src="$baseURL + '/img/console.png'"
        >
        <a
          id="commandlinelink"
          href="#"
          class="tw-link tw-link-hover"
          @click="showcommandline = !showcommandline"
        >
          <span v-show="!showcommandline">Show Command Line</span>
          <span v-show="showcommandline">Hide Command Line</span>
        </a>
      </div>
      <div v-if="showcommandline">
        <code-box
          id="commandline"
          :text="test.command"
        />
        <br>
      </div>

      <!-- Show environment variables -->
      <div
        v-if="environment"
        class="tw-flex tw-flex-row tw-gap-1"
      >
        <img
          width="20"
          height="20"
          :src="$baseURL + '/img/console.png'"
        >
        <a
          id="environmentlink"
          href="#"
          class="tw-link tw-link-hover"
          @click="showenvironment = !showenvironment"
        >
          <span v-show="!showenvironment">Show Environment</span>
          <span v-show="showenvironment">Hide Environment</span>
        </a>
      </div>
      <div v-if="showenvironment">
        <code-box
          id="environment"
          :text="environment"
        />
        <br>
      </div>

      <!-- Pull down menu to see the graphs -->
      <div class="tw-flex tw-flex-row tw-gap-1">
        <img
          width="20"
          height="20"
          :src="$baseURL + '/img/graph.png'"
        >
        <span>Display graphs:</span>
        <select
          id="GraphSelection"
          v-model="graphSelection"
          class="tw-select tw-select-bordered tw-select-xs"
          @change="displayGraph()"
        >
          <option value="">
            Select...
          </option>
          <option value="time">
            Test Time
          </option>
          <option value="status">
            Failing/Passing
          </option>
          <option v-for="measurement in numericMeasurements">
            {{ measurement.name }}
          </option>
        </select>
      </div>

      <a
        v-show="rawdatalink != ''"
        :href="rawdatalink"
        target="_blank"
      >
        View Graph Data as JSON
      </a>

      <!-- Graph -->
      <div
        v-show="showgraph && graphSelection !== 'status'"
        id="graph_holder"
      />
      <div id="tooltip" />

      <test-history-plot
        v-if="graphSelection === 'status'"
        :base-url="$baseURL"
        :project-id="build.project.id"
        :project-name="build.project.name"
        :test-name="test.name"
        :build-name="build.name"
      />

      <br>
      <b>Test Output</b>
      <code-box
        id="test_output"
        :text="test.output"
      />
      <br>

      <div v-for="preformattedMeasurement in preformattedMeasurements">
        <b>{{ preformattedMeasurement.name }}</b>
        <code-box :text="preformattedMeasurement.value" />
        <br>
      </div>
    </loading-indicator>
  </BuildSidebar>
</template>

<script>
import $ from 'jquery';
import ApiLoader from './shared/ApiLoader';
import {DateTime} from 'luxon';
import TestHistoryPlot from './shared/TestHistoryPlot.vue';
import CodeBox from './shared/CodeBox.vue';
import BuildSummaryCard from './shared/BuildSummaryCard.vue';
import LoadingIndicator from './shared/LoadingIndicator.vue';
import BuildSidebar from './shared/BuildSidebar.vue';
import gql from 'graphql-tag';
import {getRepository} from './shared/RepositoryIntegrations';
import Utils from './shared/Utils';

export default {
  name: 'TestDetails',

  components: {
    BuildSidebar,
    LoadingIndicator,
    BuildSummaryCard,
    CodeBox,
    TestHistoryPlot,
  },

  props: {
    buildId: {
      type: Number,
      required: true,
    },

    testId: {
      type: Number,
      required: true,
    },

    testingDay: {
      type: String,
      required: true,
    },
  },

  apollo: {
    build: {
      query: gql`
        query($id: ID) {
          build(id: $id) {
            id
            name
            updateStep {
              id
              revision
              priorRevision
            }
            project {
              id
              name
              enableTestTiming
              testTimeStdMultiplier
              vcsViewer
              vcsUrl
              cmakeProjectRoot
            }
          }
        }
      `,
      variables() {
        return {
          id: this.buildId,
        };
      },
    },

    test: {
      query: gql`
        query($id: ID) {
          test(id: $id) {
            id
            name
            status
            details
            command
            output
            runningTime
            meanRunningTime
            stdDevRunningTime
            timeStatusCategory
            testMeasurements {
              id
              name
              type
              value
            }
            labels {
              edges {
                node {
                  id
                  text
                }
              }
            }
            testImages {
              edges {
                node {
                  id
                  role
                  url
                }
              }
            }
          }
        }
      `,
      variables() {
        return {
          id: this.testId,
        };
      },
    },
  },

  data () {
    return {
      // API results.
      cdash: {},

      showcommandline: false,
      showenvironment: false,
      showgraph: false,
      graphSelection: '',
      rawdatalink: '',
      jeCompareInitialized: false,
    };
  },

  computed: {
    files() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type === 'file';
      });
    },

    links() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type === 'text/link';
      });
    },

    measurements() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type !== 'file'
          && measurement.type !== 'text/link'
          && measurement.type !== 'text/preformatted'
          && !(measurement.type === 'text/string' && measurement.name === 'Environment');
      });
    },

    numericMeasurements() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type.lastIndexOf('numeric/', 0) === 0;
      });
    },

    preformattedMeasurements() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type === 'text/preformatted';
      });
    },

    environment() {
      return this.test.testMeasurements.filter((measurement) => {
        return measurement.type === 'text/string' && measurement.name === 'Environment';
      })[0]?.value ?? null;
    },

    revisionUrl() {
      return getRepository(this.build.project.vcsViewer, this.build.project.vcsUrl, this.build.project.cmakeProjectRoot)
        ?.getCommitUrl(this.build.updateStep?.revision);
    },

    runningTime() {
      return Utils.formatDuration(this.test.runningTime * 1000);
    },

    runningTimeThreshold() {
      if (!this.test.runningTime || !this.test.meanRunningTime || !this.test.stdDevRunningTime) {
        return '';
      }

      const thresholdInSeconds = this.test.meanRunningTime + (this.build.project.testTimeStdMultiplier * this.test.stdDevRunningTime);
      return Utils.formatDuration(thresholdInSeconds * 1000);
    },

    testStatus() {
      switch (this.test.status) {
      case 'PASSED':
        return 'Passed';
      case 'FAILED':
        return 'Failed';
      case 'NOT_RUN':
        return 'Not Run';
      default:
        return this.test.status;
      }
    },

    /**
     * TODO: Convert these to Tailwind colors
     */
    testStatusColorClass() {
      switch (this.test.status) {
      case 'PASSED':
        return 'normal-text';
      case 'FAILED':
        return 'error-text';
      case 'NOT_RUN':
        return 'warning-text';
      default:
        return '';
      }
    },

    testTimeStatus() {
      switch (this.test.timeStatusCategory) {
      case 'PASSED':
        return 'Passed';
      case 'FAILED':
        return 'Warning';
      default:
        return this.test.timeStatusCategory;
      }
    },

    /**
     * TODO: Convert these to Tailwind colors
     */
    testTimeStatusColorClass() {
      switch (this.test.timeStatusCategory) {
      case 'PASSED':
        return 'normal-text';
      case 'FAILED':
        return 'warning-text';
      default:
        return '';
      }
    },

    compareImages() {
      if (!this.test || !this.test.testImages) {
        return [];
      }

      const images = [];

      this.test.testImages.edges.forEach(({ node: image }) => {
        if (image.role === 'ValidImage') {
          images.push(image);
        }
      });

      this.test.testImages.edges.forEach(({ node: image }) => {
        if (image.role === 'TestImage') {
          images.push(image);
        }
      });

      return images;
    },
  },

  async mounted () {
    // Ensure jQuery is globally available before loading plugins
    window.jQuery = $;
    await import('flot/dist/es5/jquery.flot');
    await import('../../angular/je_compare.js');
  },

  methods: {
    initializeJeCompare() {
      if (this.jeCompareInitialized) {
        return;
      }
      $('.je_compare').je_compare({caption: true});
      this.jeCompareInitialized = true;
    },

    postSetup: function() {
      if (this.graphSelection) {
        this.displayGraph();
      }
    },

    displayGraph: function() {
      if (history.pushState) {
        const graph_query = `?graph=${this.graphSelection}`;
        if (window.location.href.indexOf(graph_query) === -1) {
          // Update query string.
          const newurl = `${window.location.protocol}//${window.location.host}${window.location.pathname}${graph_query}`;
          window.history.pushState({path:newurl},'',newurl);

          ApiLoader.$emit('api-loaded', this.cdash);
        }
      }

      if (this.graphSelection === 'status') {
        // The passing/failing graph is special because it loads its own data and handles rendering itself.
        return;
      }

      const measurementname = this.graphSelection;
      if (this.graphSelection === '') {
        this.showgraph = false;
        $('#graph_options').html('');
        return;
      }

      this.showgraph = true;

      let graph_type = '';
      let endpoint_path = `/api/v1/testGraph.php?testname=${this.test.name}&buildid=${this.buildId}`;
      switch (this.graphSelection) {
      case 'status':
        graph_type = 'status';
        break;
      case 'time':
        graph_type = 'time';
        break;
      default:
        graph_type = 'measurement';
        endpoint_path += `&measurementname=${measurementname}`;
        break;
      }
      endpoint_path += `&type=${graph_type}`;
      this.rawdatalink = this.$baseURL + endpoint_path;

      this.$axios
        .get(endpoint_path)
        .then(response => {
          this.testGraph(response.data, graph_type);
        });
    },

    testGraph: function(response, graph_type) {
      // Isolate buildtestids from the actual data points.
      const buildtestids = {};
      const chart_data = [];
      for (let i = 0; i < response.length; i++) {
        const series = {};
        series.label = response[i].label;
        series.data = [];
        for (let j = 0; j < response[i].data.length; j++) {
          series.data.push([response[i].data[j]['x'], response[i].data[j]['y']]);
          if ('buildtestid' in response[i].data[j]) {
            buildtestids[response[i].data[j]['x']] = response[i].data[j]['buildtestid'];
          }
        }
        chart_data.push(series);
      }

      // Options that are shared by all of our different types of charts.
      const options = {
        grid: {
          backgroundColor: '#fffaff',
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4,
        },
        pan: { interactive: true },
        zoom: { interactive: true, amount: 1.1 },
        xaxis: {
          mode: 'time',
          timeformat: '%Y/%m/%d %H:%M',
          timeBase: 'milliseconds',
        },
        yaxis: {
          zoomRange: false,
          panRange: false,
        },
      };

      switch (graph_type) {
      case 'status':
        // Circles for passed tests, crosses for failed tests.
        chart_data[0].points = { symbol: 'circle'};
        chart_data[1].points = { symbol: 'cross'};
        options.series = {
          points: {
            show: true,
            radius: 5,
          },
        };
        options.yaxis.ticks = [[-1, 'Failed'], [1, 'Passed']];
        options.yaxis.min = -1.2;
        options.yaxis.max = 1.2;
        options.colors = ['#8aba5a', '#de6868'];
        break;

      case 'time':
        // Show threshold series as a filled area.
        chart_data[1].lines = { fill: true };
        // The lack of a 'break' here is intentional.
        // time & measurement charts share common options.
        // eslint-disable-next-line no-fallthrough
      case 'measurement':
        options.lines = { show: true };
        options.points = { show: true };
        options.colors = ['#0000FF', '#dba255', '#919733'];
        break;
      }

      // Navigate to other tests on click.
      const vm = this;
      $('#graph_holder').bind('plotclick', (e, pos, item) => {
        if (item) {
          const buildtestid = buildtestids[item.datapoint[0]];
          window.location = `${vm.$baseURL}/tests/${buildtestid}?graph=${vm.graphSelection}`;
        }
      });

      $.plot($('#graph_holder'), chart_data, options);

      // Show tooltip on hover.
      $('#graph_holder').bind('plothover', (event, pos, item) => {
        if (item) {
          const x = DateTime.fromMillis(item.datapoint[0]).toFormat('LLL dd, hh:mm:ss a');
          const y = item.datapoint[1].toFixed(2);

          $('#tooltip').html(
            `<b>${x}</b><br/>${
              item.series.label}: <b>${y}</b>`)
            .css({top: item.pageY+5, left: item.pageX+5})
            .fadeIn(200);
        }
        else {
          $('#tooltip').hide();
        }
      });
    },
  },
};
</script>
