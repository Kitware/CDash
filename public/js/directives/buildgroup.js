CDash.directive('buildgroup', ["VERSION", function (VERSION) {
  return {
    templateUrl: 'build/views/partials/buildgroup_' + VERSION + '.html'
  }
}]);
