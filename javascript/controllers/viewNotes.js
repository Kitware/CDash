function ViewNotesController($scope, $http, $location, $anchorScroll) {
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

  $scope.gotoNote = function(x) {
    var newHash = 'note' + x;
    if ($location.hash() !== newHash) {
      // set the $location.hash to `newHash` and
      // $anchorScroll will automatically scroll to it
      $location.hash('note' + x);
    } else {
      // call $anchorScroll() explicitly,
      // since $location.hash hasn't changed
      $anchorScroll();
    }
  };
}

CDash.controller('ViewNotesController', ['$scope', '$http', '$location', '$anchorScroll',
                                          ViewNotesController]);
