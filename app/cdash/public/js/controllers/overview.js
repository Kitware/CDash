CDash.controller('OverviewController',
  function OverviewController($scope, $location, anchors, apiLoader) {
    apiLoader.loadPageData($scope, 'api/v1/overview.php');
    $scope.finishSetup = function() {
      // Expose the jumpToAnchor function to the scope.
      // This allows us to call it from the HTML template.
      $scope.jumpToAnchor = anchors.jumpToAnchor;

      // Honor any intra-page anchor specified in the URI.
      if ($location.hash() != '') {
        anchors.jumpToAnchor($location.hash());
      }
    };
});

CDash.directive('linechart', function() {
  return {
    restrict: 'E',
    replace: true,
    scope: {
      data: '=data',
      groupname: '=groupname',
      measurementname: '=measurementname',
      project: '=project',
      anchor: '=anchor',
      sort: '=sort'
    },
    template: '<div class="overview-line-chart"/>',
    link: function(scope, element, attrs) {
      if (scope.groupname) {
        var data = JSON.parse(scope.data);
        if (data.length > 0) {
          element[0].id = scope.groupname + "_" + scope.measurementname + "_chart";
          makeLineChart(element[0].id, data, scope.project, scope.anchor, scope.sort);
        }
      }
    }
  };
});

CDash.directive('bulletchart', function() {
  return {
    restrict: 'E',
    replace: true,
    scope: {
      data: '=data',
      categoryname: '=categoryname',
    },
    template: '<div class="overview-bullet-chart"><svg></svg></div>',
    link: function(scope, element, attrs) {
      if (scope.data) {
        element[0].id = scope.data.name_clean + "_" + scope.categoryname + "_bullet";
        var chart_data = JSON.parse(scope.data.chart),
            chart_name = scope.group_name + " " + scope.data.name,
            element_name = "#" + element[0].id + " svg";
        makeBulletChart(
          chart_name,
          element_name,
          scope.data.low,
          scope.data.medium,
          scope.data.satisfactory,
          scope.data.current,
          scope.data.previous,
          25);
      }
    }
  };
});
