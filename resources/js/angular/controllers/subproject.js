export function SubProjectController($scope, $rootScope, $http) {
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

}
