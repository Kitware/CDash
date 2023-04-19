// Encapsulate common code involved in loading our page data from the API.
CDash.factory('apiLoader', ($http, $rootScope, $window, renderTimer) => {
  const loadPageData = function(controllerScope, endpoint) {
    controllerScope.loading = true;

    $http({
      url: endpoint,
      method: 'GET',
      params: $rootScope.queryString,
    }).then((s) => {
      const cdash = s.data;

      // Check if we should display filters.
      // eslint-disable-next-line eqeqeq
      if (cdash.filterdata && cdash.filterdata.showfilters == 1) {
        controllerScope.showfilters = true;
      }

      // Time how long it takes to render the page.
      renderTimer.initialRender(controllerScope, cdash);

      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;

      // Save a copy of where we loaded this data from.
      // This is used to link the user to a copy of the data in JSON format.
      controllerScope.cdash.endpoint = endpoint + $window.location.search;

      // Do any subsequent setup required for this particular controller.
      if (typeof controllerScope.finishSetup === 'function') {
        controllerScope.finishSetup();
      }
    }, (e) => {
      controllerScope.cdash = e.data;
    }).finally(() => {
      controllerScope.loading = false;
    });
  };
  return {
    loadPageData: loadPageData,
  };
});
