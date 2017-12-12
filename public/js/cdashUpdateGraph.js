function showbuildgraph_click(buildid,zoomout)
{
  if(zoomout)
    {
    $("#graph").load("ajax/showbuildupdategraph.php?buildid="+buildid);
    return;
    }

  if($("#graph").html() != "" && $("#grapholder").is(":visible"))
    {
    $("#grapholder").hide(); //fadeOut('medium');
    $("#graphoptions").html("");
    return;
    }

  $("#graph").fadeIn('slow');
  $("#graph").html("fetching...<img src=img/loading.gif></img>");
  $("#grapholder").attr("style","width:800px;height:400px;");
  $("#graphoptions").html("<a href=javascript:showbuildgraph_click("+buildid+",true)>Zoom out</a>");

  $("#graph").load("ajax/showbuildupdategraph.php?buildid="+buildid,{},function(){$("#grapholder").fadeIn('slow');
  $("#graphoptions").show();
});
}
