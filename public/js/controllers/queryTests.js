CDash.controller('QueryTestsController',
  function QueryTestsController($scope, $rootScope, $http, $filter, filters, multisort, renderTimer) {
    $scope.loading = true;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredBuilds = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.maxSize = 5;

    // Check if we have a cookie for number of tests to display.
    var num_per_page_cookie = $.cookie('queryTests_num_per_page');
    if(num_per_page_cookie) {
      $scope.pagination.numPerPage = parseInt(num_per_page_cookie);
    } else {
      $scope.pagination.numPerPage = 25;
    }

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
    }).then(function success(s) {
      var cdash = s.data;

      // Check if we should display filters.
      if (cdash.filterdata && cdash.filterdata.showfilters == 1) {
        $scope.showfilters = true;
      }

      // Check for label filters
      cdash.extrafilterurl = filters.getLabelString(cdash.filterdata);

      renderTimer.initialRender($scope, cdash);

      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
      $rootScope.setupCalendar($scope.cdash.date);
    }).finally(function() {
      $scope.loading = false;
      $scope.cdash.builds = $filter('orderBy')($scope.cdash.builds, $scope.orderByFields);
      $scope.pageChanged();
    });

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };


    $scope.pageChanged = function() {
      var begin = (($scope.pagination.currentPage - 1) * $scope.pagination.numPerPage)
      , end = begin + $scope.pagination.numPerPage;
      if (end > 0) {
        $scope.pagination.filteredBuilds = $scope.cdash.builds.slice(begin, end);
      } else {
        $scope.pagination.filteredBuilds = $scope.cdash.builds;
      }
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $scope.cdash.builds = $filter('orderBy')($scope.cdash.builds, $scope.orderByFields);
      $scope.pageChanged();
      $.cookie('cdash_query_tests_sort', $scope.orderByFields);
    };

    $scope.numTestsPerPageChanged = function() {
      $.cookie("queryTests_num_per_page", $scope.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged();
    };
});
