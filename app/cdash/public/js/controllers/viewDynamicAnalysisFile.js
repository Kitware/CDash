CDash.controller('ViewDynamicAnalysisFileController',
  ($scope, apiLoader) => {
    apiLoader.loadPageData($scope, 'api/v1/viewDynamicAnalysisFile.php');
  });
