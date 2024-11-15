$(document).ready(function() {

  /** Filename with checkbox */
  $.tablesorter.addParser({
    id: 'coveragefilename',
    is: function(s) {
       // return false so this parser is not auto detected
       return false;
       },
    format: function(s) {
       // format your data for normalization
       var t = s;
       var i = s.indexOf(">");
       if(i>0)
         {
         t = s.substr(i+1);
         }
        return t.toLowerCase();
        },
      // set type, either numeric or text
      type: 'text'
      });


  /** Priority  */
  $.tablesorter.addParser({
    id: 'coveragepriority',
    is: function(s) {
       // return false so this parser is not auto detected
       return false;
       },
    format: function(s) {
       // format your data for normalization
       var t = s;
       var i = s.indexOf("selected");
       if(i>0)
         {
         var beg = s.indexOf('"',i-5);
         return s.substr(beg+1,1);
         }
       return 0;
       },
      // set type, either numeric or text
      type: 'numeric'
      });


  // Initialize the table
  $tabs = $("#manageCoverageTable");
  $tabs.each(function(index) {
     $(this).tablesorter({
            headers: {
                0: { sorter:'coveragefilename'},
                1: { sorter:'coveragepriority'},
                2: { sorter:'text'},
            },
          debug: false,
          widgets: ['zebra']
        });
      });
});
