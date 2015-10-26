CDash.controller('ViewTestController',
  function ViewTestController($scope, $rootScope, $http) {
    $scope.loading = true;

    // Hide filters by default.
    $scope.showfilters = false;

    $scope.orderByFields = [];
    $scope.reverseSort = true;

    $http({
      url: 'api/v1/viewTest.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      $scope.cdash = cdash;
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.updateOrderByFields = function(field, $event) {
      if ($event.shiftKey) {
        if ($scope.orderByFields.indexOf(field) < 0) {
          $scope.orderByFields.push(field);
        }
        // Don't reverse the sort order when adding fields
      }
      else {
        $scope.orderByFields = [field];
        $scope.reverseSort = !$scope.reverseSort;
      }
    };

    $scope.orderByField = function(field) {
      return $scope.orderByFields.indexOf(field) >= 0;
    };
});
