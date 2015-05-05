var CDash = angular.module('CDash', ['ngAnimate']);

// Prevent angular from adding "/" after "#" in URLs
CDash.config(function($locationProvider) {
  $locationProvider.html5Mode({
    enabled: true,
    requireBase: false,
    rewriteLinks: false
  });
});

// ...but make it so links still work.
CDash.run(function($rootScope) {
  $('[ng-app]').on('click', 'a', function() {
      window.location.href = $(this).attr('href');
  });
});
