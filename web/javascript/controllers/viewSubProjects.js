CDash.controller('ViewSubProjectsController',
  function ViewSubProjectsController($scope, $rootScope, $http, multisort) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    $scope.orderByFields = [];

    $http({
      url: 'api/v1/viewSubProjects.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
    };
});
