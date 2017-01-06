CDash.controller('ViewSubProjectsController',
  function ViewSubProjectsController($scope, $rootScope, $http, multisort, renderTimer) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    // Check for sort order cookie.
    var sort_order = [];
    var sort_cookie_value = $.cookie('cdash_subproject_sort');
    if(sort_cookie_value) {
      sort_order = sort_cookie_value.split(",");
    }
    $scope.sortSubProjects = { orderByFields: sort_order };

    $http({
      url: 'api/v1/viewSubProjects.php',
      method: 'GET',
      params: $rootScope.queryString
    }).then(function success(s) {
      var cdash = s.data;
      renderTimer.initialRender($scope, cdash);
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.updateOrderByFields = function(obj, field, $event) {
      multisort.updateOrderByFields(obj, field, $event);
      $.cookie('cdash_subproject_sort', obj.orderByFields);
    };
});
