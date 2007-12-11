function showgraph_click(buildid)
{   
  if($("#graph").html() != "" && $("#grapholder").is(":visible"))
    {
				$("#grapholder").hide(); //fadeOut('medium');
    return;
    }
  
  $("#graph").show();
		$("#grapholder").attr("style","width:600px;height:400px;");
		$("#grapholder").show();
  $("#graph").load("ajax/showbuildtimegraph.php?buildid="+buildid,{},function(){$("#grapholder").fadeIn('slow');});
}
