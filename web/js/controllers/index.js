CDash.filter("showEmptyBuildsLast", function () {
  // Move missing expected builds and those missing data to the bottom of the table.
  return function (builds, sortField) {
    if (!angular.isArray(builds)) return;

    // Expected builds that haven't submitted yet will appear
    // at the bottom of the table.
    var nonempty = builds.filter(function (build) {
      return !('expectedandmissing' in build);
    });
    var expecteds = builds.filter(function (build) {
      return 'expectedandmissing' in build;
    });

    // Get the primary (first) field that we're sorting by.
    if (angular.isArray(sortField)) {
      if (sortField.length < 1) {
        return nonempty.concat(expecteds);
      }
      sortField = sortField[0];
    }
    if (sortField.charAt(0) == '-') {
      sortField = sortField.substring(1);
    }

    // The only sort fields that could have missing data have
    // a '.' in their name (update.files, compilation.errors, etc.)
    // So if we're not sorting by one of them, we can return early.
    var idx = sortField.indexOf('.');
    if (idx === -1) {
      return nonempty.concat(expecteds);
    }

    // Put builds that don't have any data for our sortField
    // at the bottom of the table, but above the expected-and-missing
    // builds.
    var dataField = sortField.substr(0, idx);
    var present = nonempty.filter(function (build) {
      return dataField in build;
    });
    var missing = nonempty.filter(function (build) {
      return !(dataField in build);
    });

    present = present.concat(missing);
    return present.concat(expecteds);
  };
})


.controller('IndexController', function IndexController($scope, $rootScope, $location, $anchorScroll, $http, $filter, multisort, filters) {
  // Show spinner while page is loading.
  $scope.loading = true;

  // Hide filters & settings dropdown menu by default.
  $scope.showfilters = false;
  $scope.showsettings = false;

  $scope.sortCoverage = { orderByFields: [] };
  $scope.sortDA = { orderByFields: [] };

  // Show/hide feed based on cookie settings.
  var feed_cookie = $.cookie('cdash_hidefeed');
  if(feed_cookie) {
    $scope.showFeed = false;
  } else {
    $scope.showFeed = true;
  }

  // Check for filters
  $rootScope.queryString['filterstring'] = filters.getString();

  $http({
    url: 'api/v1/index.php',
    method: 'GET',
    params: $rootScope.queryString
  }).success(function(cdash) {
    // Set title in root scope so the head controller can see it.
    $rootScope['title'] = cdash.title;

    for (var i in cdash.buildgroups) {
      if (!cdash.buildgroups.hasOwnProperty(i)) {
        continue;
      }

      // Initialize pagination settings.
      cdash.buildgroups[i].pagination = [];
      cdash.buildgroups[i].pagination.filteredBuilds = [];
      cdash.buildgroups[i].pagination.currentPage = 1;
      cdash.buildgroups[i].pagination.maxSize = 5;
      var num_per_page_cookie = $.cookie('num_builds_per_page');
      if(num_per_page_cookie) {
        cdash.buildgroups[i].pagination.numPerPage = parseInt(num_per_page_cookie);
      } else {
        cdash.buildgroups[i].pagination.numPerPage = 10;
      }

      // Setup default sorting.
      cdash.buildgroups[i].orderByFields = [];

      // When viewing the children of a single build, show problematic
      // SubProjects sorted oldest to newest.
      if (cdash.childview == 1) {
        if (cdash.buildgroups[i].numbuilderror > 0) {
          cdash.buildgroups[i].orderByFields.push('-compilation.error');
        } else if (cdash.buildgroups[i].numconfigureerror > 0) {
          cdash.buildgroups[i].orderByFields.push('-configure.error');
        } if (cdash.buildgroups[i].numtestfail > 0) {
          cdash.buildgroups[i].orderByFields.push('-test.fail');
        }
        cdash.buildgroups[i].orderByFields.push('builddatefull');
      } else if (!('sorttype' in cdash.buildgroups[i])) {
        // By default, sort by errors & such in the following priority order:
        // configure errors
        if (cdash.buildgroups[i].numconfigureerror > 0) {
          cdash.buildgroups[i].orderByFields.push('-configure.error');
        }
        // build errors
        if (cdash.buildgroups[i].numbuilderror > 0) {
          cdash.buildgroups[i].orderByFields.push('-compilation.error');
        }
        // tests failed
        if (cdash.buildgroups[i].numtestfail > 0) {
          cdash.buildgroups[i].orderByFields.push('-test.fail');
        }
        // tests not run
        if (cdash.buildgroups[i].numtestnotrun > 0) {
          cdash.buildgroups[i].orderByFields.push('-test.notrun');
        }
        // configure warnings
        if (cdash.buildgroups[i].numconfigurewarning > 0) {
          cdash.buildgroups[i].orderByFields.push('-configure.warning');
        }
        // build warnings
        if (cdash.buildgroups[i].numbuildwarning > 0) {
          cdash.buildgroups[i].orderByFields.push('-compilation.warning');
        }
        cdash.buildgroups[i].orderByFields.push('-builddatefull');
      } else if (cdash.buildgroups[i]['sorttype'] == 'time') {
        // For continuous integration groups, the most recent builds
        // should be at the top of the list.
        cdash.buildgroups[i].orderByFields.push('-builddatefull');
      }

      // Initialize paginated results.
      cdash.buildgroups[i].builds = $filter('orderBy')(cdash.buildgroups[i].builds, cdash.buildgroups[i].orderByFields);
      cdash.buildgroups[i].builds = $filter('showEmptyBuildsLast')(cdash.buildgroups[i].builds, cdash.buildgroups[i].orderByFields);

      // Mark this group has having "normal" builds if it only contains missing & expected builds.
      if (!cdash.buildgroups[i].hasnormalbuilds && !cdash.buildgroups[i].hasparentbuilds && cdash.buildgroups[i].builds.length > 0) {
        cdash.buildgroups[i].hasnormalbuilds = true;
      }

      $scope.pageChanged(cdash.buildgroups[i]);
    }

    // Check if we should display filters.
    if (cdash.filterdata && cdash.filterdata.showfilters == 1) {
      $scope.showfilters = true;
    }

    $scope.cdash = cdash;

    $rootScope.setupCalendar($scope.cdash.date);

    // TODO: read from cookie
    $scope.cdash.advancedview = 0;

    var projectid = $scope.cdash.projectid;
    if (projectid > 0 && $scope.cdash.feed) {
      // Setup the feed.  This functionality used to live in cdashFeed.js.
      setInterval(function() {
        if($scope.showFeed) {
          $("#feed").load("ajax/getfeed.php?projectid="+projectid,{},function(){$(this).fadeIn('slow');})
        }
      }, 30000); // 30s
    }

    // Honor intra-page anchors.
    if ($location.path() != '') {
      $location.hash($location.path().replace('/',''));
      $location.path("");
      $anchorScroll();
    }

  }).finally(function() {
    $scope.loading = false; // hide the "loading" spinner
  });

  $scope.toggleFeed = function() {
    if ($scope.loading) { return; }
    $scope.showFeed = !$scope.showFeed;
    if($scope.showFeed) {
      $.cookie('cdash_hidefeed', null);
      $("#feed").load("ajax/getfeed.php?projectid="+$scope.cdash.projectid,{},function(){$(this).fadeIn('slow');});
      }
    else {
      $.cookie('cdash_hidefeed',1);
    }
  };

  // The following functions were moved here from cdashBuildGroup.js
  $scope.URLencode = function(sStr) {
    return escape(sStr)
      .replace(/\+/g, '%2B')
      .replace(/\"/g,'%22')
      .replace(/\'/g, '%27');
  };

  $scope.toggleAdminOptions = function(build) {
    if (!("expected" in build) || (build.expected != 0 && build.expected != 1)) {
      build.loadingExpected = 1;
      // Determine whether or not this is an expected build.
      $http({
        url: 'api/v1/is_build_expected.php',
        method: 'GET',
        params: { 'buildid': build.id }
      }).success(function(response) {
        build.loadingExpected = 0;
        if ("expected" in response) {
          build.expected = response.expected;
          if ( !("showAdminOptions" in build) || build.showAdminOptions == 0) {
            build.showAdminOptions = 1;
          } else {
            build.showAdminOptions = 0;
          }
        }
      });
    } else {
      if ( !("showAdminOptions" in build) || build.showAdminOptions == 0) {
        build.showAdminOptions = 1;
      } else {
        build.showAdminOptions = 0;
      }
    }
  };

  $scope.buildgroup_click = function(buildid) {
    var group = "#buildgroup_"+buildid;
    if($(group).html() != "" && $(group).is(":visible")) {
      $(group).fadeOut('medium');
      return;
    }
    $(group).fadeIn('slow');
    $(group).html("fetching...<img src=img/loading.gif></img>");
    $(group).load("ajax/addbuildgroup.php?buildid="+buildid,{},function(){$(this).fadeIn('slow');});
  };

  $scope.buildnosubmission_click = function(siteid,buildname,divname,buildgroupid,buildtype) {
    buildname = $scope.URLencode(buildname);
    buildtype = $scope.URLencode(buildtype);

    var group = "#infoexpected_"+divname;
    if($(group).html() != "" && $(group).is(":visible")) {
      $(group).fadeOut('medium');
      return;
    }

    $(group).fadeIn('slow');
    $(group).html("fetching...<img src=img/loading.gif></img>");
    $(group).load("ajax/expectedbuildgroup.php?siteid="+siteid+"&buildname="+buildname+"&buildtype="+buildtype+"&buildgroup="+buildgroupid+"&divname="+divname,{},function(){$(this).fadeIn('slow');});
  };

  $scope.buildinfo_click = function(buildid) {
    var group = "#buildgroup_"+buildid;
    if($(group).html() != "" && $(group).is(":visible")) {
      $(group).fadeOut('medium');
      return;
    }
    $(group).fadeIn('slow');
    $(group).html("fetching...<img src=img/loading.gif></img>");
    $(group).load("ajax/buildinfogroup.php?buildid="+buildid,{},function(){$(this).fadeIn('slow');});
  };

  $scope.expectedinfo_click = function(siteid,buildname,divname,projectid,buildtype,currentime) {
    buildname = $scope.URLencode(buildname);
    var group = "#infoexpected_"+divname;
    if($(group).html() != "" && $(group).is(":visible")) {
      $(group).fadeOut('medium');
      return;
    }
    $(group).fadeIn('slow');
    $(group).html("fetching...<img src=img/loading.gif></img>");
    $(group).load("ajax/expectedinfo.php?siteid="+siteid+"&buildname="+buildname+"&projectid="+projectid+"&buildtype="+buildtype+"&currenttime="+currentime,{},function(){$(this).fadeIn('slow');});
  };


  $scope.removeBuild = function(build) {
    if (window.confirm("Are you sure you want to remove this build?")) {
      var parameters = { buildid: build.id };
        $http({
          url: 'api/v1/build.php',
          method: 'DELETE',
          params: parameters
        }).success(function() {
          // Find the index of the build to remove.
          var idx1 = -1;
          var idx2 = -1;
          for (var i in $scope.cdash.buildgroups) {
            for (var j = 0, len = $scope.cdash.buildgroups[i].builds.length; j < len; j++) {
              if ($scope.cdash.buildgroups[i].builds[j].id === build.id) {
                idx1 = i;
                idx2 = j;
                break;
              }
            }
            if (idx1 != -1) {
              break;
            }
          }
          if (idx1 > -1 && idx2 > -1) {
            // Remove the build from our scope.
            $scope.cdash.buildgroups[idx1].builds.splice(idx2, 1);
          }
      });
    }
  };


  $scope.toggleExpected = function(build, groupid) {
    var newExpectedValue = 1;
    if (build.expected == 1) {
      newExpectedValue = 0;
    }
    var parameters = {
      buildid: build.id,
      groupid: groupid,
      expected: newExpectedValue
    };
    $http.post('api/v1/build.php', parameters)
    .success(function(data) {
      build.expected = newExpectedValue;
    });
  };

  $scope.moveToGroup = function(build, groupid) {
    var parameters = {
      buildid: build.id,
      newgroupid: groupid,
      expected: build.expected
    };
    $http.post('api/v1/build.php', parameters)
    .success(function(data) {
      window.location.reload();
    });
  };

  $scope.colorblind_toggle = function() {
    if ($scope.cdash.filterdata.colorblind) {
      $rootScope.cssfile = "colorblind.css";
      $.cookie("colorblind", 1, { expires: 365 } );

    } else {
      $rootScope.cssfile = "cdash.css";
      $.cookie("colorblind", 0, { expires: 365 } );
    }
  };

  $scope.showfilters_toggle = function() {
    $scope.showfilters = !$scope.showfilters;
    filters.toggle($scope.showfilters);
  };

  $scope.numBuildsPerPageChanged = function(obj) {
    $.cookie("num_builds_per_page", obj.pagination.numPerPage, { expires: 365 });
    $scope.pageChanged(obj);
  };

  $scope.pageChanged = function(obj) {
    var begin = ((obj.pagination.currentPage - 1) * obj.pagination.numPerPage)
    , end = begin + obj.pagination.numPerPage;
    if (end > 0) {
      obj.pagination.filteredBuilds = obj.builds.slice(begin, end);
    } else {
      obj.pagination.filteredBuilds = obj.builds;
    }
  };

  $scope.updateOrderByFields = function(obj, field, $event) {
    multisort.updateOrderByFields(obj, field, $event);
    if ('pagination' in obj && 'builds' in obj) {
      obj.builds = $filter('orderBy')(obj.builds, obj.orderByFields);
      obj.builds = $filter('showEmptyBuildsLast')(obj.builds, obj.orderByFields);
      $scope.pageChanged(obj);
    }
  };

  $scope.normalBuild = function(build) {
    return build.numchildren == 0 || build.expectedandmissing == 1;
  };

  $scope.parentBuild = function(build) {
    return build.numchildren > 0 || build.expectedandmissing == 1;
  };
})
.directive('normalBuild', function() {
  return {
    templateUrl: 'views/partials/build.html'
  }
})
.directive('parentBuild', function() {
  return {
    templateUrl: 'views/partials/parentbuild.html'
  }
});
