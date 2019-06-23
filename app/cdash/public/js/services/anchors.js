// Handle intra-page links.
CDash.service('anchors', function ($anchorScroll, $location, $timeout) {
  this.jumpToAnchor = function(elementId) {
    $timeout(function() {
      $location.hash(elementId);
      $anchorScroll();
    });
  };
});
