CDash.controller('ViewTestController',
  function ViewTestController($scope, $http) {
    $http({
      url: 'api/v1/viewTest.php',
      method: 'GET',
      params: queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
    });
});
