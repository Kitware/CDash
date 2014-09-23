function makeLineChart(chartName, elementName, inputData, date) {
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

    // Change X axis to tightly fit the data.
    var highest_value = chart.axes.xaxis._dataBounds.max;
    chart.axes.xaxis.max = highest_value;

    var lowest_value = chart.axes.xaxis._dataBounds.min;
    chart.axes.xaxis.min = lowest_value;

    chart.replot();
  });
}
