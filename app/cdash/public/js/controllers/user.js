CDash.controller('UserController', ["$scope", "$http", "$timeout", "apiLoader", function UserController($scope, $http, $timeout, apiLoader) {
  apiLoader.loadPageData($scope, 'api/v1/user.php');

  $scope.generateToken = function() {
    const parameters = {
      description: $scope.cdash.tokendescription,
      scope: $scope.cdash.tokenscope === 'full_access' ? 'full_access' : 'submit_only',
      projectid: $scope.cdash.tokenscope === 'full_access'
                 || $scope.cdash.tokenscope === 'submit_only' ? -1 : $scope.cdash.tokenscope
    };
    $http.post('api/authtokens/create', parameters)
      .then(function success(s) {
        const authtoken = s.data.token;
        authtoken.copied = false;
        authtoken.raw_token = s.data.raw_token;

        $scope.cdash.projects.forEach(project => {
          if (project.id === authtoken.projectid) {
            authtoken.projectname = project.name;
          }
        });

        // A terrible hack to format the date the same way the DB returns them on initial page load
        authtoken.expires = authtoken.expires.replace('T', ' ');

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
      url: `api/authtokens/delete/${authtoken.hash}`,
      method: 'DELETE',
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

  $scope.finishSetup = function() {
    if ($scope.cdash.allow_full_access_tokens) {
      $scope.cdash.tokenscope = 'full_access';
    }
    else if ($scope.cdash.allow_submit_only_tokens) {
      $scope.cdash.tokenscope = 'submit_only';
    }
    else if ($scope.cdash.projects.length > 0) {
      $scope.cdash.tokenscope = $scope.cdash.projects[0].id.toString();
    }
    else {
      $scope.cdash.tokenscope = '';
    }
  }
}]);
