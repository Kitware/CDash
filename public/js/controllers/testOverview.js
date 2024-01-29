CDash.controller('TestOverviewController',
  ["$scope", "$rootScope", "$filter", "apiLoader", "filters", "multisort", function TestOverviewController($scope, $rootScope, $filter, apiLoader, filters, multisort) {
    $scope.groupChanged = false;

    // Hide filters by default.
    $scope.showfilters = false;

    // Check for filters.
    $rootScope.queryString['filterstring'] = filters.getString();

    // Handle sort order.
    var sort_order = [];
    // First check if one was specified via query string.
    if ('sort' in $rootScope.queryString) {
      sort_order = $rootScope.queryString.sort.split(",");
    } else {
      // Next check for a sort order cookie.
      var sort_cookie_value = $.cookie('cdash_test_overview_sort');
      if(sort_cookie_value) {
        sort_order = sort_cookie_value.split(",");
      } else {
        // Default sorting: put the most broken tests at the top of the list.
        sort_order = ['-failpercent'];
      }
    }
    $scope.orderByFields = sort_order;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredTests = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.maxSize = 5;

    // Check if we have a cookie for number of tests to display.
    var num_per_page_cookie = $.cookie('testOverview_num_per_page');
    if(num_per_page_cookie) {
      $scope.pagination.numPerPage = parseInt(num_per_page_cookie);
    } else {
      $scope.pagination.numPerPage = 10;
    }

    apiLoader.loadPageData($scope, 'api/v1/testOverview.php');

    $scope.finishSetup = function() {
      $scope.cdash.showpassedinitialvalue = $scope.cdash.showpassed;
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.pageChanged();

      // Group selection.
      var idx = $scope.cdash.groups.map(function(x) {return x.id; }).indexOf($scope.cdash.groupid);
      if (idx < 0) {
        idx = 0;
      }
      $scope.cdash.selectedGroup = $scope.cdash.groups[idx];
    };

    $scope.pageChanged = function() {
      var begin = (($scope.pagination.currentPage - 1) * $scope.pagination.numPerPage)
      , end = begin + $scope.pagination.numPerPage;
      if (end > 0) {
        $scope.pagination.filteredTests = $scope.cdash.tests.slice(begin, end);
      } else {
        $scope.pagination.filteredTests = $scope.cdash.tests;
      }
    };

    $scope.numTestsPerPageChanged = function() {
      $.cookie("testOverview_num_per_page", $scope.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged();
    };

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.pageChanged();
      $.cookie('cdash_test_overview_sort', $scope.orderByFields);
    };

    $scope.updateSelection = function() {
      var uri = '//' + location.host + location.pathname + '?project=' + $scope.cdash.projectname_encoded;
      // Include date range from time chart.
      if ($scope.cdash.begin_date == $scope.cdash.end_date) {
        uri += '&date=' + $scope.cdash.begin_date;
      } else {
        uri += '&begin=' + $scope.cdash.begin_date + '&end=' + $scope.cdash.end_date;
      }
      if ($scope.cdash.selectedGroup.id > 0) {
        uri += '&group=' + $scope.cdash.selectedGroup.id;
      }
      if ($scope.cdash.showpassed == 1) {
        uri += '&showpassed=1';
      }
      uri += filters.getString();
      window.location = uri;
    };
}]);
