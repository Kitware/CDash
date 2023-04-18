function makeBulletChart(chartName, elementName, min, avg, max, current,
  previous, chartHeight) {
  // note that chartHeight is just for the chart itself (not the labels)
  let chart;
  nv.addGraph(() => {
    chart = nv.models.bulletChart()
      .options({
        margin: {top: 33, right: 10, bottom: 5, left: 5},
        height: chartHeight + 33,
      });

    const chartData = {
      'ranges': [min, avg, max],
      'rangeLabels': ['Low', 'Medium', 'Satisfactory'],
      'measures': [current],
      'markers': [previous],
    };

    // This chart doesn't render without the marker, so instead of
    // leaving it off, we just relabel it to "Current" instead of
    // the default label of "Previous".
    // eslint-disable-next-line eqeqeq
    if (previous == current) {
      chartData['markerLabels'] = ['Current'];
    }

    d3.select(elementName)
      .datum(chartData)
      .call(chart);
    return chart;
  });
}
