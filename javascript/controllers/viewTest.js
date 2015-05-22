CDash.controller('ViewTestController',
  function ViewTestController($scope, $http) {
    $http({
      url: 'api/viewTest.php',
      method: 'GET',
      params: queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
    });
});
