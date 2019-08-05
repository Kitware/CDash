CDash.controller('ViewProjectsController',
  function ViewProjectsController($scope, apiLoader) {
    // Hide filters by default.
    $scope.showfilters = false;
    apiLoader.loadPageData($scope, 'api/v1/viewProjects.php');
});
