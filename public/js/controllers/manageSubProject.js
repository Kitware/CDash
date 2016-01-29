CDash.filter('filter_subproject_groups', function() {
  // Filter the subprojects based on group.
  return function(input, group) {
    if (typeof group === 'undefined' || group === null) {
      // "No filtering required for default "All" group.
      return input;
    }

    group_id = Number(group.id);
    var output = [];
    for (var key in input) {
      if (Number(input[key].group) === group_id) {
        output.push(input[key]);
      }

    }
    return output;
  };
})
.controller('ManageSubProjectController', function ManageSubProjectController($scope, $rootScope, $http) {
  $scope.loading = true;
  $http({
    url: 'api/v1/manageSubProject.php',
    method: 'GET',
    params: $rootScope.queryString
  }).success(function(cdash) {
    $scope.cdash = cdash;
  }).finally(function() {
    $scope.loading = false;
  });

  $scope.createSubProject = function(newSubProject, groupName) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      newsubproject: newSubProject,
      group: groupName
    };
    $http.post('api/v1/subproject.php', parameters)
    .success(function(subproj) {
      if (subproj.error) {
        $scope.cdash.error = subproj.error;
      }
      else {
        $("#subproject_created").show();
        $("#subproject_created").delay(3000).fadeOut(400);

        // Add this new subproject to our scope.
        $scope.cdash.subprojects.push(subproj);
      }
    });
  };

  $scope.createGroup = function(newGroup, threshold, isDefault) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      newgroup: newGroup,
      threshold: threshold,
      isdefault: isDefault
    };
    $http.post('api/v1/subproject.php', parameters)
    .success(function(group) {

      // Update our default group if necessary.
      if (group.is_default) {
        $scope.cdash.default_group_id = group.id;
      }

      // Add this new group to our scope.
      $scope.cdash.groups.push(group);
    });
  };

  $scope.updateGroup = function(group, is_default) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      groupid: group.id,
      name: group.name,
      threshold: group.coverage_threshold,
      is_default: is_default
    };
    $http({
      url: 'api/v1/subproject.php',
      method: 'PUT',
      params: parameters
    }).success(function(resp) {
      if (resp.error) {
        $scope.cdash.error = resp.error;
      }
      else {
        $("#group_updated_" + group.id).show();
        $("#group_updated_" + group.id).delay(3000).fadeOut(400);
      }
    });
  };

  $scope.deleteGroup = function(groupId) {
    var parameters = {
      projectid: $scope.cdash.projectid,
      groupid: groupId
    };
    $http({
      url: 'api/v1/subproject.php',
      method: 'DELETE',
      params: parameters
    }).success(function() {
      // Find the index of the group to remove.
      var index = -1;
      for(var i = 0, len = $scope.cdash.groups.length; i < len; i++) {
        if ($scope.cdash.groups[i].id === groupId) {
          index = i;
          break;
        }
      }
      if (index > -1) {
        // Remove the group from our scope.
        $scope.cdash.groups.splice(index, 1);
      }
    });
  };

});
