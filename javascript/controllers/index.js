CDash.filter("showExpectedLast", function () {
  // Keep 'expected' builds at the bottom of the build group.
  return function (builds) {
    if (!angular.isArray(builds)) return;
    var present = builds.filter(function (build) {
      return !('expected' in build);
    });
    var expecteds = builds.filter(function (build) {
      return 'expected' in build;
    });
    return present.concat(expecteds);
  };
})
.controller('IndexController', function IndexController($scope, $rootScope, $location, $anchorScroll, $http) {
  // Show spinner while page is loading.
  $scope.loading = true;

  // Hide filters & settings dropdown menu by default.
  $scope.showfilters = false;
  $scope.showsettings = false;

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
      cdash.buildgroups[i].reverseSort = true;
      // For groups that "seem" nightly, sort by errors & such in the
      // following priority order:
      if (cdash.buildgroups[i].name.toLowerCase().indexOf("nightly") != -1) {
        // configure errors
        if (cdash.buildgroups[i].numconfigureerror > 0) {
          cdash.buildgroups[i].orderByField = 'configure.error';
        }
        // build errors
        else if (cdash.buildgroups[i].numbuilderror > 0) {
          cdash.buildgroups[i].orderByField = 'compilation.error';
        }
        // tests failed
        else if (cdash.buildgroups[i].numtestfail > 0) {
          cdash.buildgroups[i].orderByField = 'test.fail';
        }
        // tests not run
        else if (cdash.buildgroups[i].numtestnotrun > 0) {
          cdash.buildgroups[i].orderByField = 'test.notrun';
        }
        // configure warnings
        else if (cdash.buildgroups[i].numconfigurewarning > 0) {
          cdash.buildgroups[i].orderByField = 'configure.warning';
        }
        // build warnings
        else if (cdash.buildgroups[i].numbuildwarning > 0) {
          cdash.buildgroups[i].orderByField = 'compilation.warning';
        }
        // build time
        else {
          cdash.buildgroups[i].orderByField = 'builddatefull';
        }
      }
      // Otherwise, sort by build time.
      else {
        cdash.buildgroups[i].orderByField = 'builddatefull';
        cdash.buildgroups[i].reverseSort = true;
      }
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

  $scope.removebuild_click = function(buildid) {
    if(confirm("Are you sure you want to remove this build?")) {
      var group = "#buildgroup_"+buildid;
      $(group).html("updating...");
      $.post("ajax/addbuildgroup.php?buildid="+buildid,{removebuild:"1",buildid:buildid}, function(data) {
        $(group).html("deleted.");
        $(group).fadeOut('slow');
        location.reload();
        return false;
      });
    }
  };

  $scope.markasexpected_click = function(buildid,groupid,expected) {
    var group = "#buildgroup_"+buildid;
    $(group).html("updating...");
    $.post("ajax/addbuildgroup.php?buildid="+buildid,{markexpected:"1",groupid:groupid,expected:expected}, function(data) {
      $(group).html("updated.");
      $(group).fadeOut('slow');
      location.reload();
      return false;
    });
  };

  $scope.addbuildgroup_click = function(buildid,groupid,definerule) {
    var expected = "expected_"+buildid+"_"+groupid;
    var t = document.getElementById(expected);
    var expectedbuild = 0;
    if(t.checked) {
      expectedbuild = 1;
    }

    var group = "#buildgroup_"+buildid;
    $(group).html("addinggroup");
    $.post("ajax/addbuildgroup.php?buildid="+buildid,{submit:"1",groupid:groupid,expected:expectedbuild,definerule:definerule}, function(data) {
      $(group).html("added to group.");
      $(group).fadeOut('slow');
      location.reload();
      return false;
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

});
