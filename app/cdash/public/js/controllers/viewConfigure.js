CDash.controller('ViewConfigureController',
  function ViewConfigureController($scope, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/viewConfigure.php');
});
