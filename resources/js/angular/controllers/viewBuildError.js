export const buildError = ["VERSION", function (VERSION) {
      return {
          templateUrl: 'assets/js/angular/views/partials/buildError.html?id=' + VERSION,
      };
  }];

export function BuildErrorController($scope, $sce, apiLoader) {
    $scope.loading = true;
    $scope.pagination = [];
    $scope.pagination.buildErrors = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.numPerPage = 25;
    $scope.pagination.maxSize = 5;

    apiLoader.loadPageData($scope, 'api/v1/viewBuildError.php');
    $scope.finishSetup = function() {
      $scope.setPage(1);
    };

    $scope.setPage = function (pageNo) {
      var begin = ((pageNo - 1) * $scope.pagination.numPerPage),
          end = begin + $scope.pagination.numPerPage;

        if (end > 0) {
            $scope.pagination.buildErrors = $scope.cdash.errors.slice(begin, end);
        } else {
            $scope.pagination.buildErrors = $scope.cdash.errors;
        }
    };

    $scope.pageChanged = function() {
      $scope.setPage($scope.pagination.currentPage);
    };
}
