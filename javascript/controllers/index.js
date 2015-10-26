CDash.filter("showExpectedLast", function () {
  // Keep 'expected' builds at the bottom of the build group.
  return function (builds) {
    if (!angular.isArray(builds)) return;
    var present = builds.filter(function (build) {
      return !('expectedandmissing' in build);
    });
    var expecteds = builds.filter(function (build) {
      return 'expectedandmissing' in build;
    });
    return present.concat(expecteds);
  };
})
.controller('IndexController', function IndexController($scope, $rootScope, $location, $anchorScroll, $http, multisort) {
  // Show spinner while page is loading.
  $scope.loading = true;

  // Hide filters & settings dropdown menu by default.
  $scope.showfilters = false;
  $scope.showsettings = false;

  $scope.sortCoverage = { orderByFields: [] };

  // Show/hide feed based on cookie settings.
  var feed_cookie = $.cookie('cdash_hidefeed');
  if(feed_cookie) {
    $scope.showFeed = false;
  } else {
    $scope.showFeed = true;
  }

  $http({
    url: 'api/v1/index.php',
    method: 'GET',
    params: $rootScope.queryString
  }).success(function(cdash) {
    // Set title in root scope so the head controller can see it.
    $rootScope['title'] = cdash.title;

    // Setup default sorting based on group name.
    for (var i in cdash.buildgroups) {
      if (!cdash.buildgroups.hasOwnProperty(i)) {
        continue;
      }

      cdash.buildgroups[i].orderByFields = [];

      // For groups that "seem" nightly, sort by errors & such in the
      // following priority order:
      if (cdash.buildgroups[i].name.toLowerCase().indexOf("nightly") != -1) {
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
      }
      // For all groups, sort by build time.
      cdash.buildgroups[i].orderByFields.push('-builddatefull');
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
    $(group).html("fetching...<img src=images/loading.gif></img>");
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
    $(group).html("fetching...<img src=images/loading.gif></img>");
    $(group).load("ajax/expectedbuildgroup.php?siteid="+siteid+"&buildname="+buildname+"&buildtype="+buildtype+"&buildgroup="+buildgroupid+"&divname="+divname,{},function(){$(this).fadeIn('slow');});
  };

  $scope.buildinfo_click = function(buildid) {
    var group = "#buildgroup_"+buildid;
    if($(group).html() != "" && $(group).is(":visible")) {
      $(group).fadeOut('medium');
      return;
    }
    $(group).fadeIn('slow');
    $(group).html("fetching...<img src=images/loading.gif></img>");
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
    $(group).html("fetching...<img src=images/loading.gif></img>");
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

  $scope.updateOrderByFields = function(obj, field, $event) {
    multisort.updateOrderByFields(obj, field, $event);
  };

});
