CDash.directive('build', (VERSION) => {
  return {
    templateUrl: `build/views/partials/build_${VERSION}.html`,
  };
});
