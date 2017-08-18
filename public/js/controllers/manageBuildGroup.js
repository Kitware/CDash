CDash.filter('filter_builds', function() {
  // Filter the builds based on what group they belong to.
  return function(input, group) {
    if (typeof group === 'undefined' || group === null) {
      // No filtering required for default "All" group.
      return input;
    }

    group_id = Number(group.id);
    var output = [];
    for (var i = 0; i < input.length; i++) {
      if (Number(input[i].groupid) === group_id) {
        output.push(input[i]);
      }

    }
    return output;
  };
})

.filter('filter_buildgroups', function() {
  // Filter BuildGroups based on their type
  return function(input, type) {
    if (typeof type === 'undefined' || type === null) {
      return input;
    }
    var output = [];
    for (var i = 0; i < input.length; i++) {
      if (input[i].type === type) {
        output.push(input[i]);
      }
    }
    return output;
  };
})

.controller('ManageBuildGroupController', function ManageBuildGroupController($scope, $http, apiLoader, $uibModal) {
  apiLoader.loadPageData($scope, 'api/v1/manageBuildGroup.php');
  $scope.finishSetup = function() {
    // Sort BuildGroups by position.
    if ($scope.cdash.buildgroups) {
      $scope.cdash.buildgroups.sort(function (a, b) {
        return Number(a.position) - Number(b.position);
      });

      // Update positions when the user stops dragging.
      $scope.sortable = {
        stop: function(e, ui) {
          for (var index in $scope.cdash.buildgroups) {
            $scope.cdash.buildgroups[index].position = index;
          }
        }
      };
    }

    // Define different types of buildgroups.
    $scope.cdash.buildgrouptypes = [
      {name: "Daily", value: "Daily"},
      {name: "Latest", value: "Latest"}
    ];
    $scope.buildType = $scope.cdash.buildgrouptypes[0];
  };

  /** create a new buildgroup */
  $scope.createBuildGroup = function(newBuildGroup, type) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      newbuildgroup: newBuildGroup,
      type: type
    };
    $http.post('api/v1/buildgroup.php', parameters)
    .then(function success(s) {
      var buildgroup = s.data;
      if (buildgroup.error) {
        $scope.cdash.error = buildgroup.error;
      }
      else {
        $("#buildgroup_created").show();
        $("#buildgroup_created").delay(3000).fadeOut(400);

        // Add this new buildgroup to our scope.
        $scope.cdash.buildgroups.push(buildgroup);

        if (type != "Daily") {
          $scope.cdash.dynamics.push(buildgroup);
        }
      }
    });
  };

  /** change the order that the buildgroups appear in */
  $scope.updateBuildGroupOrder = function() {
    var newLayout = getSortedElements("#sortable");
    var parameters = {
      projectid: $scope.cdash.projectid,
      newLayout: newLayout
    };
    $http.post('api/v1/buildgroup.php', parameters)
    .then(function success(s) {
      if (s.data.error) {
        $scope.cdash.error = s.data.error;
      }
      else {
        $("#order_updated").show();
        $("#order_updated").delay(3000).fadeOut(400);
      }
    });
  };

  /** modify an existing buildgroup */
  $scope.saveBuildGroup = function(buildgroup, summaryemail) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      buildgroup: buildgroup
    };
    $http({
      url: 'api/v1/buildgroup.php',
      method: 'PUT',
      params: parameters
    }).then(function success(s) {
      if (s.data.error) {
        $scope.cdash.error = s.data.error;
      }
      else {
        $("#buildgroup_updated_" + buildgroup.id).show();
        $("#buildgroup_updated_" + buildgroup.id).delay(3000).fadeOut(400);
      }
    });
  };

  /** delete a buildgroup */
  $scope.deleteBuildGroup = function (buildgroupid) {
    "use strict";
    var parameters = {
      projectid: $scope.cdash.projectid,
      buildgroupid: buildgroupid
    };
    $http({
      url: 'api/v1/buildgroup.php',
      method: 'DELETE',
      params: parameters
    }).then(function success() {
      // Find the index of the group to remove.
      var index = -1;
      for(var i = 0, len = $scope.cdash.buildgroups.length; i < len; i++) {
        if ($scope.cdash.buildgroups[i].id === buildgroupid) {
          index = i;
          break;
        }
      }
      if (index > -1) {
        // Remove the buildgroup from our scope.
        $scope.cdash.buildgroups.splice(index, 1);
      }
    });
  };

  /** displays confirmation modal **/
  $scope.showModal = function(buildgroupid) {
    $modal = $uibModal.open({
      animation: true,
      size: 'sm',
      backdrop: true,
      templateUrl: 'modal-template',
      controller: function() {
        var $ctrl = this;
        $ctrl.ok = function() {
          "use strict";
          $scope.deleteBuildGroup(buildgroupid)
          $modal.close();
        };
        $ctrl.cancel = function () {
          "use strict";
          $modal.close();
        }
      },
      controllerAs: '$ctrl',
    });
  };

  /** move builds to a different group */
  $scope.moveBuilds = function(builds, group, expected) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      builds: builds,
      group: group,
      expected: expected
    };
    $http.post('api/v1/buildgroup.php', parameters)
    .then(function success(s) {
      if (s.data.error) {
        $scope.cdash.error = s.data.error;
      }
      else {
        $("#builds_moved").show();
        $("#builds_moved").delay(3000).fadeOut(400);
      }
    });
  };


  /** Add rule for a wildcard BuildGroup */
  $scope.addWildcardRule = function(group, type, nameMatch) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      group: group,
      type: type,
      nameMatch: nameMatch
    };
    $http.post('api/v1/buildgroup.php', parameters)
    .then(function success(s) {
      if (s.data.error) {
        $scope.cdash.error = s.data.error;
      }
      else {
        $("#wildcard_defined").show();
        $("#wildcard_defined").delay(3000).fadeOut(400);
      }
    });
  };


  /** delete a wildcard rule */
  $scope.deleteWildcardRule = function(wildcard) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      wildcard: wildcard
    };
    $http({
      url: 'api/v1/buildgroup.php',
      method: 'DELETE',
      params: parameters
    }).then(function success() {
      // Find the index of the wildcard to remove.
      var index = $scope.cdash.wildcards.indexOf(wildcard);
      if (index > -1) {
        // Remove this wildcard from our scope.
        $scope.cdash.wildcards.splice(index, 1);
      }
    });
  };


  /** add a build row to a dynamic group */
  $scope.addDynamicRow = function(dynamic, buildgroup, site, match) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      dynamic: dynamic,
      buildgroup: buildgroup,
      site: site,
      match: match
    };
    $http.post('api/v1/buildgroup.php', parameters)
    .then(function success(s) {
      if (s.data.error) {
        $scope.cdash.error = s.data.error;
      }
      else {
        // Add this new rule to our scope.
        var idx = $scope.cdash.dynamics.indexOf(dynamic);
        if (idx > -1) {
          if ($scope.cdash.dynamics[idx].rules) {
            $scope.cdash.dynamics[idx].rules.push(rule);
          } else {
            $scope.cdash.dynamics[idx].rules = [rule];
          }
        }
        $("#dynamic_defined").show();
        $("#dynamic_defined").delay(3000).fadeOut(400);
      }
    });
  };

  $scope.deleteDynamicRule = function(dynamic, rule) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      dynamic: dynamic,
      rule: rule
    };
    $http({
      url: 'api/v1/buildgroup.php',
      method: 'DELETE',
      params: parameters
    }).then(function success() {
      // Find the index of the dynamic group in question.
      var idx1 = $scope.cdash.dynamics.indexOf(dynamic);
      if (idx1 > -1) {
        // Then find the index of the rule that's being removed.
        var idx2 = $scope.cdash.dynamics[idx1].rules.indexOf(rule);
        if (idx2 > -1) {
          // And remove it from our scope.
          $scope.cdash.dynamics[idx1].rules.splice(idx2, 1);
        }
      }
    });
  };


});
