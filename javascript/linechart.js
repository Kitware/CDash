function makeLineChart(chartName, elementName, inputData) {
  var chart;

  nv.addGraph(function() {
    chart = nv.models.lineChart()
    .options({
      showLegend: false,
      showXAxis: false,
      showYAxis: false,
      margin: {top: 2, right: 2, bottom: 2, left: 2},
    });

    var chart_data = [{
      values: inputData,
      key: chartName,
      color: "#ff7f0e",
      area: false
    }];
    d3.select(elementName)
      .datum(chart_data)
      .call(chart);

    nv.utils.windowResize(chart.update);
    return chart;
  });
}
