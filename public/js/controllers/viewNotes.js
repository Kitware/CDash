function ViewNotesController($scope, $rootScope, $http, $location, $anchorScroll, renderTimer) {
  $scope.loading = true;
  $http({
    url: 'api/v1/viewNotes.php',
    method: 'GET',
    params: $rootScope.queryString
  }).success(function(cdash) {
    renderTimer.initialRender($scope, cdash);
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

CDash.controller('ViewNotesController', ['$scope', '$rootScope', '$http', '$location', '$anchorScroll', 'renderTimer',
                                          ViewNotesController]);
