// Handle intra-page links.
export function anchorsSvc ($anchorScroll, $location, $timeout) {
  this.jumpToAnchor = function(elementId) {
    $timeout(function() {
      $location.hash(elementId);
      $anchorScroll();
    });
  };
}
