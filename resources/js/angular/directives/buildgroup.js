CDash.directive('buildgroup', ["VERSION", function (VERSION) {
  return {
    templateUrl: 'assets/js/angular/views/partials/buildgroup.html?id=' + VERSION,
  }
}]);
