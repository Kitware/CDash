CDash.directive('build', ["VERSION", function (VERSION) {
  return {
    templateUrl: 'build/views/partials/build.html?id=' + VERSION,
  }
}]);
