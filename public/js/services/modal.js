CDash.factory('modalSvc', function modalSvc ($uibModal) {
  const showModal = function(modelId, okFn, template, success, error) {
    $modal = $uibModal.open({
      animation: true,
      size: 'sm',
      backdrop: true,
      templateUrl: template,
      controller: function () {
        var $ctrl = this;
        $ctrl.ok = function() {
          okFn(modelId);
          $modal.close();
        };
        $ctrl.cancel = function () {
          $modal.close();
        }
      },
      controllerAs: '$ctrl',
    });

    // some clarification...
    // success is the result of a successful button click (e.g. $ctrl.ok,
    // $ctrl.cancel)
    success = angular.isFunction(success)? success : function () {};

    // error is triggered as the result of a backdrop click thus it is
    // not really an error, but for convention's sake sticking with error
    error = angular.isFunction(error) ? error : function () {};

    // prevent console from complaining about unhandled backdrop click
    $modal.result.then(success, error);

    return $modal;
  };

  return {
    showModal: showModal,
  };
});
