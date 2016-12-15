CDash.controller('ViewProjectsController',
  function ViewProjectsController($scope, $rootScope, $http, renderTimer) {
    $scope.loading = true;
    // Hide filters by default.
    $scope.showfilters = false;
    $http({
      url: 'api/v1/viewProjects.php',
      method: 'GET',
      params: $rootScope.queryString
    }).then(function success(s) {
      renderTimer.initialRender($scope, s.data);
    }).finally(function() {
      $scope.loading = false;
    });
});
