CDash.controller('UserController', function UserController($scope, $http, $timeout, apiLoader) {
  apiLoader.loadPageData($scope, 'api/v1/user.php');

  $scope.generateToken = function() {
    const parameters = {
      description: $scope.cdash.tokendescription,
      scope: $scope.cdash.tokenscope === 'full_access' ? 'full_access' : 'submit_only',
      projectid: $scope.cdash.tokenscope === 'full_access'
                 || $scope.cdash.tokenscope === 'submit_only' ? -1 : $scope.cdash.tokenscope
    };
    $http.post('api/v1/authtoken.php', parameters)
    .then(function success(s) {
      const authtoken = s.data.token;
      authtoken.copied = false;
      authtoken.raw_token = s.data.raw_token;

      $scope.cdash.projects.forEach(project => {
        if (project.id === authtoken.projectid) {
          authtoken.projectname = project.name;
        }
      });

      $scope.cdash.authtokens.push(authtoken);
    }, function error(e) {
      $scope.cdash.message = e.data.error;
    });
  };

  $scope.copyTokenSuccess = function(token) {
    token.copied = true;
    token.showcheck = true;
    $timeout(function() {
      token.showcheck = false;
    }, 2000);
  };

  $scope.revokeToken = function(authtoken) {
    $http({
      url: 'api/v1/authtoken.php',
      method: 'DELETE',
      params: { hash: authtoken.hash }
    }).then(function success() {
      // Remove this token from our list.
      let index = -1;
      for(let i = 0, len = $scope.cdash.authtokens.length; i < len; i++) {
        if ($scope.cdash.authtokens[i].hash === authtoken.hash) {
          index = i;
          break;
        }
      }
      if (index > -1) {
        $scope.cdash.authtokens.splice(index, 1);
      }
    }, function error(e) {
      $scope.cdash.message = e.data.error;
    });
  };
});
