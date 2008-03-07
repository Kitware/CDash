$(document).ready(function() {
     $('#calendar').datepicker({onSelect: calendarSelected}); 
    
  // Display the date range from a multi-month inline date picker 
  function calendarSelected(dateStr) {
   var project = document.getElementById("projectname");
    window.location = "index.php?project="+project.value+"&date="+dateStr.substr(6,4)+dateStr.substr(0,2)+dateStr.substr(3,2);
   }
  
   $('#calendar').hide();
   
   // Quick links
   $('.quicklink').hide();
   
   $('.table-heading1').mouseover(function(){
      $('.quicklink',this).show();                  
      }).mouseout(function(){
        $('.quicklink',this).hide();        
        });
   $('.table-heading2').mouseover(function(){
      $('.quicklink',this).show();                  
      }).mouseout(function(){
        $('.quicklink',this).hide();        
        });

   $('.table-heading3').mouseover(function(){
      $('.quicklink',this).show();                  
      }).mouseout(function(){
        $('.quicklink',this).hide();        
        });
   
    });   
