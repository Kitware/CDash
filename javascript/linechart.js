function makeLineChart(chartName, elementName, inputData, date) {
  jQuery(function(){
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
  });
}
