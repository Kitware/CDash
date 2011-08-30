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

  $("#timegraphoptions").html("<a href=javascript:showtesttimegraph_click("+buildid+","+testid+",true)>Zoom out</a>");
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

  $("#passinggraphoptions").html("<a href=javascript:showtestpassinggraph_click("+buildid+","+testid+",true)>Zoom out</a>");
  $("#passinggraph").load("ajax/showtestpassinggraph.php?testid="+testid+"&buildid="+buildid,{},function(){
  $("#passinggrapholder").fadeIn('slow');
  $("#passinggraphoptions").show();
  });
}

function showtestfailuregraph_click(projectid,testname,starttime,zoomout)
{
   if(zoomout)
    {
    $("#testfailuregraph").load("ajax/showtestfailuregraph.php?testname="+testname+"&starttime="+starttime+"&projectid="+projectid+"&zoomout=1");
    return;
    }
  else if($("#testfailuregraph").html() != "" && $("#testfailuregrapholder").is(":visible"))
    {
    $("#testfailuregrapholder").hide(); //fadeOut('medium');
    $("#testfailuregraphoptions").html("");
    return;
    }

  $("#testfailuregraph").fadeIn('slow');
  $("#testfailuregraph").html("fetching...<img src=images/loading.gif></img>");
  $("#testfailuregrapholder").attr("style","width:800px;height:400px;");

  $("#testfailuregraphoptions").html("<a href=javascript:showtestfailuregraph_click('"+projectid+"','"+testname+"','"+starttime+"',true)>Zoom out</a>");
  $("#testfailuregraph").load("ajax/showtestfailuregraph.php?testname="+testname+"&projectid="+projectid+"&starttime="+starttime,{},function(){
  $("#testfailuregrapholder").fadeIn('slow');
  $("#testfailuregraphoptions").show();
  });
}

function shownamedmeasurementgraph_click(buildid,testid,measurement)
{
  var divname = "#"+measurement+"graph";
  if($(divname).html() != "" && $(divname+"older").is(":visible"))
    {
    $(divname+"older").hide(); //fadeOut('medium');
    $(divname+"options").html("");
    return;
    }

  $(divname).fadeIn('slow');
  $(divname).html("fetching...<img src=images/loading.gif></img>");
  $(divname+"older").attr("style","width:800px;height:400px;");

  $(divname).load("ajax/showtestmeasurementdatagraph.php?testid="+testid+"&buildid="+buildid+"&measurement="+measurement,{},function(){
  $(divname+"older").fadeIn('slow');
  $(divname+"options").show();
  });
}
