CDash.controller('ManageMeasurementsController',
  function ManageMeasurementsController($http, $scope, apiLoader, modalSvc) {
    apiLoader.loadPageData($scope, 'api/v1/manageMeasurements.php');

    $scope.finishSetup = function() {
      // Mark all measurements as clean (unmodified).
      for (var i = 0, len = $scope.cdash.measurements.length; i < len; i++) {
        $scope.cdash.measurements[i].dirty = false;
      }
      // Create a blank measurement for the user to fill out.
      $scope.newMeasurement();
    };

    $scope.newMeasurement = function() {
      $scope.cdash.newmeasurement = {
        id: -1,
        dirty: false,
        name: '',
        summarypage: 1,
        testpage: 1
      };
    };

    // Save measurements to database.
    $scope.save = function() {
      var measurements_to_save = [];
      // Gather up all the modified measurements.
      for (var i = 0, len = $scope.cdash.measurements.length; i < len; i++) {
        if ($scope.cdash.measurements[i].dirty) {
          measurements_to_save.push($scope.cdash.measurements[i]);
        }
      }

      // Also save the new measurement if the user filled it out.
      if ($scope.cdash.newmeasurement.name != '') {
        measurements_to_save.push($scope.cdash.newmeasurement);
      }

      // Submit the request.
      var parameters = {
        projectid: $scope.cdash.projectid,
        measurements: measurements_to_save
      };
      $http.post('api/v1/manageMeasurements.php', parameters)
      .then(function success(s) {
        $("#save_complete").show();
        $("#save_complete").delay(3000).fadeOut(400);
        if (s.data.id > 0) {
          // Assign an id to the "new" measurement and create a new blank one
          // for the user to fill out.
          $scope.cdash.newmeasurement.id = s.data.id;
          $scope.cdash.measurements.push($scope.cdash.newmeasurement);
          $scope.newMeasurement();
        }
      }, function error(e) {
        $scope.cdash.error = e.data.error;
      });
    };

    // Display confirmation dialog before deleting a measurement.
    $scope.confirmDelete = function(measurement) {
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
