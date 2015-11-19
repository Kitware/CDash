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
    $("#graphoptions").html("");
    return;
    }

  $("#graph").fadeIn('slow');
  $("#graph").html("fetching...<img src=images/loading.gif></img>");
  $("#grapholder").attr("style","width:800px;height:400px;");
  $("#graphoptions").html("<a href=javascript:showgraph_click("+buildid+",true)>Zoom out</a>");

  $("#graph").load("ajax/showbuildtimegraph.php?buildid="+buildid,{},function(){$("#grapholder").fadeIn('slow');
$("#graphoptions").show();
});
}

/** Display the build build history */
function showbuildhistory_click(buildid)
{
  if($("#buildhistory").html() != "")
    {
    $("#buildhistory").hide();
    $("#buildhistory").html("");
    return;
    }

  $("#buildhistory").load("ajax/showbuildhistory.php?buildid="+buildid,{},
   function(){
     $("#buildhistory").show();
   });
}
