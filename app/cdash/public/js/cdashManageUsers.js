
$(document).ready(function() {
 $("#newuser").hide();
 $("#search").keyup(function()
   {
   var search;
   search = $("#search").val();

   if (search.length > 0)
       {
       // Trigger AJAX request
       $('#newuser').load("ajax/findusers.php?search="+search,{},function(){$("#newuser").fadeIn('fast');});
       }
    else
       {
       $('#newuser').fadeOut('medium');
       }
   });
 });
