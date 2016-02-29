CDash.controller('QueryTestsController',
  function QueryTestsController($scope, $rootScope, $http, filters, multisort) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    // Check for filters
    $rootScope.queryString['filterstring'] = filters.getString();

    // Check for sort order cookie.
    var sort_order = [];
    var sort_cookie_value = $.cookie('cdash_query_tests_sort');
    if(sort_cookie_value) {
      sort_order = sort_cookie_value.split(",");
    }
    $scope.orderByFields = sort_order;

    $http({
      url: 'api/v1/queryTests.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {

      // Check if we should display filters.
      if (cdash.filterdata && cdash.filterdata.showfilters == 1) {
        $scope.showfilters = true;
      }

      // Check for label filters
      cdash.extrafilterurl = filters.getLabelString(cdash.filterdata);

      $scope.cdash = cdash;
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $.cookie('cdash_query_tests_sort', $scope.orderByFields);
    };
});
