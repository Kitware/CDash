CDash.controller('TestOverviewController',
  function TestOverviewController($scope, $rootScope, $http, renderTimer) {
    $scope.loading = true;
    $http({
      url: 'api/v1/testOverview.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      renderTimer.initialRender($scope, cdash);
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
      $rootScope.setupCalendar($scope.cdash.date);
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.groupSelectionChanged = function() {
      var url = 'testOverview.php?project=' + $scope.cdash.projectname + '&date=' + $scope.cdash.date;
      if ($scope.cdash.groupSelection > 0) {
        url += '&groupSelection=' + $scope.cdash.groupSelection;
      }
      window.location = url;
    };
});
