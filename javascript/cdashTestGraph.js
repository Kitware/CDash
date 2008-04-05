function showtesttimegraph_click(buildid,testid,zoomout)
{   
  if(zoomout)
    {
    $("#timegraph").load("ajax/showtesttimegraph.php?testid="+testid+"&buildid="+buildid+"&zoomout=1");
    return;
    }
  else if($("#timegraph").html() != "" && $("#timegrapholder").is(":visible"))
    {
    $("#timegrapholder").hide(); //fadeOut('medium');
    $("#timegraphoptions").html("");
    return;
    }
 
 
  $("#timegraph").fadeIn('slow');
  $("#timegraph").html("fetching...<img src=images/loading.gif></img>");
  $("#timegrapholder").attr("style","width:800px;height:400px;");

  $("#timegraphoptions").html("<a href=javascript:showtesttimegraph_click("+buildid+","+testid+",true)>[Zoom out]</a>");
  $("#timegraph").load("ajax/showtesttimegraph.php?testid="+testid+"&buildid="+buildid,{},function(){
  $("#timegrapholder").fadeIn('slow');
  $("#timegraphoptions").show();

});
}

function showtestpassinggraph_click(buildid,testid,zoomout)
{  
  if(zoomout)
    {
    $("#passinggraph").load("ajax/showtestpassinggraph.php?testid="+testid+"&buildid="+buildid+"&zoomout=1");
    return;
    }
  else if($("#passinggraph").html() != "" && $("#passinggrapholder").is(":visible"))
    {
    $("#passinggrapholder").hide(); //fadeOut('medium');
    $("#passinggraphoptions").html("");
    return;
    } 

  $("#passinggraph").fadeIn('slow');
  $("#passinggraph").html("fetching...<img src=images/loading.gif></img>");
  $("#passinggrapholder").attr("style","width:800px;height:400px;");
  
  $("#passinggraphoptions").html("<a href=javascript:showtestpassinggraph_click("+buildid+","+testid+",true)>[Zoom out]</a>");
  $("#passinggraph").load("ajax/showtestpassinggraph.php?testid="+testid+"&buildid="+buildid,{},function(){
$("#passinggrapholder").fadeIn('slow');
$("#passinggraphoptions").show();
});
}
