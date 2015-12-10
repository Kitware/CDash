CDash.controller('ViewTestController',
  function ViewTestController($scope, $rootScope, $http, $filter, multisort, filters) {
    $scope.loading = true;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredTests = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.numPerPage = 25;
    $scope.pagination.maxSize = 5;

    // Hide filters by default.
    $scope.showfilters = false;

    // Check for filters
    $rootScope.queryString['filterstring'] = filters.getString();

    // Default sorting : failed tests in alphabetical order.
    $scope.orderByFields = ['status', 'name'];

    $http({
      url: 'api/v1/viewTest.php',
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
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.setPage(1);
    });

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.setPage = function (pageNo) {
      var begin = ((pageNo - 1) * $scope.pagination.numPerPage)
      , end = begin + $scope.pagination.numPerPage;
      $scope.pagination.filteredTests = $scope.cdash.tests.slice(begin, end);

      // Load history & summary data for these newly revealed tests (if necessary).
      var tests_to_load = [];
      for (var i = 0, len = $scope.pagination.filteredTests.length; i < len; i++) {
        if ( !('detailsloaded' in $scope.pagination.filteredTests[i]) ) {
          tests_to_load.push($scope.pagination.filteredTests[i]['name']);
        }
      }

      if (tests_to_load.length > 0) {
        $http({
          url: 'api/v1/viewTest.php',
          method: 'GET',
          params: {
            'tests[]': tests_to_load,
            'previous_builds': $scope.cdash.previous_builds,
            'time_begin': $scope.cdash.time_begin,
            'time_end': $scope.cdash.time_end,
            'projectid': $scope.cdash.projectid,
            'groupid': $scope.cdash.groupid
          }
        }).success(function(response) {
          $scope.cdash.displayhistory = $scope.cdash.displayhistory || response.displayhistory;
          $scope.cdash.displaysummary = $scope.cdash.displaysummary || response.displaysummary;

          function copy_test_details(test, response) {
            if ('history' in response) {
              test['history'] = response['history'];
              test['historyclass'] = response['historyclass'];
            }
            if ('summary' in response) {
              test['summary'] = response['summary'];
              test['summaryclass'] = response['summaryclass'];
            }
            test['detailsloaded'] = true;
          }

          // Update our currently displayed filtered results with this new data.
          for (var i = 0, len1 = response.tests.length; i < len1; i++) {
            for (var j = 0, len2 = $scope.pagination.filteredTests.length; j < len2; j++) {
              if (response.tests[i].name === $scope.pagination.filteredTests[j].name) {
                copy_test_details($scope.pagination.filteredTests[j], response.tests[i]);
              }
            }
          }

          // Also copy this newfound data into the 'master list' of tests.
          for (var i = 0, len1 = response.tests.length; i < len1; i++) {
            for (var j = 0, len2 = $scope.cdash.tests.length; j < len2; j++) {
              if (response.tests[i].name === $scope.cdash.tests[j].name) {
                copy_test_details($scope.cdash.tests[j], response.tests[i]);
              }
            }
          }
        });
      }
    };

    $scope.pageChanged = function() {
      $scope.setPage($scope.pagination.currentPage);
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.pageChanged();
    };
});
