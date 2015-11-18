CDash.controller('ViewProjectsController',
  function ViewProjectsController($scope, $rootScope, $http) {
    $scope.loading = true;
    // Hide filters by default.
    $scope.showfilters = false;
    $http({
      url: 'api/v1/viewProjects.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
    }).finally(function() {
      $scope.loading = false;
    });
});
