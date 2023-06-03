CDash.controller('ViewSubProjectsController',
  ["$scope", "multisort", "apiLoader", function ViewSubProjectsController($scope, multisort, apiLoader) {
    // Hide filters by default.
    $scope.showfilters = false;

    // Check for sort order cookie.
    var sort_order = [];
    var sort_cookie_value = $.cookie('cdash_subproject_sort');
    if(sort_cookie_value) {
      sort_order = sort_cookie_value.split(",");
    }
    $scope.sortSubProjects = { orderByFields: sort_order };

    apiLoader.loadPageData($scope, 'api/v1/viewSubProjects.php');

    $scope.updateOrderByFields = function(obj, field, $event) {
      multisort.updateOrderByFields(obj, field, $event);
      $.cookie('cdash_subproject_sort', obj.orderByFields);
    };
}]);
