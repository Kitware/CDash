CDash.controller('QueryTestsController',
  ($scope, $rootScope, $filter, apiLoader, filters, multisort) => {
    $scope.loading = true;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredBuilds = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.maxSize = 5;

    // Check if we have a cookie for number of tests to display.
    const num_per_page_cookie = $.cookie('queryTests_num_per_page');
    if (num_per_page_cookie) {
      $scope.pagination.numPerPage = parseInt(num_per_page_cookie);
    }
    else {
      $scope.pagination.numPerPage = 25;
    }

    // Hide filters by default.
    $scope.showfilters = false;

    // Check for filters
    $rootScope.queryString['filterstring'] = filters.getString();

    // Check for sort order cookie.
    let sort_order = [];
    const sort_cookie_value = $.cookie('cdash_query_tests_sort');
    if (sort_cookie_value) {
      sort_order = sort_cookie_value.split(',');
    }
    $scope.orderByFields = sort_order;

    apiLoader.loadPageData($scope, 'api/v1/queryTests.php');
    $scope.finishSetup = function() {
      // Hide test output context by default.
      $scope.cdash.showmatchingoutput = false;

      // Check for label filters
      $scope.cdash.extrafilterurl = filters.getLabelString($scope.cdash.filterdata);
      if ($scope.cdash.extrafilterurl) {
        $scope.cdash.querytestfilters = $scope.cdash.extrafilterurl;
      }
      $scope.cdash.builds = $filter('orderBy')($scope.cdash.builds, $scope.orderByFields);
      $scope.pageChanged();
    };

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };


    $scope.pageChanged = function() {
      const begin = (($scope.pagination.currentPage - 1) * $scope.pagination.numPerPage)
        , end = begin + $scope.pagination.numPerPage;
      if (end > 0) {
        $scope.pagination.filteredBuilds = $scope.cdash.builds.slice(begin, end);
      }
      else {
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
      $.cookie('queryTests_num_per_page', $scope.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged();
    };

    $scope.toggleShowMatchingOutput = function() {
      $scope.cdash.showmatchingoutput = !($scope.cdash.showmatchingoutput);
    };

    $scope.sortByExtraMeasurement = function(idx, $event) {
      const field = `measurements[${idx}]`;
      $scope.updateOrderByFields(field, $event);
    };
  });
