function makeLineChart(chartName, elementName, inputData, date) {
  var chart;

  nv.addGraph(function() {
    chart = nv.models.lineChart()
    .options({
      showLegend: false,
      showXAxis: false,
      showYAxis: false,
      margin: {top: 2, right: 2, bottom: 2, left: 2},
    });

    if (date) {
      chart.xAxis.tickFormat(function(d) {
        return d3.time.format('%a, %d %b %Y')(new Date(d))
      });
      chart.xScale(d3.time.scale()); //fixes misalignment of timescale with line graph
    }

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
