
$(document).ready(function() {
 $("#newuser").hide();
 $("#search").keyup(function()
   {
   var search;
   search = $("#search").val();

   var projectid = document.getElementById("projectid").value;

   if (search.length > 0)
       {
       // Trigger AJAX request
       $('#newuser').load("ajax/finduserproject.php?projectid="+projectid+"&search="+search,{},function(){$("#newuser").fadeIn('fast');});
       }
    else
       {
       $('#newuser').fadeOut('medium');
       }
   });
 });
