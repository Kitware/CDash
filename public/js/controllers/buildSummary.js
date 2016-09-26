CDash.controller('BuildSummaryController',
  function BuildSummaryController($scope, $rootScope, $http, $location, $timeout, anchors, renderTimer) {
    // Support for the various graphs on this page.
    $scope.showTimeGraph = false;
    $scope.showErrorGraph = false;
    $scope.showWarningGraph = false;
    $scope.showTestGraph = false;
    $scope.showHistoryGraph = false;
    $scope.showNote = false;
    $scope.graphLoading = false;
    $scope.graphLoaded = false;
    $scope.graphData = [];
    $scope.graphRendered = {
      'time': false,
      'errors': false,
      'warnings': false,
      'tests': false
    };

    $scope.loading = true;
    $http({
      url: 'api/v1/buildSummary.php',
      method: 'GET',
      params: $rootScope.queryString
    }).then(function success(s) {
      renderTimer.initialRender($scope, s.data);
      $scope.cdash.noteStatus = "0";
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = $scope.cdash.title;
      $scope.loading = false;

      // Expose the jumpToAnchor function to the scope.
      // This allows us to call it from the HTML template.
      $scope.jumpToAnchor = anchors.jumpToAnchor;

      // Honor any intra-page anchor specified in the URI.
      if ($location.hash() != '') {
        anchors.jumpToAnchor($location.hash());
      }
    }, function error(e) {
      $scope.cdash = e.data;
      $scope.loading = false;
    });


    // Show/hide our various history graphs.
    $scope.toggleTimeGraph = function() {
      $scope.showTimeGraph = !$scope.showTimeGraph;
      // Use a 1 ms timeout before loading graph data.
      // This gives the holder div a chance to become visible before the graph
      // is drawn.  Otherwise flot has trouble drawing the graph with the
      // correct dimensions.
      $timeout(function() {
        if (!$scope.graphLoaded) {
          $scope.loadGraphData('time');
        } else {
          $scope.renderGraph('time');
        }
      }, 1);
    };
    $scope.toggleErrorGraph = function() {
      $scope.showErrorGraph = !$scope.showErrorGraph;
      $timeout(function() {
        if (!$scope.graphLoaded) {
          $scope.loadGraphData('errors');
        } else {
          $scope.renderGraph('errors');
        }
      }, 1);
    };
    $scope.toggleWarningGraph = function() {
      $scope.showWarningGraph = !$scope.showWarningGraph;
      $timeout(function() {
        if (!$scope.graphLoaded) {
          $scope.loadGraphData('warnings');
        } else {
          $scope.renderGraph('warnings');
        }
      }, 1);
    };
    $scope.toggleTestGraph = function() {
      $scope.showTestGraph = !$scope.showTestGraph;
      $timeout(function() {
        if (!$scope.graphLoaded) {
          $scope.loadGraphData('tests');
        } else {
          $scope.renderGraph('tests');
        }
      }, 1);
    };
    $scope.toggleHistoryGraph = function() {
      $scope.showHistoryGraph = !$scope.showHistoryGraph;
      // Not rendered by flot, so no need for timeout.
      $scope.loadGraphData();
    };

    // Load graph data via AJAX.
    $scope.loadGraphData = function(graphType) {
      $scope.graphLoading = true;
      $http({
        url: 'api/v1/getPreviousBuilds.php',
        method: 'GET',
        params: { buildid: $scope.cdash.build.id }
      }).success(function(resp) {
        $scope.cdash.buildtimes = [];
        $scope.cdash.builderrors = [];
        $scope.cdash.buildwarnings = [];
        $scope.cdash.testfailed = [];
        $scope.cdash.buildids = [];
        $scope.cdash.buildhistory = [];

        // Isolate data for each graph.
        var builds = resp['builds'];
        for (var i = 0, len = builds.length; i < len; i++) {
          var build = builds[i];
          var t = build['timestamp'];

          $scope.cdash.buildtimes.push([t, build['time'] / 60]);
          $scope.cdash.builderrors.push([t, build['builderrors']]);
          $scope.cdash.buildwarnings.push([t, build['buildwarnings']]);
          $scope.cdash.testfailed.push([t, build['testfailed']]);
          $scope.cdash.buildids[t] = build['id'];

          var history_build = [];
          history_build['id'] = build['id'];
          history_build['nfiles'] = build['nfiles'];
          history_build['configureerrors'] = build['configureerrors'];
          history_build['configurewarnings'] = build['configurewarnings'];
          history_build['builderrors'] = build['builderrors'];
          history_build['buildwarnings'] = build['buildwarnings'];
          history_build['testfailed'] = build['testfailed'];
          history_build['starttime'] = build['starttime'];
          $scope.cdash.buildhistory.push(history_build);
        }
        $scope.cdash.buildhistory.reverse();
        $scope.graphLoaded = true;
        if (graphType) {
          // Render the graph that triggered this call.
          $scope.renderGraph(graphType);
        }
      }).finally(function() {
        $scope.graphLoading = false;
      });
    };

    // Initial render for one of our graphs.
    $scope.renderGraph = function (graphType) {

      if ($scope.graphRendered[graphType]) {
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
          data = $scope.cdash.buildtimes;
          element = "#buildtimegrapholder";
          label = "Build Time";
          break;
        case 'errors':
          options['colors'] = ["#FDD017"];
          options['yaxis'] = {minTickSize: 1};
          data = $scope.cdash.builderrors;
          element = "#builderrorsgrapholder";
          label = "# errors";
          break;
        case 'warnings':
          options['colors'] = ["#FF0000"];
          options['yaxis'] = {minTickSize: 1};
          data = $scope.cdash.buildwarnings;
          element = "#buildwarningsgrapholder";
          label = "# warnings";
          break;
        case 'tests':
          options['colors'] = ["#0000FF"];
          options['yaxis'] = {minTickSize: 1};
          data = $scope.cdash.testfailed;
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

      $(element).bind("plotclick", function (e, pos, item) {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          buildid = buildids[item.datapoint[0]];
          window.location = "buildSummary.php?buildid=" + buildid;
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

      $scope.graphRendered[graphType] = true;
    };

    $scope.toggleNote = function() {
      $scope.showNote = !$scope.showNote;
    };

    $scope.addNote = function() {
      var parameters = {
        buildid: $scope.cdash.build.id,
        Status: $scope.cdash.noteStatus,
        AddNote: $scope.cdash.noteText
      };

      $http.post('api/v1/addUserNote.php', parameters)
      .then(function success(s) {
        // Add the newly created note to our list.
        $scope.cdash.notes.push(s.data.note);
      }, function error(e) {
        // Display the error.
        $scope.cdash.error = e.data.error;
      });
    };

});
