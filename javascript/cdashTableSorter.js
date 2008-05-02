$(document).ready(function() {
 
  /** Load the help page */
  $('#key').jqm({ajax: 'key.html', trigger: 'a.keytrigger'});
  if(this.getElementById('projectname'))
    {
    var projectname = this.getElementById('projectname').value;
    $('#groupsdescription').jqm({ajax: 'groupsDescription.php?project='+projectname, trigger: 'a.grouptrigger'});
    }
    
  /** Build name */ 
  $.tablesorter.addParser({ 
      // set a unique id 
      id: 'buildname', 
      is: function(s) { 
            // return false so this parser is not auto detected 
            return false; 
        }, 
        format: function(s) { 
            // format your data for normalization
            var t = s;
            var i = s.indexOf("<a href");
            if(i>0)
              {
              var j = s.indexOf(">",i);
              var k = s.indexOf("</a>",j);
              t = s.substr(j+1,k-j-1);
              }
            return t.toLowerCase(); 
        }, 
        // set type, either numeric or text 
        type: 'text' 
    }); 
  
  /** Update */
  $.tablesorter.addParser({ 
      // set a unique id 
      id: 'numericvalue', 
      is: function(s) { 
            // return false so this parser is not auto detected 
            return false; 
        }, 
        format: function(s) { 
            // format your data for normalization
            var i = s.indexOf("<a href");
            if(i==-1) // IE
              {
              i = s.indexOf("<A href");
              }
            var j = s.indexOf(">",i);
            var k = s.indexOf("</a>",j);
            if(k==-1) // IE
              {
              k = s.indexOf("</A>");
              }
            var t = s.substr(j+1,k-j-1);
            return t.toLowerCase(); 
        }, 
        // set type, either numeric or text 
        type: 'numeric' 
    }); 


  // Initialize the viewTest tables
  $tabs = $("#viewTestTable");
  $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'buildname'},
                1: { sorter:'buildname'},
                2: { sorter:'buildname'},
                3: { sorter:'numeric'},
                4: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
        
    });
  
  // Initialize the testSummary tables
  if($tabs.length==0)
    {
    $tabs = $("#testSummaryTable");
    $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'text'},
                1: { sorter:'buildname'},
                2: { sorter:'text'},
                3: { sorter:'buildname'},
                4: { sorter:'numeric'},
                5: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    }

  // If all the above are not working
  if($tabs.length==0)
    {
    // Initialize the Index tables
    $tabs = $(".tabb",this);
    $tabs.each(function(index) {          
       $(this).tablesorter({
              headers: { 
                  0: { sorter:'buildname'},
                  1: { sorter:'buildname'},
                  2: { sorter:'numericvalue'},
                  3: { sorter:'numericvalue'},
                  4: { sorter:'numericvalue'},
                  5: { sorter:'numericvalue'},
                  7: { sorter:'numericvalue'},
                  8: { sorter:'numericvalue'},
                  9: { sorter:'numericvalue'},
                  11: { sorter:'text'}
              },
            debug: false,
            widgets: ['zebra'] 
          });  
       
      // Get the cookie
      var tableid = this.id;
      var cookiename = "cdash_table_sort_"+tableid;
      var cook = $.cookie(cookiename); // get cookie
      if(cook)
        {
        var cookArray = cook.split(',');
        var sortArray = new Array();
        var j=0;
        for(var i=0; i < cookArray.length; i+=2) 
          {
          sortArray[j] = [cookArray[i],cookArray[i+1]];
          j++;
          }
        $(this).trigger("sorton",[sortArray]);    
        }              
      });
    }
  
  
  
});   
