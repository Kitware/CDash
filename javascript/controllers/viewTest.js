CDash.controller('ViewTestController',
  function ViewTestController($scope, $rootScope, $http, $filter, multisort) {
    $scope.loading = true;

    // Pagination settings.
    $scope.pagination = [];
    $scope.pagination.filteredTests = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.numPerPage = 25;
    $scope.pagination.maxSize = 5;

    // Hide filters by default.
    $scope.showfilters = false;

    // Default sorting : failed tests in alphabetical order.
    $scope.orderByFields = ['status', 'name'];

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
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.setPage(1);
    });

    $scope.setPage = function (pageNo) {
      var begin = ((pageNo - 1) * $scope.pagination.numPerPage)
      , end = begin + $scope.pagination.numPerPage;
      $scope.pagination.filteredTests = $scope.cdash.tests.slice(begin, end);
    };

    $scope.pageChanged = function() {
      $scope.setPage($scope.pagination.currentPage);
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $scope.cdash.tests = $filter('orderBy')($scope.cdash.tests, $scope.orderByFields);
      $scope.pageChanged();
    };
});
