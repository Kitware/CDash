CDash.controller('ViewTestController',
  ($scope, $rootScope, $http, $filter, $q, apiLoader, multisort, filters) => {
    $scope.loading = true;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredTests = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.maxSize = 5;

    // Check if we have a cookie for number of tests to display.
    const num_per_page_cookie = $.cookie('viewTest_num_per_page');
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
    const sort_cookie_value = $.cookie('cdash_view_test_sort');
    if (sort_cookie_value) {
      sort_order = sort_cookie_value.split(',');
    }
    else {
      // Default sorting : failed tests in alphabetical order.
      sort_order = ['subprojectname', 'status', 'name'];
    }
    $scope.orderByFields = sort_order;

    // Mechanism to cancel the summary/history AJAX query if the user loads another page.
    $scope.canceler = $q.defer();

    apiLoader.loadPageData($scope, 'api/v1/viewTest.php');
    $scope.finishSetup = function() {
      // Check for label filters
      $scope.cdash.extrafilterurl = filters.getLabelString($scope.cdash.filterdata);
      if ($scope.cdash.extrafilterurl) {
        $scope.cdash.querytestfilters = $scope.cdash.extrafilterurl;
      }
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.setPage(1);
    };

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.setPage = function (pageNo) {
      const begin = ((pageNo - 1) * $scope.pagination.numPerPage)
        , end = begin + $scope.pagination.numPerPage;
      if (end > 0) {
        $scope.pagination.filteredTests = $scope.cdash.tests.slice(begin, end);
      }
      else {
        $scope.pagination.filteredTests = $scope.cdash.tests;
      }

      // Load history & summary data for these newly revealed tests (if necessary).
      const tests_to_load = [];
      for (let i = 0, len = $scope.pagination.filteredTests.length; i < len; i++) {
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
            'buildid': $scope.cdash.build.id,
            'previous_builds': $scope.cdash.previous_builds,
            'time_begin': $scope.cdash.time_begin,
            'time_end': $scope.cdash.time_end,
            'projectid': $scope.cdash.projectid,
            'groupid': $scope.cdash.groupid,
          },
          timeout: $scope.canceler.promise,
        }).then((s) => {
          const response = s.data;
          $scope.cdash.displayhistory = $scope.cdash.displayhistory || response.displayhistory;
          $scope.cdash.displaysummary = $scope.cdash.displaysummary || response.displaysummary;

          function copy_test_details(test, response) {

            // Don't display extra data for missing tests
            if (test['status'] === 'Missing') {
              return;
            }

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
          for (let i = 0, len1 = response.tests.length; i < len1; i++) {
            for (let j = 0, len2 = $scope.pagination.filteredTests.length; j < len2; j++) {
              if (response.tests[i].name === $scope.pagination.filteredTests[j].name) {
                copy_test_details($scope.pagination.filteredTests[j], response.tests[i]);
              }
            }
          }

          // Also copy this newfound data into the 'master list' of tests.
          for (let i = 0, len1 = response.tests.length; i < len1; i++) {
            for (let j = 0, len2 = $scope.cdash.tests.length; j < len2; j++) {
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
      $.cookie('cdash_view_test_sort', $scope.orderByFields);
    };

    $scope.sortByExtraMeasurement = function(idx, $event) {
      const field = `measurements[${idx}]`;
      $scope.updateOrderByFields(field, $event);
    };

    $scope.numTestsPerPageChanged = function() {
      $.cookie('viewTest_num_per_page', $scope.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged();
    };

    $scope.cancelAjax = function() {
      $scope.canceler.resolve();
    };
  });
