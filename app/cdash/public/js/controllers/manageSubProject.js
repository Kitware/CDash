CDash.filter('filter_subproject_groups', () => {
  // Filter the subprojects based on group.
  return function(input, group) {
    if (typeof group === 'undefined' || group === null) {
      // "No filtering required for default "All" group.
      return input;
    }

    group_id = Number(group.id);
    const output = [];
    for (const key in input) {
      if (Number(input[key].group) === group_id) {
        output.push(input[key]);
      }

    }
    return output;
  };
})
  .controller('ManageSubProjectController', ($scope, $http, apiLoader) => {
    apiLoader.loadPageData($scope, 'api/v1/manageSubProject.php');
    $scope.finishSetup = function() {
    // Sort groups by position.
      if ($scope.cdash.groups) {
        $scope.cdash.groups.sort((a, b) => {
          return Number(a.position) - Number(b.position);
        });
      }

      // Update positions when the user stops dragging.
      $scope.sortable = {
        stop: function(e, ui) {
          for (const index in $scope.cdash.groups) {
            $scope.cdash.groups[index].position = index;
          }
        },
      };
    };

    $scope.createSubProject = function(newSubProject, groupName) {
      const parameters = {
        projectid: $scope.cdash.projectid,
        newsubproject: newSubProject,
        group: groupName,
      };
      $http.post('api/v1/subproject.php', parameters)
        .then((s) => {
          const subproj = s.data;
          if (subproj.error) {
            $scope.cdash.error = subproj.error;
          }
          else {
            $('#subproject_created').show();
            $('#subproject_created').delay(3000).fadeOut(400);

            // Add this new subproject to our scope.
            $scope.cdash.subprojects.push(subproj);
          }
        });
    };

    $scope.createGroup = function(newGroup, threshold, isDefault) {
      const parameters = {
        projectid: $scope.cdash.projectid,
        newgroup: newGroup,
        threshold: threshold,
        isdefault: isDefault,
      };
      $http.post('api/v1/subproject.php', parameters)
        .then((s) => {
          const group = s.data;

          // Update our default group if necessary.
          if (group.is_default) {
            $scope.cdash.default_group_id = group.id;
          }

          // Add this new group to our scope.
          $scope.cdash.groups.push(group);
        });
    };

    $scope.updateGroup = function(group, is_default) {
      const parameters = {
        projectid: $scope.cdash.projectid,
        groupid: group.id,
        name: group.name,
        threshold: group.coverage_threshold,
        is_default: is_default,
      };
      $http({
        url: 'api/v1/subproject.php',
        method: 'PUT',
        params: parameters,
      }).then((s) => {
        if (s.data.error) {
          $scope.cdash.error = s.data.error;
        }
        else {
          $(`#group_updated_${group.id}`).show();
          $(`#group_updated_${group.id}`).delay(3000).fadeOut(400);
        }
      });
    };

    $scope.deleteGroup = function(groupId) {
      const parameters = {
        projectid: $scope.cdash.projectid,
        groupid: groupId,
      };
      $http({
        url: 'api/v1/subproject.php',
        method: 'DELETE',
        params: parameters,
      }).then(() => {
      // Find the index of the group to remove.
        let index = -1;
        for (let i = 0, len = $scope.cdash.groups.length; i < len; i++) {
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

    /** Change the order that the groups appear in. */
    $scope.updateGroupOrder = function() {
      const newLayout = getSortedElements('#sortable');
      const parameters = {
        projectid: $scope.cdash.projectid,
        newLayout: newLayout,
      };
      $http.post('api/v1/subproject.php', parameters)
        .then((s) => {
          if (s.data.error) {
            $scope.cdash.error = s.data.error;
          }
          else {
            $('#order_updated').show();
            $('#order_updated').delay(3000).fadeOut(400);
          }
        });
    };

  });
