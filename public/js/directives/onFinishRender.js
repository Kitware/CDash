CDash.directive('onFinishRender', ["$timeout", function ($timeout) {
  return {
    restrict: 'A',
    link: function (scope, element, attr) {
      if (scope.$last === true) {
        scope.$evalAsync(attr.onFinishRender);
      }
    }
  }
}]);
