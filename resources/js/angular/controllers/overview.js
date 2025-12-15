function makeLineChart(elementName, inputData, project, anchor, sort) {
  jQuery(function(){

    // setup the chart
    var chart = $.jqplot (elementName, [inputData], {
      axes:{
        xaxis:{
          renderer: $.jqplot.DateAxisRenderer,
          tickOptions: {formatString:'%b %#d'},
        }
      },
      highlighter: {
        show: true,
        sizeAdjust: 7.5
      },
      cursor: {
        show: false
      }
    });

    $("#" + elementName).bind('jqplotDataClick',
      function (ev, seriesIndex, pointIndex, data) {
        // Get the date for this data point in the format that CDash expects.
        var d = new Date(data[0]);
        var day = ("0" + d.getDate()).slice(-2);
        var month = ("0" + (d.getMonth() + 1)).slice(-2);
        var year = d.getFullYear();
        var date = year + "-" + month + "-" + day;

        // Redirect the user to this project's index page for the given date.
        var url = "index.php?project=" + project + "&date=" + date;
        if (sort) {
          url += "&sort=" + sort;
        }
        if (anchor) {
          url += "##" + anchor;
        }
        window.open(url, '_blank');
      }
    );

    // Change X axis to tightly fit the data.
    var highest_value = chart.axes.xaxis._dataBounds.max;
    var lowest_value = chart.axes.xaxis._dataBounds.min;
    if (highest_value > lowest_value) {
      chart.axes.xaxis.max = highest_value;
      chart.axes.xaxis.min = lowest_value;
      chart.replot();
    }
  });
}

function makeBulletChart(chartName, elementName, min, avg, max, current,
                         previous, chartHeight) {
  // note that chartHeight is just for the chart itself (not the labels)
  var chart;
  nv.addGraph(function() {
    chart = nv.models.bulletChart()
      .options({
        margin: {top: 33, right: 10, bottom: 5, left: 5},
        height: chartHeight + 33
      });

    var chartData = {
      "ranges": [min, avg, max],
      "rangeLabels": ["Low", "Medium", "Satisfactory"],
      "measures": [current],
      "markers": [previous],
    };

    // This chart doesn't render without the marker, so instead of
    // leaving it off, we just relabel it to "Current" instead of
    // the default label of "Previous".
    if (previous == current) {
      chartData["markerLabels"] = ["Current"];
    }

    d3.select(elementName)
      .datum(chartData)
      .call(chart);
    return chart;
  });
}

export function linechart() {
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
}

export function bulletchart() {
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
}

export function OverviewController($scope, $location, anchors, apiLoader) {
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
}
