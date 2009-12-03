function changeCompiler()
  {
  selected=$("#system_select").attr("value");
  $("#result_compiler1").html('<img src="images/loading.gif"/>');
  $.ajax(
    {
    type: "POST",
    url: "ajax/manageclient.php",
    dataType: 'html',
    timeout: 100000000000,
    data:'osid='+selected,
    success: function(html)
      {
      $("#result_compiler2").html(html);
      $("#result_compiler1").html('<b>Compiler:</b>');
      }
    });
  }
   
 function changeCMake()
   {
   selected=$("#system_select").attr("value");
   selected2=$("#select_compiler").attr("value");
   $("#result_cmake1").html('<img src="images/loading.gif"/>');
   $.ajax(
     {
     type: "POST",
     url: "ajax/manageclient.php",
     dataType: 'html',
     timeout: 100000000000,
     data:'osid='+selected+'&compiler='+selected2,
     success: function(html)
       {
       $("#result_cmake2").html(html);
       $("#result_cmake1").html('<b>CMake Version:</b>');
       }
      });
    }
  
function changeLibrary()
  {
  selected=$("#system_select").attr("value");
  selected2=$("#select_compiler").attr("value");
  selected3=$("#select_cmake").attr("value");
  $("#result_library1").html('<img src="images/loading.gif"/>');
  $.ajax(
    {
    type: "POST",
    url: "ajax/manageclient.php",
    dataType: 'html',
    timeout: 100000000000,
    data:'osid='+selected+'&compiler='+selected2+'&cmake='+selected3,
    success: function(html)
      {
      $("#result_library2").html(html);
      $("#result_library1").html('<b>Libraries:</b>');
      $("#result_library1").attr('valgin','top');
      }
    });
  $.ajax(
    {
    type: "POST",
    url: "ajax/manageclient.php",
    dataType: 'html',
    timeout: 100000000000,
    data:'gettoolkits=1&osid='+selected,
    success: function(html)
      {
      $("#result_toolkit2").html(html);
      $("#result_toolkit1").html('<b>Toolkits:</b>');
      $("#result_toolkit1").attr('valgin','top');
      }
    });  
  }
