CDash.controller('ViewUpdateController',
  ($scope, $rootScope, $http, apiLoader) => {
    $scope.graphLoaded = false;
    $scope.graphLoading = false;
    $scope.showGraph = false;
    apiLoader.loadPageData($scope, 'api/v1/viewUpdate.php');

    $scope.toggleGraph = function() {
      $scope.showGraph = !$scope.showGraph;
      if (!$scope.graphLoaded) {
        $scope.loadGraph();
      }
    };

    $scope.loadGraph = function() {
      $scope.graphLoading = true;
      $http({
        url: 'api/v1/buildUpdateGraph.php',
        method: 'GET',
        params: {
          buildid: $scope.cdash.build.buildid,
        },
      }).then((s) => {
        $scope.initializeGraph(s.data);
        $scope.graphLoaded = true;
        $scope.graphLoading = false;
      });
    };

    $scope.initializeGraph = function(input) {
      const options = {
        lines: {show: true},
        points: {show: true},
        xaxis: {mode: 'time'},
        grid: {
          backgroundColor: '#fffaff',
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4,
        },
        selection: {mode: 'x'},
        colors: ['#0000FF', '#dba255', '#919733'],
      };

      $('#graph_holder').bind('selected', (event, area) => {
        plot = $.plot($('#graph_holder'), [{
          label: 'Number of changed files',
          data: input.data,
        }], $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
      });

      $('#graph_holder').bind('plotclick', (e, pos, item) => {
        if (item) {
          plot.highlight(item.series, item.datapoint);
          buildid = input.buildids[item.datapoint[0]];
          window.location = `build/${buildid}`;
        }
      });

      plot = $.plot($('#graph_holder'), [{label: 'Number of changed files', data: input.data}], options);
    };
  })

  .directive('updatedFiles', (VERSION) => {
    return {
      templateUrl: `build/views/partials/updatedfiles_${VERSION}.html`,
    };
  });
