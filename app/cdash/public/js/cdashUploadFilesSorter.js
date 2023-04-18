$(document).ready(() => {
  // Initialize the table
  $tabs = $('#filesTable');
  $tabs.each(function(index) {
    $(this).tablesorter({
      headers: {
        0: { sorter:'text'},
        1: { sorter:'numeric'},
        2: { sorter:'text'},
      },
      debug: false,
      widgets: ['zebra'],
    });
  });
});
