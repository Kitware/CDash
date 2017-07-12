CDash.controller('ViewDynamicAnalysisFileController',
  function ViewDynamicAnalysisFileController($scope, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/viewDynamicAnalysisFile.php');
});
