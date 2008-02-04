function showgraph_click(buildid)
{   
  if($("#graph").html() != "" && $("#grapholder").is(":visible"))
    {
				$("#grapholder").hide(); //fadeOut('medium');
    return;
    }
  
	$("#graph").fadeIn('slow');
  $("#graph").html("fetching...<img src=images/loading.gif></img>");
	$("#grapholder").attr("style","width:800px;height:400px;");
	$("#grapholder").show();
  $("#graph").load("ajax/showbuildtimegraph.php?buildid="+buildid,{},function(){$("#grapholder").fadeIn('slow');});
}
