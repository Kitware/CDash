CDash.controller('BuildErrorController',
  function BuildErrorController($scope, $rootScope, $http, $sce, renderTimer) {
    $scope.loading = true;
    $scope.pagination = [];
    $scope.pagination.buildErrors = [];
    $scope.pagination.currentPage = 1;
    $scope.pagination.numPerPage = 25;
    $scope.pagination.maxSize = 5;

    $http({
      url: 'api/v1/viewBuildError.php',
      method: 'GET',
      params: $rootScope.queryString
    }).then(function success(s) {
      var cdash = s.data;
      // Handle the fact that we add HTML links to compiler output.
      var trustErrorHtml = function (error) {
          error.precontext = $sce.trustAsHtml(error.precontext);
          error.postcontext = $sce.trustAsHtml(error.postcontext);
          error.text = $sce.trustAsHtml(error.text);
          return error;
      };

      // Errors are either cdash.errors, or all values in cdash.errors.*
      if (Array.isArray(cdash.errors)) {
          for (var i in cdash.errors) {
              cdash.errors[i] = trustErrorHtml(cdash.errors[i]);
          }
      } else {
          for (var subproject in cdash.errors) {
              for (var error in cdash.errors[subproject]) {
                  cdash.errors[subproject][error] = trustErrorHtml(cdash.errors[subproject][error]);
              }
          }
      }
      renderTimer.initialRender($scope, cdash);

      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
      $scope.setPage(1);
    });

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
  }).directive('buildError', function () {
      return {
          templateUrl: 'views/partials/buildError.html'
      };
  });
