CDash.controller('ViewDynamicAnalysisFileController',
  ["$scope", "apiLoader", function ViewDynamicAnalysisFileController($scope, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/viewDynamicAnalysisFile.php');
}]);
