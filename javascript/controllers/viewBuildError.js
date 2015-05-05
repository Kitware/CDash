CDash.controller('BuildErrorController',
  function BuildErrorController($scope, $http, $sce) {
    $http({
      url: 'api/viewBuildError.php',
      method: 'GET',
      params: queryString
    }).success(function(cdash) {
      for (var i in cdash.errors) {
        // Handle the fact that we add HTML links to compiler output.
        cdash.errors[i].precontext = $sce.trustAsHtml(cdash.errors[i].precontext);
        cdash.errors[i].postcontext = $sce.trustAsHtml(cdash.errors[i].postcontext);
        cdash.errors[i].text = $sce.trustAsHtml(cdash.errors[i].text);
      }
      $scope.cdash = cdash;
    });
});
