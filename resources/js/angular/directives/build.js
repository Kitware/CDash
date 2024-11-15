CDash.directive('build', ["VERSION", function (VERSION) {
  return {
    templateUrl: 'assets/js/angular/views/partials/build.html?id=' + VERSION,
  }
}]);
