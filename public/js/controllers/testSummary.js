CDash.controller('TestSummaryController',
  function TestSummaryController($scope, $rootScope, $http, multisort, renderTimer) {
    $scope.loading = true;
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

    $http({
      url: 'api/v1/testSummary.php',
      method: 'GET',
      params: $rootScope.queryString
    }).then(function success(s) {
      var cdash = s.data;
      renderTimer.initialRender($scope, cdash);

      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
      $scope.graphurl = $scope.failureGraphUrl();
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $.cookie('cdash_test_summary_sort', $scope.orderByFields);
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
