CDash.controller('QueryTestsController',
  function QueryTestsController($scope, $rootScope, $http) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    $http({
      url: 'api/v1/queryTests.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {

      // Check if we should display filters.
      if (cdash.filterdata && cdash.filterdata.showfilters == 1) {
        $scope.showfilters = true;
      }

      $scope.cdash = cdash;
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
    });
});
