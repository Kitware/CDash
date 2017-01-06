CDash.controller('CompareCoverageController',
  function CompareCoverageController($scope, $rootScope, $http, filters, multisort, renderTimer) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    // Check for filters.
    $rootScope.queryString['filterstring'] = filters.getString();

    $scope.sortCoverage = { orderByFields: [] };

    $http({
      url: 'api/v1/compareCoverage.php',
      method: 'GET',
      params: $rootScope.queryString
    }).then(function success(s) {
      var cdash = s.data;
      // Check if we should display filters.
      if (cdash.filterdata && cdash.filterdata.showfilters == 1) {
        $scope.showfilters = true;
      }

      renderTimer.initialRender($scope, cdash);

      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.updateOrderByFields = function(obj, field, $event) {
      multisort.updateOrderByFields(obj, field, $event);
    };
});
