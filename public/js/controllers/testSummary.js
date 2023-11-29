CDash.controller('TestSummaryController',
  ["$scope", "$timeout", "apiLoader", "multisort", function TestSummaryController($scope, $timeout, apiLoader, multisort) {
    // Hide filters and graph by default.
    $scope.showfilters = false;
    $scope.showgraph = false;
    $scope.graphurl = '';

    // Check for sort order cookie.
    var sort_order = [];
    var sort_cookie_value = $.cookie('cdash_test_summary_sort');
    if(sort_cookie_value) {
      sort_order = sort_cookie_value.split(",");
    } else {
      // Default sorting : status then site.
      sort_order = ['status', 'site'];
    }
    $scope.orderByFields = sort_order;

    apiLoader.loadPageData($scope, 'api/v1/testSummary.php');
    $scope.finishSetup = function() {
      $scope.graphurl = $scope.failureGraphUrl();
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $.cookie('cdash_test_summary_sort', $scope.orderByFields);
    };

    $scope.failureGraphUrl = function() {
      return 'ajax/showtestfailuregraph.php?testname=' + $scope.cdash.testName + '&projectid=' + $scope.cdash.projectid + '&starttime=' + $scope.cdash.currentstarttime;
    };

    $scope.toggleGraph = function() {
      $scope.showgraph = !$scope.showgraph;
      $timeout(function() {
        $scope.resetZoom();
      }, 10);
    }

    $scope.resetZoom = function() {
      // ng-include won't reload if graphurl doesn't change, so simply
      // twiddle between URLs.
      if ($scope.graphurl.indexOf("zoomout") != -1) {
        $scope.graphurl = $scope.failureGraphUrl();
      } else {
        $scope.graphurl = $scope.failureGraphUrl() + "&zoomout=1";
      }
    };

}]);
