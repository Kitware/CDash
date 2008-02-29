$(document).ready(function() {
  
$("#testtable").tablesorter(); 

});   


function site_sort_click(groupid)
{   
  var group = "#table_group_"+groupid;
  $(group).tablesorter(); 
  
  //alert(group);
  //$("#testtable").tablesorter(); 
   // set sorting column and direction, this will sort on the first and third column the column index starts at zero 
  //var sorting = [[0,1]]; 
  // sort on the first column 
  //$("#testtable").trigger("sorton",[sorting]);   
  //$(group).trigger("sorton",[sorting]);   
  //$(group).fadeIn('slow');
  return;
}
