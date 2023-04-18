CDash.directive('convertToNumber', () => {
  return {
    require: 'ngModel',
    link: function(scope, element, attrs, ngModel) {
      ngModel.$parsers.push((val) => {
        return parseInt(val, 10);
      });
      ngModel.$formatters.push((val) => {
        return `${val}`;
      });
    },
  };
});
