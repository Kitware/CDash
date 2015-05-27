CDash.filter('filter_builds', function() {
  // Filter the builds based on what group they belong to.
  return function(input, group) {
    if (typeof group === 'undefined' || group === null) {
      // "No filtering required for default "All" group.
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
.controller('ManageBuildGroupController', function ManageBuildGroupController($scope, $http) {
  $http({
    url: 'api/manageBuildGroup.php',
    method: 'GET',
    params: queryString
  }).success(function(cdash) {
    $scope.cdash = cdash;

    // Sort BuildGroups by position.
    if ($scope.cdash.buildgroups) {
      $scope.cdash.buildgroups.sort(function (a, b) {
        return a.position > b.position;
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

  });

  /** create a new buildgroup */
  $scope.createBuildGroup = function(newBuildGroup) {
    var parameters = {
      projectid: $scope.cdash.project.id,
      newbuildgroup: newBuildGroup
    };
    $http.post('api/buildgroup.php', parameters)
    .success(function(buildgroup) {
      if (buildgroup.error) {
        $scope.cdash.error = buildgroup.error;
      }
      else {
        $("#buildgroup_created").show();
        $("#buildgroup_created").delay(3000).fadeOut(400);

        // Add this new buildgroup to our scope.
        $scope.cdash.buildgroups.push(buildgroup);
      }
    });
  };

  /** change the order that the buildgroups appear in */
  $scope.updateBuildGroupOrder = function() {
    var newLayout = getSortedElements("#sortable");
    var parameters = {
      projectid: $scope.cdash.project.id,
      newLayout: newLayout
    };
    $http.post('api/buildgroup.php', parameters)
    .success(function(resp) {
      if (resp.error) {
        $scope.cdash.error = resp.error;
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
      projectid: $scope.cdash.project.id,
      buildgroup: buildgroup
    };
    $http({
      url: 'api/buildgroup.php',
      method: 'PUT',
      params: parameters
    }).success(function(resp) {
      if (resp.error) {
        $scope.cdash.error = resp.error;
      }
      else {
        $("#buildgroup_updated_" + buildgroup.id).show();
        $("#buildgroup_updated_" + buildgroup.id).delay(3000).fadeOut(400);
      }
    });
  };

  /** delete a buildgroup */
  $scope.deleteBuildGroup = function(buildgroupid) {
    if (window.confirm("Are you sure you want to delete this BuildGroup? If the BuildGroup is not empty, builds will be put in their original BuildGroup.")) {

      var parameters = {
        projectid: $scope.cdash.project.id,
        buildgroupid: buildgroupid
      };
      $http({
        url: 'api/buildgroup.php',
        method: 'DELETE',
        params: parameters
      }).success(function() {
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
    }
  };


  /** move builds to a different group */
  $scope.moveBuilds = function(builds, group, expected) {
    var parameters = {
      projectid: $scope.cdash.project.id,
      builds: builds,
      group: group,
      expected: expected
    };
    $http.post('api/buildgroup.php', parameters)
    .success(function(buildgroup) {
      if (buildgroup.error) {
        $scope.cdash.error = buildgroup.error;
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
      projectid: $scope.cdash.project.id,
      group: group,
      type: type,
      nameMatch: nameMatch
    };
    $http.post('api/buildgroup.php', parameters)
    .success(function(buildgroup) {
      if (buildgroup.error) {
        $scope.cdash.error = buildgroup.error;
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
      projectid: $scope.cdash.project.id,
      wildcard: wildcard
    };
    $http({
      url: 'api/buildgroup.php',
      method: 'DELETE',
      params: parameters
    }).success(function() {
      // Find the index of the wildcard to remove.
      var index = $scope.cdash.wildcards.indexOf(wildcard);
      if (index > -1) {
        // Remove this wildcard from our scope.
        $scope.cdash.wildcards.splice(index, 1);
      }
    });
  };

});
