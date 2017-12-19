CDash.directive('autoComplete', function($parse) {
  return function(scope, element, attrs) {
    element.autocomplete({
      source: $parse(attrs.availableValues)(scope)
    });
  };
});
