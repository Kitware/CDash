CDash.controller('UserController', function UserController($scope, apiLoader) {
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
});
