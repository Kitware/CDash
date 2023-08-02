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
    <h3 class="center-text">
      Submissions parsed over the past {{ cdash.num_hours }} hours
    </h3>
    <div
      id="timechart"
      class="row"
    >
      <div class="col-md-12">
        <svg
          width="100%"
          style="height:375px"
        />
      </div>
    </div>
    <br>
    <p>
      Note: Detailed information about submission failures can be found in the CDash logs.<br>
      <span v-if="cdash.log_directory.length > 0">
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
export default {
  name: "SubmissionProcessingMonitor",

  data () {
    return {
      // API results.
      cdash: {},
      loading: true,
      errored: false,
    }
  },

  mounted () {
    ApiLoader.loadPageData(this, '/api/monitor');
  },

  methods: {
    postSetup: function (response) {
      var vm = this;
      nv.addGraph(function() {
        vm.timechart = nv.models.lineChart()
          .margin({top: 30, right: 60, bottom: 30, left: 60})
          .useInteractiveGuideline(true)
          .showLegend(true)
          .showYAxis(true)
          .showXAxis(true)
          .x(function(d) { return d[0] })
          .y(function(d) { return d[1] })
          .showLegend(true);

        vm.timechart.xAxis.showMaxMin(false);
        vm.timechart_selection = d3.select('#timechart svg').datum(vm.cdash.time_chart_data);
        vm.timechart_selection.call(vm.timechart);

        vm.timechart_selection
          .select(".nv-axislabel")
          .style('font-size', '16')
          .style('font-weight', 'bold');

        vm.timechart.update();
        nv.utils.windowResize(vm.timechart.update);

        // Only allow whole numbers as y-axis ticks.
        const yAxisTicks = vm.timechart.yScale().ticks().filter(Number.isInteger);
        vm.timechart.yAxis
          .tickValues(yAxisTicks)
          .tickFormat(d3.format('d'));

        // Format x-axis labels as time.
        vm.timechart.xAxis
          .showMaxMin(false)
          .tickValues(vm.cdash.ticks)
          .tickFormat(function(d) {
            const formatter = new Intl.DateTimeFormat("en-us", {
              month: "short",
              day: "numeric",
              hour: "numeric",
              minute: "numeric",
            });
            // We multiply by 1,000 to convert from epoch seconds to milliseconds
            // since that's what Javascript's Date() constructor expects.
            return formatter.format(new Date(d * 1000));
          });
        vm.timechart.update();
      });
    }
  },
}
</script>
