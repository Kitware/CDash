CDash.controller('UserController', function UserController($scope, $http, $timeout, apiLoader) {
  apiLoader.loadPageData($scope, 'api/v1/user.php');

  $scope.deleteSchedule = function(job) {
    if (window.confirm("Are you sure you want to delete this schedule?")) {
      var parameters = { removeschedule: job.id };
      $http({
        url: 'manageClient.php',
        method: 'GET',
        params: parameters
      }).then(function success() {
        // Find and remove this job schedule.
        var index = -1;
        for(var i = 0, len = $scope.cdash.jobschedule.length; i < len; i++) {
          if ($scope.cdash.jobschedule[i].id === job.id) {
            index = i;
            break;
          }
        }
        if (index > -1) {
          $scope.cdash.jobschedule.splice(index, 1);
        }
      }, function error(e) {
        $scope.cdash.message = e.data.error;
      });
    }
  };

  $scope.generateToken = function() {
    var parameters = { description: $scope.cdash.tokendescription };
    $http.post('api/v1/authtoken.php', parameters)
    .then(function success(s) {
      var authtoken = s.data.token;
      authtoken.copied = false;
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
    var parameters = { hash: authtoken.hash };
    $http({
      url: 'api/v1/authtoken.php',
      method: 'DELETE',
      params: parameters
    }).then(function success() {
      // Remove this token from our list.
      var index = -1;
      for(var i = 0, len = $scope.cdash.authtokens.length; i < len; i++) {
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
