<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <h4>
      There are currently {{ cdash.backlog_length }} submissions waiting to be parsed.
    </h4>
    <h4 v-if="cdash.backlog_length > 0">
      The oldest submission was created {{ cdash.backlog_time }}.
    </h4>
    <div v-if="plot_data && cdash.time_chart_data" class="center-text">
       <TimelinePlot
         :plotData="plot_data"
         :title="cdash.time_chart_data.title"
         :xLabel="cdash.time_chart_data.xLabel"
         :yLabel="cdash.time_chart_data.yLabel"
       />
    </div>
    <br>
    <p>
      Note: Detailed information about submission failures can be found in the CDash logs.<br>
      <span v-if="'log_directory' in cdash">
        Log files can be found in: <tt>{{ cdash.log_directory }}</tt>
      </span>
      <span v-else>
        This CDash instance uses a non-standard logging configuration.  Check your <tt>LOG_CHANNEL</tt>
        environment setting for more information.
      </span>
    </p>
  </section>
</template>
<script>
import ApiLoader from './shared/ApiLoader';
import TimelinePlot from './shared/TimelinePlot';
export default {
  name: "SubmissionProcessingMonitor",

  components: {
    TimelinePlot,
  },

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
      plot_data: {},
    }
  },

  mounted () {
    ApiLoader.loadPageData(this, '/api/monitor');
  },

  methods: {
    postSetup: function() {
      const api_data = this.cdash.time_chart_data.data;

      // perform data marshalling before sending data to plot template
      const formatted_data = [];
      for (let i = 0; i < api_data.length; i++) {
        formatted_data[i] = {
          color: api_data[i].color,
          name: api_data[i].name,
          values: api_data[i].values.map((d) => {
            // converts UNIX epoch format from API to JS date object
            return { x: new Date(d[0]*1000), y: d[1] };
          })
        };
      }
      this.plot_data = formatted_data;
    }
  },
}
</script>
