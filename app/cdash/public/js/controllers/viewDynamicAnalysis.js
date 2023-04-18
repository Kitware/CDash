CDash.controller('ViewDynamicAnalysisController',
  ($scope, apiLoader) => {
    apiLoader.loadPageData($scope, 'api/v1/viewDynamicAnalysis.php');
  });
