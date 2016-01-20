CDash.controller('CompareCoverageController',
  function CompareCoverageController($scope, $rootScope, $http, filters, multisort) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    $scope.sortCoverage = { orderByFields: [] };

    $http({
      url: 'api/v1/compareCoverage.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {

      $scope.cdash = cdash;

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
