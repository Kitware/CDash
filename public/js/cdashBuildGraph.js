function showbuildgraph_click(buildid,type,zoomout)
{
  if(zoomout)
    {
    $("#build"+type+"graph").load("ajax/showbuildgraph.php?buildid="+buildid+"&graphtype="+type);
    return;
    }

  if($("#build"+type+"graph").html() != "" && $("#build"+type+"grapholder").is(":visible"))
    {
    $("#build"+type+"grapholder").hide(); //fadeOut('medium');
    $("#build"+type+"graphoptions").html("");
    return;
    }

  $("#build"+type+"graph").fadeIn('slow');
  $("#build"+type+"graph").html("fetching...<img src=img/loading.gif></img>");
  $("#build"+type+"grapholder").attr("style","width:800px;height:400px;");
  $("#build"+type+"graphoptions").html("<a href=javascript:showbuildgraph_click("+buildid+",'"+type+"',true)>Zoom out</a>");

  $("#build"+type+"graph").load("ajax/showbuildgraph.php?buildid="+buildid+"&graphtype="+type,
                                {},function(){$("#build"+type+"grapholder").fadeIn('slow');
$("#build"+type+"graphoptions").show();
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
