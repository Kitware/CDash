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
