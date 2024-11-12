$(document).ready(function() {

  // Initialize the table
  $tabs = $("#maintainerTable");
  $tabs.each(function(index) {
     $(this).tablesorter({
            headers: {
                0: { sorter:'text'},
                1: { sorter:'text'},
                2: { sorter:'text'},
                3: { sorter:'text'},
            },
          debug: false,
          widgets: ['zebra']
        });
      });
});
