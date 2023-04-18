CDash.directive('onFinishRender', ($timeout) => {
  return {
    restrict: 'A',
    link: function (scope, element, attr) {
      if (scope.$last === true) {
        scope.$evalAsync(attr.onFinishRender);
      }
    },
  };
});
