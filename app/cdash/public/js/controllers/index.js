CDash.filter('showEmptyBuildsLast', () => {
  // Move missing expected builds and those missing data to the bottom of the table.
  return function (builds, sortField) {
    if (!angular.isArray(builds)) {
      return;
    }

    // Expected builds that haven't submitted yet will appear
    // at the bottom of the table.
    const nonempty = builds.filter((build) => {
      return !('expectedandmissing' in build);
    });
    const expecteds = builds.filter((build) => {
      return 'expectedandmissing' in build;
    });

    // Get the primary (first) field that we're sorting by.
    if (angular.isArray(sortField)) {
      if (sortField.length < 1) {
        return nonempty.concat(expecteds);
      }
      sortField = sortField[0];
    }
    if (sortField.charAt(0) === '-') {
      sortField = sortField.substring(1);
    }

    // The only sort fields that could have missing data have
    // a '.' in their name (update.files, compilation.errors, etc.)
    // So if we're not sorting by one of them, we can return early.
    const idx = sortField.indexOf('.');
    if (idx === -1) {
      return nonempty.concat(expecteds);
    }

    // Put builds that don't have any data for our sortField
    // at the bottom of the table, but above the expected-and-missing
    // builds.
    const dataField = sortField.substr(0, idx);
    let present = nonempty.filter((build) => {
      return dataField in build;
    });
    const missing = nonempty.filter((build) => {
      return !(dataField in build);
    });

    present = present.concat(missing);
    return present.concat(expecteds);
  };
})


  .controller('IndexController', ($scope, $rootScope, $location, $http, $filter, $timeout, anchors, apiLoader, filters, multisort, modalSvc) => {
  // Show spinner while page is loading.
    $scope.loading = true;

    // Hide filters & settings dropdown menu by default.
    $scope.showfilters = false;
    $scope.showsettings = false;

    // Check if we have a cookie for auto-refresh.
    const refresh_cookie = $.cookie('cdash_refresh') === 'true';
    if (refresh_cookie) {
      $scope.autoRefresh = true;
      $timeout(() => {
        window.location.reload();
      }, 5000);
    }
    else {
      $scope.autoRefresh = false;
    }

    // Hide timeline chart by default. It is conditionally enabled below if
    // we are reviewing results for a single buildgroup.
    $scope.showTimelineChart = false;

    // Check for filters
    $rootScope.queryString['filterstring'] = filters.getString();

    // Check if buildgroup sort order was specified via query string.
    let query_sort_order = [];
    if ('sort' in $rootScope.queryString) {
      query_sort_order = $rootScope.queryString.sort.split(',');
    }

    apiLoader.loadPageData($scope, 'api/v1/index.php');

    $scope.finishSetup = function() {
    // Check for more sorting cookies.  Buildgroup sorting is handled below.
      let sort_order = [];
      let cookie_value = $.cookie(`cdash_${$scope.cdash.projectname}_coverage_sort`);
      if (cookie_value) {
        sort_order = cookie_value.split(',');
      }
      $scope.sortCoverage = { orderByFields: sort_order };

      sort_order = [];
      cookie_value = $.cookie(`cdash_${$scope.cdash.projectname}_DA_sort`);
      if (cookie_value) {
        sort_order = cookie_value.split(',');
      }
      $scope.sortDA = { orderByFields: sort_order };

      sort_order = [];
      cookie_value = $.cookie(`cdash_${$scope.cdash.projectname}_subproject_sort`);
      if (cookie_value) {
        sort_order = cookie_value.split(',');
      }
      $scope.sortSubProjects = { orderByFields: sort_order };

      // Check if we have a cookie for number of rows to display.
      const num_per_page_cookie = $.cookie('num_builds_per_page');

      // Modify some settings if we're viewing the results from a single group.
      if ('buildgroup' in $rootScope.queryString) {
        $scope.showTimelineChart = true;
        $scope.cdash.buildgroup = $rootScope.queryString['buildgroup'];
      }

      for (const i in $scope.cdash.buildgroups) {
        if (!$scope.cdash.buildgroups.hasOwnProperty(i)) {
          continue;
        }

        // Initialize pagination settings.
        $scope.cdash.buildgroups[i].pagination = [];
        $scope.cdash.buildgroups[i].pagination.filteredBuilds = [];
        $scope.cdash.buildgroups[i].pagination.currentPage = 1;
        $scope.cdash.buildgroups[i].pagination.maxSize = 5;
        if (num_per_page_cookie) {
          $scope.cdash.buildgroups[i].pagination.numPerPage = parseInt(num_per_page_cookie);
        }
        else {
          $scope.cdash.buildgroups[i].pagination.numPerPage = 10;
        }

        // Setup sorting.
        let sorting_set = false;
        if (query_sort_order.length > 0) {
        // Use sort order that was specified via query string.
          $scope.cdash.buildgroups[i].orderByFields = query_sort_order;
          sorting_set = true;
        }
        else {
        // If sort order wasn't specified via query string, check to see
        // if we have a cookie telling us how to sort this buildgroup.
          const cookie_name = $scope.getCookieName($scope.cdash.buildgroups[i], $scope.cdash.projectname, $scope.cdash.childview);
          const sort_cookie_value = $.cookie(cookie_name);
          if (sort_cookie_value) {
            sort_order = sort_cookie_value.split(',');
            $scope.cdash.buildgroups[i].orderByFields = sort_order;
            sorting_set = true;
          }
        }
        if (!sorting_set) {
        // Default sorting.
          $scope.cdash.buildgroups[i].orderByFields = [];

          // When viewing the children of a single build, show problematic
          // SubProjects sorted oldest to newest.
          // eslint-disable-next-line eqeqeq
          if ($scope.cdash.childview == 1) {
            if ($scope.cdash.buildgroups[i].numbuilderror > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-compilation.error');
            }
            else if ($scope.cdash.buildgroups[i].numconfigureerror > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-configure.error');
            } if ($scope.cdash.buildgroups[i].numtestfail > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-test.fail');
            }
            $scope.cdash.buildgroups[i].orderByFields.push('builddatefull');
          }
          else if (!('sorttype' in $scope.cdash.buildgroups[i])) {
          // By default, sort by errors & such in the following priority order:
          // configure errors
            if ($scope.cdash.buildgroups[i].numconfigureerror > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-configure.error');
            }
            // build errors
            if ($scope.cdash.buildgroups[i].numbuilderror > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-compilation.error');
            }
            // tests failed
            if ($scope.cdash.buildgroups[i].numtestfail > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-test.fail');
            }
            // tests not run
            if ($scope.cdash.buildgroups[i].numtestnotrun > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-test.notrun');
            }
            // configure warnings
            if ($scope.cdash.buildgroups[i].numconfigurewarning > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-configure.warning');
            }
            // build warnings
            if ($scope.cdash.buildgroups[i].numbuildwarning > 0) {
              $scope.cdash.buildgroups[i].orderByFields.push('-compilation.warning');
            }
            $scope.cdash.buildgroups[i].orderByFields.push('-builddatefull');
          }
          else if ($scope.cdash.buildgroups[i]['sorttype'] === 'time') {
          // For continuous integration groups, the most recent builds
          // should be at the top of the list.
            $scope.cdash.buildgroups[i].orderByFields.push('-builddatefull');
          }
        }

        // Initialize paginated results.
        $scope.cdash.buildgroups[i].builds = $filter('orderBy')($scope.cdash.buildgroups[i].builds, $scope.cdash.buildgroups[i].orderByFields);
        $scope.cdash.buildgroups[i].builds = $filter('showEmptyBuildsLast')($scope.cdash.buildgroups[i].builds, $scope.cdash.buildgroups[i].orderByFields);

        // Mark this group has having "normal" builds if it only contains missing & expected builds.
        if (!$scope.cdash.buildgroups[i].hasnormalbuilds && !$scope.cdash.buildgroups[i].hasparentbuilds && $scope.cdash.buildgroups[i].builds.length > 0) {
          $scope.cdash.buildgroups[i].hasnormalbuilds = true;
        }

        $scope.pageChanged($scope.cdash.buildgroups[i]);
      }

      // Check for label filters
      $scope.cdash.extrafilterurl = '';
      if ($scope.cdash.sharelabelfilters) {
        $scope.cdash.extrafilterurl = filters.getLabelString($scope.cdash.filterdata);
        $scope.cdash.querytestfilters = $scope.cdash.extrafilterurl;
      }

      // Read simple/advanced view cookie setting.
      const advanced_cookie = $.cookie(`cdash_${$scope.cdash.projectname}_advancedview`);
      let show_time_columns = 0;
      // eslint-disable-next-line eqeqeq
      if (advanced_cookie == 1) {
        $scope.cdash.advancedview = 1;
        if ($scope.cdash.showstarttime) {
        // Don't show time columns for all-at-once subproject builds.
        // This situation is identified by showstarttime being false.
          show_time_columns = 1;
        }
      }
      else {
        $scope.cdash.advancedview = 0;
      }
      $scope.cdash.showtimecolumns = show_time_columns;

      // Should we show the Test Time column?
      if (!$scope.cdash.showtesttime) {
        $scope.cdash.showtesttime =
        // eslint-disable-next-line eqeqeq
        $scope.cdash.advancedview != 0 && $scope.cdash.showstarttime;
      }

      // Determine if we should display any extra columns in the 'Test' section.
      $scope.cdash.extratestcolumns = 0;
      if ($scope.cdash.showtesttime) {
        $scope.cdash.extratestcolumns += 1;
      }
      if ($scope.cdash.advancedview && $scope.cdash.showProcTime) {
        $scope.cdash.extratestcolumns += 1;
      }

      $scope.cdash.numcolumns = 14 + $scope.cdash.extratestcolumns + $scope.cdash.displaylabels;

      const projectid = $scope.cdash.projectid;

      // Expose the jumpToAnchor function to the scope.
      // This allows us to call it from the HTML template.
      $scope.jumpToAnchor = anchors.jumpToAnchor;

      // Honor any intra-page anchor specified in the URI.
      // eslint-disable-next-line eqeqeq
      if ($location.hash() != '') {
        anchors.jumpToAnchor($location.hash());
      }

    };


    $scope.toggleAdvancedView = function() {
      // eslint-disable-next-line eqeqeq
      if ($scope.cdash.advancedview == 1) {
        $scope.cdash.advancedview = 0;
      }
      else {
        $scope.cdash.advancedview = 1;
      }
      $.cookie(`cdash_${$scope.cdash.projectname}_advancedview`, $scope.cdash.advancedview);
      window.location.reload(true);
    };


    // The following functions were moved here from cdashBuildGroup.js
    $scope.URLencode = function(sStr) {
      return escape(sStr)
        .replace(/\+/g, '%2B')
        .replace(/\"/g,'%22')
        .replace(/\'/g, '%27');
    };

    $scope.toggleAdminOptions = function(build) {
      if (!('expectedandmissing' in build) &&
        // eslint-disable-next-line eqeqeq
        (!('expected' in build) || (build.expected != 0 && build.expected != 1))) {
        build.loading = 1;
        // Determine whether or not this is an expected build.
        $http({
          url: 'api/v1/is_build_expected.php',
          method: 'GET',
          params: { 'buildid': build.id },
        }).then((s) => {
          const response = s.data;
          build.loading = 0;
          if ('expected' in response) {
            build.expected = response.expected;
            // eslint-disable-next-line eqeqeq
            if ( !('showAdminOptions' in build) || build.showAdminOptions == 0) {
              build.showAdminOptions = 1;
            }
            else {
              build.showAdminOptions = 0;
            }
          }
        });
      }
      else {
        // eslint-disable-next-line eqeqeq
        if ( !('showAdminOptions' in build) || build.showAdminOptions == 0) {
          build.showAdminOptions = 1;
        }
        else {
          build.showAdminOptions = 0;
        }
      }
    };

    $scope.toggleBuildProblems = function(build) {
      if (!('hasErrors' in build)) {
        build.loading = 1;
        $http({
          url: 'api/v1/build.php',
          method: 'GET',
          params: {
            'buildid': build.id,
            'getproblems': 1,
          },
        }).then((s) => {
          const response = s.data;
          build.loading = 0;
          build.showProblems = 1;

          build.hasErrors = response.hasErrors;
          build.failingSince = response.failingSince;
          build.failingDate = response.failingDate;
          build.daysWithErrors = response.daysWithErrors;

          build.hasFailingTests = response.hasFailingTests;
          build.testsFailingSince = response.testsFailingSince;
          build.testsFailingDate = response.testsFailingDate;
          build.daysWithFailingTests = response.daysWithFailingTests;
        });
      }
      else {
        // eslint-disable-next-line eqeqeq
        if (build.showProblems == 0) {
          build.showProblems = 1;
        }
        else {
          build.showProblems = 0;
        }
      }
    };

    $scope.toggleExpectedInfo = function(build) {
      if (!('lastSubmission' in build)) {
        build.loading = 1;
        $http({
          url: 'api/v1/expectedbuild.php',
          method: 'GET',
          params: {
            'siteid': build.siteid,
            'groupid': build.buildgroupid,
            'name': build.buildname,
            'type': build.buildtype,
            'currenttime': $scope.cdash.unixtimestamp,
          },
        }).then((s) => {
          const response = s.data;
          build.loading = 0;
          build.showExpectedInfo = 1;
          build.lastSubmission = response.lastSubmission;
          build.lastSubmissionDate = response.lastSubmissionDate;
          build.daysSinceLastBuild = response.daysSinceLastBuild;
        });
      }
      else {
        // eslint-disable-next-line eqeqeq
        if (build.showExpectedInfo == 0) {
          build.showExpectedInfo = 1;
        }
        else {
          build.showExpectedInfo = 0;
        }
      }
    };

    $scope.showModal = function (buildid) {
      modalSvc.showModal(buildid, $scope.removeBuild, 'modal-template');
    };

    $scope.removeBuild = function(build) {
      const parameters = { buildid: build.id };
      $http({
        url: 'api/v1/build.php',
        method: 'DELETE',
        params: parameters,
      }).then(() => {
        $scope.removeBuildFromScope(build);
      });
    };

    $scope.removeBuildFromScope = function(build) {
    // Find the build to remove in its group.
      let idx1 = -1;
      let idx2 = -1;
      for (let i = 0, len1 = $scope.cdash.buildgroups.length; i < len1; i++) {
        for (let j = 0, len2 = $scope.cdash.buildgroups[i].builds.length; j < len2; j++) {
          if ($scope.cdash.buildgroups[i].builds[j] === build) {
            idx1 = i;
            idx2 = j;
            break;
          }
        }
        // eslint-disable-next-line eqeqeq
        if (idx1 != -1) {
          break;
        }
      }
      if (idx1 > -1 && idx2 > -1) {
      // Remove the build from our scope.
        $scope.cdash.buildgroups[idx1].builds.splice(idx2, 1);
        $scope.pageChanged($scope.cdash.buildgroups[idx1]);
      }
    };

    $scope.toggleDone = function(build) {
      let newDoneValue = 1;
      // eslint-disable-next-line eqeqeq
      if (build.done == 1) {
        newDoneValue = 0;
      }
      const parameters = {
        buildid: build.id,
        done: newDoneValue,
      };
      $http.post('api/v1/build.php', parameters)
        .then(() => {
          build.done = newDoneValue;
        });
    };

    $scope.toggleExpected = function(build, groupid) {
      // eslint-disable-next-line eqeqeq
      if (build.expectedandmissing == 1) {
      // Delete a rule specifying a missing expected build.
        const parameters = {
          siteid: build.siteid,
          groupid: build.buildgroupid,
          name: build.buildname,
          type: build.buildtype,
        };
        $http({
          url: 'api/v1/expectedbuild.php',
          method: 'DELETE',
          params: parameters,
        }).then(() => {
          $scope.removeBuildFromScope(build);
        });
      }
      else {
        let newExpectedValue = 1;
        // eslint-disable-next-line eqeqeq
        if (build.expected == 1) {
          newExpectedValue = 0;
        }
        const parameters = {
          buildid: build.id,
          groupid: groupid,
          expected: newExpectedValue,
        };
        $http.post('api/v1/build.php', parameters)
          .then(() => {
            build.expected = newExpectedValue;
          });
      }
    };

    $scope.toggleAutoRefresh = function() {
      const refresh_cookie = $.cookie('cdash_refresh') === 'true';
      if (refresh_cookie) {
      // Delete the cookie and reload
        $.cookie('cdash_refresh', null);
        window.location.reload();
      }
      else {
        $.cookie('cdash_refresh', 'true');
        window.location.reload();
      }
    };

    $scope.moveToGroup = function(build, groupid) {
      // eslint-disable-next-line eqeqeq
      if (build.expectedandmissing == 1) {
        const parameters = {
          siteid: build.siteid,
          groupid: build.buildgroupid,
          newgroupid: groupid,
          name: build.buildname,
          type: build.buildtype,
        };
        $http.post('api/v1/expectedbuild.php', parameters)
          .then(() => {
            window.location.reload();
          });
      }
      else {
        const parameters = {
          buildid: build.id,
          newgroupid: groupid,
          expected: build.expected,
        };
        $http.post('api/v1/build.php', parameters)
          .then(() => {
            window.location.reload();
          });
      }
    };

    $scope.colorblind_toggle = function() {
      if ($scope.cdash.filterdata.colorblind) {
        $rootScope.cssfile = 'colorblind';
        $.cookie('colorblind', 1, { expires: 365 } );

      }
      else {
        $rootScope.cssfile = 'cdash';
        $.cookie('colorblind', 0, { expires: 365 } );
      }
    };

    $scope.showfilters_toggle = function() {
      $scope.showfilters = !$scope.showfilters;
      filters.toggle($scope.showfilters);
    };

    $scope.numBuildsPerPageChanged = function(obj) {
      $.cookie('num_builds_per_page', obj.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged(obj);
    };

    $scope.pageChanged = function(obj) {
      const begin = ((obj.pagination.currentPage - 1) * obj.pagination.numPerPage)
        , end = begin + obj.pagination.numPerPage;
      if (end > 0) {
        obj.pagination.filteredBuilds = obj.builds.slice(begin, end);
      }
      else {
        obj.pagination.filteredBuilds = obj.builds;
      }
    };

    $scope.updateOrderByFields = function(obj, field, $event, whichTable) {
      whichTable = whichTable || 'buildgroup';
      let cookie_name = '';
      multisort.updateOrderByFields(obj, field, $event);
      switch (whichTable) {
      case 'buildgroup':
      default:
        cookie_name = $scope.getCookieName(obj, $scope.cdash.projectname, $scope.cdash.childview);
        obj.builds = $filter('orderBy')(obj.builds, obj.orderByFields);
        obj.builds = $filter('showEmptyBuildsLast')(obj.builds, obj.orderByFields);
        $scope.pageChanged(obj);
        break;
      case 'coverage':
        cookie_name = `cdash_${$scope.cdash.projectname}_coverage_sort`;
        break;
      case 'DA':
        cookie_name = `cdash_${$scope.cdash.projectname}_DA_sort`;
        break;
      case 'subproject':
        cookie_name = `cdash_${$scope.cdash.projectname}_subproject_sort`;
        break;
      }
      // Save the new sort order in a cookie.
      $.cookie(cookie_name, obj.orderByFields);
    };

    $scope.normalBuild = function(build) {
      // eslint-disable-next-line eqeqeq
      return build.numchildren == 0 || build.expectedandmissing == 1;
    };

    $scope.parentBuild = function(build) {
      // eslint-disable-next-line eqeqeq
      return build.numchildren > 0 || build.expectedandmissing == 1;
    };

    $scope.getCookieName = function(buildgroup, projectname, childview) {
      let cookie_name = `cdash_${projectname}`;
      // eslint-disable-next-line eqeqeq
      if (childview == 1) {
        cookie_name += '_child_index';
      }
      else {
        cookie_name += '_index';
      }
      cookie_name += buildgroup.name;
      cookie_name += '_sort';
      return cookie_name;
    };
  });
