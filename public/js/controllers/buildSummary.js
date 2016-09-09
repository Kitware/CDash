CDash.controller('BuildSummaryController',
  function BuildSummaryController($scope, $rootScope, $http, renderTimer) {

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
    }, function error(e) {
      $scope.cdash = e.data;
      $scope.loading = false;
    });


    // Show/hide our various history graphs.
    $scope.toggleTimeGraph = function() {
      $scope.showTimeGraph = !$scope.showTimeGraph;

      if (!$scope.graphLoaded) {
        $scope.loadGraphs();
      }
    };
    $scope.toggleErrorGraph = function() {
      $scope.showErrorGraph = !$scope.showErrorGraph;

      if (!$scope.graphLoaded) {
        $scope.loadGraphs();
      }
    };
    $scope.toggleWarningGraph = function() {
      $scope.showWarningGraph = !$scope.showWarningGraph;

      if (!$scope.graphLoaded) {
        $scope.loadGraphs();
      }
    };
    $scope.toggleTestGraph = function() {
      $scope.showTestGraph = !$scope.showTestGraph;

      if (!$scope.graphLoaded) {
        $scope.loadGraphs();
      }
    };
    $scope.toggleHistoryGraph = function() {
      $scope.showHistoryGraph = !$scope.showHistoryGraph;

      if (!$scope.graphLoaded) {
        $scope.loadGraphs();
      }
    };

    // Load graph data via AJAX.
    $scope.loadGraphs = function() {
      $scope.graphLoading = true;
      $http({
        url: 'api/v1/getPreviousBuilds.php',
        method: 'GET',
        params: { buildid: $scope.cdash.build.id }
      }).success(function(resp) {
        $scope.setupGraphs(resp['builds']);
        $scope.graphLoaded = true;
      }).finally(function() {
        $scope.graphLoading = false;
      });
    };

    // Initialize each graph with newly loaded data.
    $scope.setupGraphs = function (builds) {
      var buildtime = [];
      var builderrors = [];
      var buildwarnings = [];
      var testfailed = [];
      var buildids = [];

      $scope.cdash.buildhistory = [];

      // Isolate data for each graph.
      for (var i = 0, len = builds.length; i < len; i++) {
        var build = builds[i];
        var t = build['timestamp'];

        buildtime.push([t, build['time'] / 60]);
        builderrors.push([t, build['builderrors']]);
        buildwarnings.push([t, build['buildwarnings']]);
        testfailed.push([t, build['testfailed']]);
        buildids[t] = build['id'];

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

      // Options shared by all four graphs.
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

      var default_yaxis = {minTickSize: 1};

      // Settings specific to each graph.
      var graph_settings = [
        // Build Time
        {
          color: ["#41A317"],
          data: buildtime,
          element: "#buildtimegrapholder",
          label: "Build Time",
          yaxis: {
            tickFormatter: function (v, axis) {
              return v.toFixed(axis.tickDecimals) + " mins"}
            }
        },
        // Build Errors
        {
          color: ["#FDD017"],
          data: builderrors,
          element: "#builderrorsgrapholder",
          label: "# errors"
        },
        // Build Warnings
        {
          color: ["#FF0000"],
          data: buildwarnings,
          element: "#buildwarningsgrapholder",
          label: "# warnings"
        },
        // Tests Failed
        {
          color: ["#0000FF"],
          data: testfailed,
          element: "#buildtestsfailedgrapholder",
          label: "# tests failed"
        }
      ];

      // Render all of the graphs.
      for (var i = 0, len = graph_settings.length; i < len; i++) {
        var settings = graph_settings[i];
        options['colors'] = settings['color'];
        if ('yaxis' in settings) {
          options['yaxis'] = settings['yaxis'];
        } else {
          options['yaxis'] = default_yaxis;
        }

        $(settings['element']).bind("selected", function (event, area) {
          plot = $.plot($(settings['element']), [{label: settings['label'], data: settings['data']}],
            $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
        });

        $(settings['element']).bind("plotclick", function (e, pos, item) {
          if (item) {
            plot.highlight(item.series, item.datapoint);
            buildid = buildids[item.datapoint[0]];
            window.location = "buildSummary.php?buildid=" + buildid;
          }
        });

        plot = $.plot($(settings['element']), [{label: settings['label'], data: settings['data']}],
          options);
      }
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
