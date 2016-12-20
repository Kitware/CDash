CDash.controller('ViewTestController',
  function ViewTestController($scope, $rootScope, $http, $filter, multisort, filters, renderTimer) {
    $scope.loading = true;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredTests = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.maxSize = 5;

    // Check if we have a cookie for number of tests to display.
    var num_per_page_cookie = $.cookie('viewTest_num_per_page');
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
    var sort_cookie_value = $.cookie('cdash_view_test_sort');
    if(sort_cookie_value) {
      sort_order = sort_cookie_value.split(",");
    } else {
      // Default sorting : failed tests in alphabetical order.
      sort_order = ['subprojectname', 'status', 'name'];
    }
    $scope.orderByFields = sort_order;

    $http({
      url: 'api/v1/viewTest.php',
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
      if (end > 0) {
        $scope.pagination.filteredTests = $scope.cdash.tests.slice(begin, end);
      } else {
        $scope.pagination.filteredTests = $scope.cdash.tests;
      }

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
        }).then(function success(s) {
          var response = s.data;
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
      $.cookie('cdash_view_test_sort', $scope.orderByFields);
    };

    $scope.numTestsPerPageChanged = function() {
      $.cookie("viewTest_num_per_page", $scope.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged();
    };
});
