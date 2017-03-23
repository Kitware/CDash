CDash.controller('BuildErrorController',
  function BuildErrorController($scope, $sce, apiLoader) {
    $scope.loading = true;
    $scope.pagination = [];
    $scope.pagination.buildErrors = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.numPerPage = 25;
    $scope.pagination.maxSize = 5;

    apiLoader.loadPageData($scope, 'api/v1/viewBuildError.php');
    $scope.finishSetup = function() {
      // Handle the fact that we add HTML links to compiler output.
      // TODO: this might have to become a pre-render hook?
      // TODO: TEST IF THIS BROKE LINKIFIED OUTPUT!!!!
      var trustErrorHtml = function (error) {
          error.precontext = $sce.trustAsHtml(error.precontext);
          error.postcontext = $sce.trustAsHtml(error.postcontext);
          error.text = $sce.trustAsHtml(error.text);
          return error;
      };

      // Errors are either $scope.cdash.errors, or all values in $scope.cdash.errors.*
      if (Array.isArray($scope.cdash.errors)) {
          for (var i in $scope.cdash.errors) {
              $scope.cdash.errors[i] = trustErrorHtml($scope.cdash.errors[i]);
          }
      } else {
          for (var subproject in $scope.cdash.errors) {
              for (var error in $scope.cdash.errors[subproject]) {
                  $scope.cdash.errors[subproject][error] = trustErrorHtml($scope.cdash.errors[subproject][error]);
              }
          }
      }
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
  }).directive('buildError', function (VERSION) {
      return {
          templateUrl: 'build/views/partials/buildError_' + VERSION + '.html'
      };
  });
