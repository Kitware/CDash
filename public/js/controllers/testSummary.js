CDash.controller('TestSummaryController',
  function TestSummaryController($scope, $rootScope, $http, multisort) {
    $scope.loading = true;
    // Hide filters and graph by default.
    $scope.showfilters = false;
    $scope.showgraph = false;
    $scope.graphurl = '';

    // Default sorting : status then site.
    $scope.orderByFields = ['status', 'site'];

    $http({
      url: 'api/v1/testSummary.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
      $scope.graphurl = $scope.failureGraphUrl();
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
    };

    $scope.failureGraphUrl = function() {
      return 'ajax/showtestfailuregraph.php?testname=' + $scope.cdash.testName + '&projectid=' + $scope.cdash.projectid + '&starttime=' + $scope.cdash.currentstarttime;
    };

    $scope.resetZoom = function() {
      // ng-include won't reload if graphurl doesn't change, so simply
      // twiddle between URLs.
      if ($scope.graphurl.indexOf("zoomout") != -1) {
        $scope.graphurl = $scope.failureGraphUrl();
      } else {
        $scope.graphurl = $scope.failureGraphUrl() + "&zoomout=1";
      }
    };

});
