function showgraph_click(buildid,zoomout)
{  
  if(zoomout)
    {
    $("#graph").load("ajax/showbuildtimegraph.php?buildid="+buildid);
    return;
    }
 
  if($("#graph").html() != "" && $("#grapholder").is(":visible"))
    {
    $("#grapholder").hide(); //fadeOut('medium');
    return;
    }
  
  $("#graph").fadeIn('slow');
  $("#graph").html("fetching...<img src=images/loading.gif></img>");
  $("#grapholder").attr("style","width:800px;height:400px;");
  $("#graphoptions").html("<a href=javascript:showgraph_click("+buildid+",true)>[Zoom out]</a>");

  $("#graph").load("ajax/showbuildtimegraph.php?buildid="+buildid,{},function(){$("#grapholder").fadeIn('slow');
$("#graphoptions").show();
});
}
