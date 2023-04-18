CDash.controller('ManageOverviewController', ($scope, $http, apiLoader) => {
  apiLoader.loadPageData($scope, 'api/v1/manageOverview.php');
  $scope.finishSetup = function() {
    // Setup sortable elements.
    $scope.buildSortable = {
      stop: function(e, ui) {
        for (const index in $scope.cdash.buildcolumns) {
          $scope.cdash.buildcolumns[index].position = index;
        }
      },
    };
    $scope.staticSortable = {
      stop: function(e, ui) {
        for (const index in $scope.cdash.staticrows) {
          $scope.cdash.staticrows[index].position = index;
        }
      },
    };
  };

  $scope.addBuildColumn = function(column) {
    const index = $scope.cdash.availablegroups.indexOf(column);
    $scope.cdash.availablegroups.splice(index, 1);
    $scope.cdash.buildcolumns.push(column);
  };

  $scope.removeBuildColumn = function(column) {
    const index = $scope.cdash.buildcolumns.indexOf(column);
    $scope.cdash.buildcolumns.splice(index, 1);
    $scope.cdash.availablegroups.push(column);
  };

  $scope.addStaticRow = function(row) {
    const index = $scope.cdash.availablegroups.indexOf(row);
    $scope.cdash.availablegroups.splice(index, 1);
    $scope.cdash.staticrows.push(row);
  };

  $scope.removeStaticRow = function(row) {
    const index = $scope.cdash.staticrows.indexOf(row);
    $scope.cdash.staticrows.splice(index, 1);
    $scope.cdash.availablegroups.push(row);
  };

  $scope.saveLayout = function() {
    // Mark all build and static components as such.
    const buildElements = getSortedElements('#buildSortable');
    for (i = 0; i < buildElements.length; ++i) {
      buildElements[i]['type'] = 'build';
    }
    const staticElements = getSortedElements('#staticSortable');
    for (i = 0; i < staticElements.length; ++i) {
      staticElements[i]['type'] = 'static';
    }

    // Concatenate them together and format as JSON.
    const newLayout = JSON.stringify(buildElements.concat(staticElements));

    $('#loading').attr('src', 'img/loading.gif');

    const parameters = {
      projectid: $scope.cdash.projectid,
      saveLayout: newLayout,
    };
    $http.post('api/v1/manageOverview.php', parameters)
      .then(() => {
        $('#loading').attr('src', 'img/check.gif');
      });
  };
});
