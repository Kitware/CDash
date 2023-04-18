function makeLineChart(elementName, inputData, project, anchor, sort) {
  jQuery(() => {

    // setup the chart
    const chart = $.jqplot (elementName, [inputData], {
      axes:{
        xaxis:{
          renderer: $.jqplot.DateAxisRenderer,
          tickOptions: {formatString:'%b %#d'},
        },
      },
      highlighter: {
        show: true,
        sizeAdjust: 7.5,
      },
      cursor: {
        show: false,
      },
    });

    $(`#${elementName}`).bind('jqplotDataClick',
      (ev, seriesIndex, pointIndex, data) => {
        // Get the date for this data point in the format that CDash expects.
        const d = new Date(data[0]);
        const day = (`0${d.getDate()}`).slice(-2);
        const month = (`0${d.getMonth() + 1}`).slice(-2);
        const year = d.getFullYear();
        const date = `${year}-${month}-${day}`;

        // Redirect the user to this project's index page for the given date.
        let url = `index.php?project=${project}&date=${date}`;
        if (sort) {
          url += `&sort=${sort}`;
        }
        if (anchor) {
          url += `##${anchor}`;
        }
        window.open(url, '_blank');
      },
    );

    // Change X axis to tightly fit the data.
    const highest_value = chart.axes.xaxis._dataBounds.max;
    const lowest_value = chart.axes.xaxis._dataBounds.min;
    if (highest_value > lowest_value) {
      chart.axes.xaxis.max = highest_value;
      chart.axes.xaxis.min = lowest_value;
      chart.replot();
    }
  });
}
