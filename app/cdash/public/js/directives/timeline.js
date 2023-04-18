const timelineController =
  function TimelineChartController($http, $scope) {
    $scope.loading = true;

    query_parameters = {
      project: $scope.$parent.cdash.projectname,
      filterdata: $scope.$parent.cdash.filterdata,
    };
    if ($scope.$parent.cdash.hasOwnProperty('begin') &&
        $scope.$parent.cdash.hasOwnProperty('end')) {
      query_parameters.begin = $scope.$parent.cdash.begin;
      query_parameters.end = $scope.$parent.cdash.end;
    }
    else {
      query_parameters.date = $scope.$parent.cdash.date;
    }
    if ($scope.$parent.cdash.hasOwnProperty('buildgroup')) {
      $scope.buildgroup = $scope.$parent.cdash.buildgroup;
      query_parameters.buildgroup = $scope.buildgroup;
    }
    $http({
      url: 'api/v1/timeline.php',
      method: 'GET',
      params: query_parameters,
    }).then((s) => {
      $scope.timeline = s.data;
      $scope.error = false;
      $scope.finishSetup();
    }, (e) => {
      $scope.error = e.data;
    }).finally(() => {
      $scope.loading = false;
    });


    $scope.finishSetup = function() {
      if ($scope.timeline === undefined || $scope.timeline.length === 0) {
        return;
      }

      // Construct an array of timestamps corresponding to our nightly start times.
      const nightly_start_times = Object.keys($scope.timeline.time_to_date);
      // Convert from string to int.
      $scope.nightly_start_times = nightly_start_times.map((x) => {
        return Number(x);
      });

      nv.addGraph(() => {
        $scope.timechart = nv.models.stackedAreaChart()
          .x((d) => {
            return d[0];
          })
          .y((d) => {
            return d[1];
          })
          .interpolate('step-after')
          .margin({top: 30, right: 60, bottom: 30, left: 60})
          .rightAlignYAxis(false)
          .showControls(false)
          .showLegend(true)
          .showTotalInTooltip(false)
          .useInteractiveGuideline(true);

        if ($scope.timeline.hasOwnProperty('colors')) {
          $scope.timechart.color($scope.timeline.colors);
        }

        $scope.timechart.xAxis.showMaxMin(false);
        $scope.timechart_selection = d3.select('#timechart svg').datum($scope.timeline.data);
        $scope.timechart_selection.call($scope.timechart);

        $scope.timechart_selection
          .select('.nv-axislabel')
          .style('font-size', '16')
          .style('font-weight', 'bold');

        $scope.timechart.update();
        nv.utils.windowResize($scope.timechart.update);

        // Calculate how many ticks can comfortably fit on our X-axis.
        const bbox = d3.select('.nv-stackedAreaChart g rect').node().getBBox();
        const text_element = $scope.timechart_selection
          .append('text')
          .attr('class', 'nvd3')
          .text('2999-12-31')
          .style('visibility', 'hidden');
        const label_width = text_element.node().getBBox().width;
        text_element.remove();
        const num_ticks = Math.floor(Math.round(bbox.width) / (Math.ceil(label_width) * 2));

        // Extract that many evenly spaced dates for our X-axis tick values.
        const nightly_start_times = $scope.nightly_start_times.slice(0);
        // Don't show the final X-axis tick because it represents one day
        // past our specified range.
        nightly_start_times.pop();
        if (nightly_start_times.length > num_ticks) {
          // eslint-disable-next-line no-var
          var tick_values = [nightly_start_times[0]];
          const interval = nightly_start_times.length / num_ticks;
          for (let i = 1; i < num_ticks; i++) {
            tick_values.push(nightly_start_times[Math.round(i * interval)]);
          }
        }
        else {
          // eslint-disable-next-line no-var
          var tick_values = nightly_start_times;
        }

        // Format x-axis labels as dates.
        $scope.timechart.xAxis
          .showMaxMin(false)
          .tickValues(tick_values)
          .tickFormat((d) => {
            return $scope.timeline.time_to_date[d];
          });
        $scope.timechart.update();

        // Use d3.brush to allow the user to select a date range.
        $scope.timeline.brush = d3.svg.brush()
          .x($scope.timechart.xScale())
          .extent([$scope.timeline.extentstart, $scope.timeline.extentend])
          .on('brushstart', brushstart)
          .on('brushend', brushend);
        $scope.start_brushing = true;

        const brush_element = $scope.timechart_selection
          .select('.nv-areaWrap')
          .append('g')
          .attr('class', 'brush')
          .call($scope.timeline.brush)
          .selectAll('rect')
          .attr('height', bbox.height)
          .attr('fill-opacity', '.125')
          .attr('stroke', '#fff');

        // Remove the brush background so mouseover events get passed through
        // to the underlying chart. This makes it so our tooltips still work.
        brush_element.select('.background').remove();

        $scope.computeSelectedDateRange();

        function brushstart() {
          // Work around a d3 bug where brushstart() gets called before
          // AND after brushend().
          if (!$scope.start_brushing) {
            $scope.start_brushing = true;
            return;
          }

          // Hide tooltips while moving the brush.
          d3.select('.nvtooltip').style('display', 'none');
          $scope.start_brushing = false;
        }

        // Snap to day boundaries.
        function brushend() {
          if (!d3.event.sourceEvent) {
            return;
          } // only transition after input

          // Use binary search to round to the nearest day.
          function find_closest_time (input_time, valid_times) {
            let mid;
            let lo = 0;
            let hi = valid_times.length - 1;
            while (hi - lo > 1) {
              mid = Math.floor ((lo + hi) / 2);
              if (valid_times[mid] < input_time) {
                lo = mid;
              }
              else {
                hi = mid;
              }
            }
            if (input_time - valid_times[lo] <= valid_times[hi] - input_time) {
              return valid_times[lo];
            }
            return valid_times[hi];
          }
          const extent = $scope.timeline.brush.extent();
          const new_extent = [];
          new_extent[0] = find_closest_time(extent[0], $scope.nightly_start_times);
          new_extent[1] = find_closest_time(extent[1], $scope.nightly_start_times);

          // Don't go out of bounds.
          if (new_extent[0] < $scope.timeline.min) {
            new_extent[0] = $scope.timeline.min;
          }
          if (new_extent[1] > $scope.timeline.max) {
            new_extent[1] = $scope.timeline.max;
          }

          // eslint-disable-next-line eqeqeq
          const min_changed = extent[0] != new_extent[0];
          // eslint-disable-next-line eqeqeq
          const max_changed = extent[1] != new_extent[1];
          // Don't collapse the extent down to nothing.
          // At minimum it should span a single testing day.
          // eslint-disable-next-line eqeqeq
          if (new_extent[0] == new_extent[1]) {
            if (min_changed) {
              new_extent[0] = new_extent[1] - 1000 * 3600 * 24;
            }
            else if (max_changed) {
              new_extent[1] = new_extent[0] + 1000 * 3600 * 24;
            }
          }

          // Smoothly move the brush if necessary.
          // eslint-disable-next-line eqeqeq
          if (new_extent[0] != extent[0] || new_extent[1] != extent[1]) {
            d3.select(this).transition()
              .duration($scope.timeline.brush.empty() ? 0 : 750)
              .call($scope.timeline.brush.extent(new_extent))
              .call($scope.timeline.brush.event);
          }

          $scope.computeSelectedDateRange();
          d3.select('.nvtooltip').style('display', 'block');
        }

        return $scope.timechart;
      });
    };

    $scope.computeSelectedDateRange = function() {
      // Record our currently selected time range in terms of testing days.
      const extent = $scope.timeline.brush.extent();
      const timestamps = Object.keys($scope.timeline.time_to_date);

      // extent[0] and extent[1] should both already be set to timestamps that
      // represent testing day boundaries.  These boundaries are the keys of our
      // time_to_date object.  If our extent is found to hold invalid values
      // we record the beginning and/or end of our testing date range instead.
      if (timestamps.indexOf(String(extent[0])) === -1) {
        $scope.$parent.cdash.begin_date = $scope.timeline.time_to_date[Number(timestamps[0])];
      }
      else {
        $scope.$parent.cdash.begin_date = $scope.timeline.time_to_date[extent[0]];
      }

      // The end of our range is pointing at the end of the testing day.
      // We want to report the beginning of this day instead, so we grab the date
      // corresponding to the previous timestamp.
      idx = timestamps.indexOf(String(extent[1]));
      if (idx > 0) {
        $scope.$parent.cdash.end_date = $scope.timeline.time_to_date[timestamps[idx - 1]];
      }
      else {
        $scope.$parent.cdash.end_date = $scope.timeline.time_to_date[Number(timestamps[timestamps.length - 1])];
      }
    };

    $scope.updateSelection = function() {
      // Defer to the parent controller's implementation of this function
      // if one exists.
      if (typeof $scope.$parent.updateSelection === 'function') {
        return $scope.$parent.updateSelection();
      }

      let uri = `//${location.host}${location.pathname}?project=${$scope.cdash.projectname_encoded}`;

      if ($scope.hasOwnProperty('buildgroup')) {
        uri += `&buildgroup=${$scope.buildgroup}`;
      }

      // Include date range from time chart.
      // eslint-disable-next-line eqeqeq
      if ($scope.cdash.begin_date == $scope.cdash.end_date) {
        uri += `&date=${$scope.cdash.begin_date}`;
      }
      else {
        uri += `&begin=${$scope.cdash.begin_date}&end=${$scope.cdash.end_date}`;
      }

      window.location = uri;
    };
  };

CDash.directive('timeline', (VERSION) => {
  return {
    restrict: 'A',
    templateUrl: `build/views/partials/timeline_${VERSION}.html`,
    controller: timelineController,
  };
});
