CDash.controller('ManageMeasurementsController',
  function ManageMeasurementsController($http, $scope, apiLoader, modalSvc) {
    apiLoader.loadPageData($scope, 'api/v1/manageMeasurements.php');

    // Display confirmation dialog.
    $scope.confirmDelete = function (measurement) {
      modalSvc.showModal(measurement.id, $scope.removeMeasurement, 'modal-template');
    }

    // Remove measurement upon confirmation.
    $scope.removeMeasurement = function(id_to_remove) {
      var parameters = {
        projectid: $scope.cdash.projectid,
        id: id_to_remove
      };
      $http({
        url: 'api/v1/manageMeasurements.php',
        method: 'DELETE',
        params: parameters
      }).then(function success() {
        // Find the measurement to remove.
        for (var i = 0, len = $scope.cdash.measurements.length; i < len; i++) {
          if ($scope.cdash.measurements[i].id === id_to_remove) {
            // Remove it from our scope.
            $scope.cdash.measurements.splice(i, 1);
            break;
          }
        }
      });
    };
});
