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

function showprojectgraph_click(projectid,timestamp,zoomout)
{
  if(zoomout)
    {
    $("#graph").load("ajax/showprojectupdategraph.php?projectid="+projectid+"&timestamp="+timestamp);
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
  $("#graphoptions").html("<a href=javascript:showprojectgraph_click("+projectid+","+timestamp+",true)>Zoom out</a>");

  $("#graph").load("ajax/showprojectupdategraph.php?projectid="+projectid+"&timestamp="+timestamp,{},function(){$("#grapholder").fadeIn('slow');
  $("#graphoptions").show();
});
}
