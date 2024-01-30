CDash.controller('UserStatisticsController',
  ["$scope", "$filter", "apiLoader", "multisort", function UserStatisticsController($scope, $filter, apiLoader, multisort) {
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

    apiLoader.loadPageData($scope, 'api/v1/userStatistics.php');
    $scope.finishSetup = function() {
      $scope.cdash.users = $filter('orderBy')($scope.cdash.users, $scope.orderByFields);
    };

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
}]);
