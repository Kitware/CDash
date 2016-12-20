function ViewNotesController($scope, $rootScope, $http, $location, anchors, renderTimer) {
  $scope.loading = true;
  $http({
    url: 'api/v1/viewNotes.php',
    method: 'GET',
    params: $rootScope.queryString
  }).then(function success(s) {
    var cdash = s.data;
    renderTimer.initialRender($scope, cdash);
    // Honor any intra-page anchor specified in the URI.
    if ($location.hash() != '') {
    anchors.jumpToAnchor($location.hash());
    }
  }).finally(function() {
    $scope.loading = false;
  });

  $scope.gotoNote = function(x) {
    var newHash = 'note' + x;
    anchors.jumpToAnchor(newHash);
  };
}

CDash.controller('ViewNotesController', ['$scope', '$rootScope', '$http', '$location', 'anchors', 'renderTimer',
                                          ViewNotesController]);
