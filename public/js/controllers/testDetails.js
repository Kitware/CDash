CDash.controller('TestDetailsController',
  function TestDetailsController($scope, $http, $window, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/testDetails.php');
    $scope.finishSetup = function() {
      $scope.cdash.showgraph = false;
      $scope.cdash.showcommandline = false;
      $scope.cdash.rawdatalink = '';
      if ($scope.queryString.graph) {
        $scope.cdash.graphSelection = $scope.queryString.graph;
        $scope.display_graph();
      }
    };

    $scope.toggle_commandline = function() {
      $scope.cdash.showcommandline = !($scope.cdash.showcommandline);
    };

    $scope.display_graph = function() {
      var testid = $scope.cdash.test.id;
      var buildid = $scope.cdash.test.buildid;
      var measurementname = $scope.cdash.graphSelection;
      if ($scope.cdash.graphSelection === "") {
        $scope.cdash.showgraph = false;
        $("#graph_options").html("");
        return;
      }

      $scope.cdash.showgraph = true;

      var query_params = {
        testid: testid,
        buildid: buildid
      };

      var graph_type = '';
      $scope.cdash.rawdatalink = 'api/v1/testGraph.php?testid=' + testid + '&buildid=' + buildid;
      switch ($scope.cdash.graphSelection) {
        case "status":
          graph_type = 'status';
          break;
        case "time":
          graph_type = 'time';
          break;
        default:
          graph_type = 'measurement';
          query_params.measurementname = measurementname;
          $scope.cdash.rawdatalink += '&measurementname=' + measurementname;
          break;
      }
      $scope.cdash.rawdatalink += '&type=' + graph_type;

      query_params.type = graph_type;
      $http({
        url: 'api/v1/testGraph.php',
        method: 'GET',
        params: query_params
      }).then(function success(s) {
        $scope.test_graph(s.data, graph_type);
      });

    };

    $scope.test_graph = function(response, graph_type) {
      // Separate out build & test ids from the actual data points.
      var buildids = {};
      var testids = {};
      var chart_data = [];
      for (var i = 0; i < response.length; i++) {
        var series = {};
        series.label = response[i].label;
        series.data = [];
        for (var j = 0; j < response[i].data.length; j++) {
          series.data.push([response[i].data[j]['x'], response[i].data[j]['y']]);
          if (i == 0) {
            buildids[response[i].data[j]['x']] = response[i].data[j]['buildid'];
            testids[response[i].data[j]['x']] = response[i].data[j]['testid'];
          }
        }
        chart_data.push(series);
      }

      // Options that are shared by all of our different types of charts.
      var options = {
        grid: {
          backgroundColor: "#fffaff",
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4
        },
        pan: { interactive: true },
        zoom: { interactive: true, amount: 1.1 },
        xaxis: { mode: "time" },
        yaxis: {
          zoomRange: false,
          panRange: false
        }
      };

      switch (graph_type) {
        case "status":
          // Circles for passed tests, crosses for failed tests.
          chart_data[0].points = { symbol: 'circle'};
          chart_data[1].points = { symbol: 'cross'};
          options.series = {
            points: {
              show: true,
              radius: 5
            }
          };
          options.yaxis.ticks = [[-1, "Failed"], [1, "Passed"]];
          options.yaxis.min = -1.2;
          options.yaxis.max = 1.2;
          options.colors = ["#8aba5a", "#de6868"];
          break;

        case "time":
          // Show threshold series as a filled area.
          chart_data[1].lines = { fill: true };
          // The lack of a 'break' here is intentional.
          // time & measurement charts share common options.
        case "measurement":
          options.lines = { show: true };
          options.points = { show: true };
          options.colors = ["#0000FF", "#dba255", "#919733"];
          break;
      }

      $("#graph_holder").bind("plotclick", function (e, pos, item) {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          buildid = buildids[item.datapoint[0]];
          testid = testids[item.datapoint[0]];
          var url = "testDetails.php?test=" + testid + "&build=" + buildid + "&graph=" + $scope.cdash.graphSelection;
          $window.open(url);
        }
       });

      plot = $.plot(
        $("#graph_holder"), chart_data, options);

      // Show tooltip on hover.
      date_formatter = d3.time.format("%b %d, %I:%M:%S %p");
      $("#graph_holder").bind("plothover", function (event, pos, item) {
        if (item) {
          var x = date_formatter(new Date(item.datapoint[0])),
              y = item.datapoint[1].toFixed(2);

          $("#tooltip").html(
              "<b>" + x + "</b><br/>" +
              item.series.label + ": <b>" + y + "</b>")
            .css({top: item.pageY+5, left: item.pageX+5})
            .fadeIn(200);
        } else {
          $("#tooltip").hide();
        }
      });
    };

    $scope.setup_compare = function() {
      $('.je_compare').je_compare({caption: true});
    };
});
