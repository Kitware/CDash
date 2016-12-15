CDash.controller('ManageOverviewController', function ManageOverviewController($scope, $rootScope, $http, renderTimer) {
  $scope.loading = true;
  $http({
    url: 'api/v1/manageOverview.php',
    method: 'GET',
    params: $rootScope.queryString
  }).then(function success(s) {
    var cdash = s.data;
    renderTimer.initialRender($scope, cdash);
    // Setup sortable elements.
    $scope.buildSortable = {
      stop: function(e, ui) {
        for (var index in $scope.cdash.buildcolumns) {
          $scope.cdash.buildcolumns[index].position = index;
        }
      }
    };
    $scope.staticSortable = {
      stop: function(e, ui) {
        for (var index in $scope.cdash.staticrows) {
          $scope.cdash.staticrows[index].position = index;
        }
      }
    };
  }).finally(function() {
    $scope.loading = false;
  });

  $scope.addBuildColumn = function(column) {
    var index = $scope.cdash.availablegroups.indexOf(column);
    $scope.cdash.availablegroups.splice(index, 1);
    $scope.cdash.buildcolumns.push(column);
  };

  $scope.removeBuildColumn = function(column) {
    var index = $scope.cdash.buildcolumns.indexOf(column);
    $scope.cdash.buildcolumns.splice(index, 1);
    $scope.cdash.availablegroups.push(column);
  };

  $scope.addStaticRow = function(row) {
    var index = $scope.cdash.availablegroups.indexOf(row);
    $scope.cdash.availablegroups.splice(index, 1);
    $scope.cdash.staticrows.push(row);
  };

  $scope.removeStaticRow = function(row) {
    var index = $scope.cdash.staticrows.indexOf(row);
    $scope.cdash.staticrows.splice(index, 1);
    $scope.cdash.availablegroups.push(row);
  };

  $scope.saveLayout = function() {
    // Mark all build and static components as such.
    var buildElements = getSortedElements('#buildSortable');
    for (i = 0; i < buildElements.length; ++i) {
      buildElements[i]['type'] = 'build';
    }
    var staticElements = getSortedElements('#staticSortable');
    for (i = 0; i < staticElements.length; ++i) {
      staticElements[i]['type'] = 'static';
    }

    // Concatenate them together and format as JSON.
    var newLayout = JSON.stringify(buildElements.concat(staticElements));

    $("#loading").attr("src", "img/loading.gif");

    var parameters = {
      projectid: $scope.cdash.projectid,
      saveLayout: newLayout
    };
    $http.post('api/v1/manageOverview.php', parameters)
    .then(function success() {
      $("#loading").attr("src", "img/check.gif");
    });
  };
});
