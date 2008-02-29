$(document).ready(function() {

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
            var i = s.indexOf("<a href");
            var j = s.indexOf(">",i);
            var k = s.indexOf("</a>",j);
            var t = s.substr(j+1,k-j-1);
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
            var j = s.indexOf(">",i);
            var k = s.indexOf("</a>",j);
            var t = s.substr(j+1,k-j-1);
            return t.toLowerCase(); 
        }, 
        // set type, either numeric or text 
        type: 'numeric' 
    }); 

  // Initialize the tables
  $tabs = $(".tabb",this);
  $tabs.each(function(index) {
      $(this).tablesorter({ 
            headers: { 
                1: { sorter:'buildname'},
                2: { sorter:'numericvalue'},
                3: { sorter:'numericvalue'},
                4: { sorter:'numericvalue'},
                5: { sorter:'numericvalue'},
                7: { sorter:'numericvalue'},
                8: { sorter:'numericvalue'},
                9: { sorter:'numericvalue'}                
            },
          debug: false,
          widgets: ['zebra'] 
        });             
                            
      });

});   
