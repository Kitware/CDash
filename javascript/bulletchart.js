function makeBulletChart(chartName, elementName, min, avg, max, current,
                         previous, chartHeight) {
  // note that chartHeight is just for the chart itself (not the labels)
  var chart;
  nv.addGraph(function() {
    chart = nv.models.bulletChart()
    .options({
      margin: {top: 0, right: 10, bottom: 5, left: 5},
      height: chartHeight
    });
    d3.select(elementName)
      .datum({
        "ranges": [min, avg, max],
        "rangeLabels": ["Satisfactory", "Medium", "Low"],
        "measures": [current],
        "markers": [previous]
        })
      .call(chart);
    return chart;
  });
}
