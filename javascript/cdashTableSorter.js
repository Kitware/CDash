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
            
            // We don't have a <a href
            if(i==-1)
              {
              return s;
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
  
  /** percent for coverage */
  $.tablesorter.addParser({ 
      // set a unique id 
      id: 'percentage', 
      is: function(s) { 
            // return false so this parser is not auto detected 
            return false; 
        }, 
        format: function(s) {
            // format your data for normalization
            var i = s.indexOf("<b");
            var j = s.indexOf(">",i);
            var k = s.indexOf("</b>",j);
            var t = s.substr(j+1,k-j-2);
            return t.toLowerCase(); 
        }, 
        // set type, either numeric or text 
        type: 'numeric' 
    }); 
  
  /** numeric for dynalanisys */
  $.tablesorter.addParser({ 
      // set a unique id 
      id: 'dynanalysismetric', 
      is: function(s) { 
            // return false so this parser is not auto detected 
            return false; 
        }, 
        format: function(s) {
            // format your data for normalization
            var i = s.indexOf("<b");
            var j = s.indexOf(">",i);
            var k = s.indexOf("</b>",j);
            var t = s.substr(j+1,k-j-1);
            return t.toLowerCase(); 
        }, 
        // set type, either numeric or text 
        type: 'numeric' 
    }); 

  // Initialize the viewTest tables
  $tabs = $("#viewTestTable");
  var nrows = 0;
  if(document.getElementById('viewTestTable'))
    {
    var nrows = document.getElementById('viewTestTable').getElementsByTagName('thead')[0].getElementsByTagName('th').length; 
    }
 
 if(nrows==3)
    {
    $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'buildname'},
                1: { sorter:'buildname'},
                2: { sorter:'digit'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    } 
    
  if(nrows==4 && document.getElementById('showtesttimediv'))
    {
    $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'buildname'},
                1: { sorter:'buildname'},
                2: { sorter:'buildname'},
                3: { sorter:'digit'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    } 
  else if(nrows==4)
    {
    $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'buildname'},
                1: { sorter:'buildname'},
                2: { sorter:'digit'},
                3: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    }
  
  if(nrows==5)
    {
    $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'buildname'},
                1: { sorter:'buildname'},
                2: { sorter:'buildname'},
                3: { sorter:'digit'},
                4: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    }
 
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
                4: { sorter:'digit'},
                5: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    }
    
  // Initialize the userStatistics table
  if($tabs.length==0)
    {
    $tabs = $("#userStatistics");
    $tabs.each(function(index) {          
     $(this).tablesorter({
            headers: { 
                0: { sorter:'text'},
                1: { sorter:'digit'},
                2: { sorter:'digit'},
                3: { sorter:'digit'},
                4: { sorter:'digit'},
                5: { sorter:'digit'},
                6: { sorter:'digit'},
                7: { sorter:'digit'},
                8: { sorter:'digit'}
            },
          debug: false,
          widgets: ['zebra'] 
        });  
      });
    }
    
  // Initialize the subproject table
  if($tabs.length==0)
    {
    $tabs = $("#subproject");
    $tabs.each(function(index) {
     $(this).tablesorter({
            headers: { 
                0: { sorter:'text'},
                1: { sorter:'digit'},
                2: { sorter:'digit'},
                3: { sorter:'digit'},
                4: { sorter:'digit'},
                5: { sorter:'digit'},
                6: { sorter:'digit'},
                7: { sorter:'digit'},
                8: { sorter:'digit'},
                9: { sorter:'digit'},
                10: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra']
        });  
      });
    }
  


  // If all the above are not working then it should be the index table
  if($tabs.length==0)
    {
    // Initialize the Index tables
    $tabs = $(".tabb",this);
    $tabs.each( 
     function(index) {
      var tableid = this.id;
      if(tableid == "coveragetable" || tableid == "dynamicanalysistable")
        {
        return;
        }
       $(this).tablesorter({
              headers: { 
                  0: { sorter:'buildname'},
                  1: { sorter:'buildname'},
                  2: { sorter:'numericvalue'},
                  3: { sorter:'digit'},
                  4: { sorter:'numericvalue'},
                  5: { sorter:'numericvalue'},
                  6: { sorter:'digit'},
                  7: { sorter:'numericvalue'},
                  8: { sorter:'numericvalue'},
                  9: { sorter:'digit'},
                  10: { sorter:'numericvalue'},
                  11: { sorter:'numericvalue'},
                  12: { sorter:'numericvalue'},
                  13: { sorter:'digit'},
                  14: { sorter:'text'},
                  15: { sorter:'text'}
              },
            debug: false,
            widgets: ['zebra'] 
          });  
       
      // Get the cookie
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
    
    
    // Initialize the coverage table 
    $tabs = $("#coveragetable");
    $tabs.each(function(index) {
     $(this).tablesorter({
            headers: { 
                0: { sorter:'text'},
                1: { sorter:'text'},
                2: { sorter:'percentage'},
                3: { sorter:'dynanalysismetric'},
                4: { sorter:'dynanalysismetric'},
                5: { sorter:'text'},
                6: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra']
        });  
      });
    
    // Initialize the dynamic analysis table 
    $tabs = $("#dynamicanalysistable");
    $tabs.each(function(index) {
     $(this).tablesorter({
            headers: { 
                0: { sorter:'text'},
                1: { sorter:'text'},
                2: { sorter:'text'},
                3: { sorter:'dynanalysismetric'}, // not percent but same format
                4: { sorter:'text'},
                5: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra']
        });  
      });
    } // end indextable

});   
