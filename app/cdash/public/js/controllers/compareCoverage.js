CDash.controller('CompareCoverageController',
  ["$scope", "$rootScope", "apiLoader", "filters", "multisort", function CompareCoverageController($scope, $rootScope, apiLoader, filters, multisort) {
    // Hide filters by default.
    $scope.showfilters = false;

    // Check for filters.
    $rootScope.queryString['filterstring'] = filters.getString();

    $scope.sortCoverage = { orderByFields: [] };

    apiLoader.loadPageData($scope, 'api/v1/compareCoverage.php');

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.updateOrderByFields = function(obj, field, $event) {
      multisort.updateOrderByFields(obj, field, $event);
    };
}]);
