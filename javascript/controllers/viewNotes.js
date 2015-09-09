CDash.controller('ViewNotesController',
  function ViewNotesController($scope, $http) {
    $scope.loading = true;
    $http({
      url: 'api/v1/viewNotes.php',
      method: 'GET',
      params: queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
    }).finally(function() {
      $scope.loading = false;
    });
});
