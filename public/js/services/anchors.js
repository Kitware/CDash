// Handle intra-page links.
CDash.service('anchors', ["$anchorScroll", "$location", "$timeout", function ($anchorScroll, $location, $timeout) {
  this.jumpToAnchor = function(elementId) {
    $timeout(function() {
      $location.hash(elementId);
      $anchorScroll();
    });
  };
}]);
