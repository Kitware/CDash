// Time how long the initial render takes and add this to the value
// shown at the bottom of the page.
CDash.factory('renderTimer', ($timeout) => {
  const initialRender = function(controllerScope, cdash) {
    // Redirect if the API told us to.
    if ('redirect' in cdash) {
      window.location = cdash.redirect;
      return;
    }

    if (!'generationtime' in cdash) {
      return;
    }
    const start = new Date();

    // This is when the initial page render happens.
    controllerScope.cdash = cdash;

    $timeout(() => {
      const renderTime = +((new Date() - start) / 1000);
      let generationTimeStr = (renderTime + cdash.generationtime).toFixed(2);
      generationTimeStr += `s (${cdash.generationtime}s)`;
      controllerScope.cdash.generationtime = generationTimeStr;
    }, 0, true, controllerScope, cdash);
  };
  return {
    initialRender: initialRender,
  };
});
