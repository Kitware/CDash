CDash.controller('SubProjectController', function SubProjectController($scope, $rootScope, $http) {
  $scope.dataLoaded = false;

  $scope.loadData = function(id) {
    if ($scope.dataLoaded) {
      // Data already loaded, no need to do it again.
      return;
    }
    else {
      $rootScope.queryString['subprojectid'] = id;
      $http({
        url: 'api/v1/subproject.php',
        method: 'GET',
        params: $rootScope.queryString
      }).then(function success(s) {
        $scope.details = s.data;

        // Create a reference to this subproject's group.
        var index = -1;
        for(var i = 0, len = $scope.cdash.groups.length; i < len; i++) {
          if ($scope.cdash.groups[i].id === $scope.details.group) {
            $scope.details.group = $scope.cdash.groups[i];
            break;
          }
        }

        $scope.dataLoaded = true;
      });
    }
  };

  $scope.deleteSubProject = function(id) {
    var parameters = {
      projectid: $scope.details.projectid,
      subprojectid: id
    };

    $http({
      url: 'api/v1/subproject.php',
      method: 'DELETE',
      params: parameters
    }).then(function success() {
      // Find the index of the subproject to remove.
      var index = -1;
      for(var i = 0, len = $scope.cdash.subprojects.length; i < len; i++) {
        if ($scope.cdash.subprojects[i].id === id) {
          index = i;
          break;
        }
      }
      if (index > -1) {
        // Remove the subproject from our scope.
        $scope.cdash.subprojects.splice(index, 1);
      }
    });
  };

  $scope.addDependency = function(dependency, subprojectId) {
    var parameters = {
      projectid: $scope.details.projectid,
      subprojectid: subprojectId,
      dependencyid: dependency.id
    };

    $http({
      url: 'api/v1/subproject.php',
      method: 'PUT',
      params: parameters
    }).then(function success() {
      // Find the index of the dependency we just added.
      var index = -1;
      for(var i = 0, len = $scope.details.available_dependencies.length; i < len; i++) {
        if ($scope.details.available_dependencies[i].id === dependency.id) {
          index = i;
          break;
        }
      }
      if (index > -1) {
        // Remove this subproject from our list of available dependencies.
        var added = $scope.details.available_dependencies.splice(index, 1);
        // And add it to our list of dependencies.
        $scope.details.dependencies.push(added[0]);
      }
    });
  };

  $scope.removeDependency = function(dependencyId, subprojectId) {
    var parameters = {
      projectid: $scope.details.projectid,
      subprojectid: subprojectId,
      dependencyid: dependencyId
    };

    $http({
      url: 'api/v1/subproject.php',
      method: 'DELETE',
      params: parameters
    }).then(function success() {
      // Find the index of the dependency to remove.
      var index = -1;
      for(var i = 0, len = $scope.details.dependencies.length; i < len; i++) {
        if ($scope.details.dependencies[i].id === dependencyId) {
          index = i;
          break;
        }
      }
      if (index > -1) {
        // Remove this subproject from our list of dependencies.
        var removed = $scope.details.dependencies.splice(index, 1);
        // And add it to our list of potential dependencies.
        $scope.details.available_dependencies.push(removed[0]);
      }
    });
  };

  $scope.changeGroup = function() {
    var parameters = {
      projectid: $scope.details.projectid,
      subprojectid: $scope.details.subprojectid,
      groupname: $scope.details.group.name
    };
    $http({
      url: 'api/v1/subproject.php',
      method: 'PUT',
      params: parameters
    }).then(function success() {
      $("#group_changed_" + $scope.details.subprojectid).show();
      $("#group_changed_" + $scope.details.subprojectid).delay(3000).fadeOut(400);
    });
  };

});
