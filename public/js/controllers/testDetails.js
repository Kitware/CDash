CDash.controller('TestDetailsController',
  function TestDetailsController($scope, $http, $window, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/testDetails.php');
    $scope.finishSetup = function() {
      $scope.cdash.showgraph = false;
      $scope.cdash.showcommandline = false;
      $scope.cdash.csvlink = '';
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

      switch ($scope.cdash.graphSelection) {
        case "TestPassingGraph":
          $http({
            url: 'ajax/showtestpassinggraph.php',
            method: 'GET',
            params: {
              testid: testid,
              buildid: buildid
            }
          }).then(function success(s) {
            $scope.status_graph(s.data);
          });
          break;

        case "TestTimeGraph":
          $http({
            url: 'ajax/showtesttimegraph.php',
            method: 'GET',
            params: {
              testid: testid,
              buildid: buildid
            }
          }).then(function success(s) {
            $scope.measurement_graph(s.data, "Execution Time (seconds)");
          });
          break;

        default:
          $http({
            url: 'ajax/showtestmeasurementdatagraph.php',
            method: 'GET',
            params: {
              testid: testid,
              buildid: buildid,
              measurement: measurementname
            }
          }).then(function success(s) {
            $scope.measurement_graph(s.data, measurementname);
            $scope.cdash.csvlink = 'ajax/showtestmeasurementdatagraph.php?testid=' + testid + '&buildid=' + buildid + '&measurement=' + measurementname + '&export=csv';
          });
          break;
      }
    };

    $scope.status_graph = function(response) {
      var d1 = [];
      var ty = [];
      var max = 0;
      ty.push([-1,"Failed"]);
      ty.push([1,"Passed"]);

      for (var i = 0; i < response.length; i++) {
        d1.push([response[i]['x'], response[i]['y']]);
        if (response[i]['x'] > max) {
          max = response[i]['x'];
        }
      }

      var options = {
        bars: {
          show: true,
          barWidth: 35000000,
          lineWidth: 0.9
        },
        yaxis: {
          ticks: ty,
          min: -1.2,
          max: 1.2,
          zoomRange: false,
          panRange: false
        },
        xaxis: {
          mode: "time",
          min: max - 2000000000,
          max: max + 50000000
        },
        grid: {backgroundColor: "#fffaff"},
        colors: ["#0000FF", "#dba255", "#919733"],
        zoom: { interactive: true },
        pan: { interactive: true }
      };

      $.plot($("#graph_holder"), [{label: "Failed/Passed",  data: d1}], options);
    };

    $scope.measurement_graph = function(response, measurementName) {
      var d1 = [];
      var buildids = {};
      var testids = {};
      var max = 0;

      for (var i = 0; i < response.length; i++) {
        d1.push([response[i]['x'], response[i]['y']]);
        buildids[response[i]['x']] = response[i]['buildid'];
        testids[response[i]['x']] = response[i]['testid'];
        if (response[i]['x'] > max) {
          max = response[i]['x'];
        }
      }

      var options = {
        lines: { show: true },
        points: { show: true },
        xaxis: { mode: "time", },
        yaxis: {
          zoomRange: false,
          panRange: false
        },
        grid: {
          backgroundColor: "#fffaff",
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4
        },
        colors: ["#0000FF", "#dba255", "#919733"],
        zoom: { interactive: true, amount: 1.1 },
        pan: { interactive: true },
      };

      $("#graph_holder").bind("plotclick", function (e, pos, item) {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          buildid = buildids[item.datapoint[0]];
          testid = testids[item.datapoint[0]];
          var url = "testDetails.php?test="+testid+"&build="+buildid;
          $window.open(url);
        }
       });

      plot = $.plot(
        $("#graph_holder"),
        [{label: measurementName, data: d1}],
        options);

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
