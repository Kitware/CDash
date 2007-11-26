$(document).ready(function() {
     $('#calendar').datepicker({onSelect: calendarSelected}); 
     
  // Display the date range from a multi-month inline date picker 
  function calendarSelected(dateStr) {
    window.location = "index.php?project=Insight&date="+dateStr.substr(6,4)+dateStr.substr(0,2)+dateStr.substr(3,2);
   }
  
   $('#calendar').hide();
   
    });   
