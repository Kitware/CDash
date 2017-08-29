CDash.controller('ManageMeasurementsController',
  function ManageMeasurementsController($scope, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/manageMeasurements.php');
});
