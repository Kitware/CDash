CDash.controller('UserStatisticsController',
  function UserStatisticsController($scope, $rootScope, $http, $filter, $location, multisort, renderTimer) {
    // Check for sort order cookie.
    var sort_order = [];
    var sort_cookie_value = $.cookie('cdash_user_stats_sort');
    if(sort_cookie_value) {
      sort_order = sort_cookie_value.split(",");
    } else {
      // Default sorting priority.  The goal here is to put the most active
      // and helpful developers at the top of the list, with an emphasis
      // towards rewarding good behavior as opposed to punishing errors.
      sort_order = ['-totalupdatedfiles', '-fixed_errors', '-fixed_warnings',
                    '-fixed_tests', 'failed_errors', 'failed_warnings',
                    'failed_tests'];
    }
    $scope.orderByFields = sort_order;

    $scope.loading = true;
    $http({
      url: 'api/v1/userStatistics.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      renderTimer.initialRender($scope, cdash);
      $scope.cdash.users = $filter('orderBy')($scope.cdash.users, $scope.orderByFields);
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
      $rootScope.setupCalendar($scope.cdash.date);
    }).finally(function() {
      $scope.loading = false;
    });

    $scope.defaultSorting = function() {
      $scope.orderByFields =
        ['-totalupdatedfiles', '-fixed_errors', '-fixed_warnings',
         '-fixed_tests', 'failed_errors', 'failed_warnings', 'failed_tests'];
      $scope.cdash.users = $filter('orderBy')($scope.cdash.users, $scope.orderByFields);
      $.cookie('cdash_user_stats_sort', null);
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $scope.cdash.users = $filter('orderBy')($scope.cdash.users, $scope.orderByFields);
      $.cookie('cdash_user_stats_sort', $scope.orderByFields);
    };
});
