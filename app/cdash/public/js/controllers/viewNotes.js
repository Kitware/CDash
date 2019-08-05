function ViewNotesController($scope, $location, anchors, apiLoader) {
  apiLoader.loadPageData($scope, 'api/v1/viewNotes.php');
  $scope.finishSetup = function() {
    // Honor any intra-page anchor specified in the URI.
    if ($location.hash() != '') {
    anchors.jumpToAnchor($location.hash());
    }
  };

  $scope.gotoNote = function(x) {
    var newHash = 'note' + x;
    anchors.jumpToAnchor(newHash);
  };
}

CDash.controller('ViewNotesController', ['$scope', '$location', 'anchors', 'apiLoader',
                                          ViewNotesController]);
