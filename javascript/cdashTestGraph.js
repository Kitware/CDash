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

function showcommandline_click()
{
  if($('#commandline').is(":visible"))
    {
    $('#commandlinelink').html('Show Command Line');
    }
  else
    {
    $('#commandlinelink').html('Hide Command Line');
    }
  $('#commandline').toggle();
}

function displaygraph_selected(buildid,testid,zoomout)
{
  var measurementname = $('#GraphSelection').val();

  if(measurementname == 0)
    {
    $("#graph_holder").hide();
    $("#graph_options").html("");
    return;
    }
  else if(measurementname == 'TestTimeGraph')
    {
    $("#graph_holder").attr("style","width:800px;height:400px;");

    if(zoomout)
      {
      $("#graph").load("ajax/showtesttimegraph.php?testid="+testid+"&buildid="+buildid+"&zoomout=1");
      return;
      }

    $("#graph").fadeIn('slow');
    $("#graph").html("fetching...<img src=images/loading.gif></img>");

    $("#graph_options").html("<a href=javascript:displaygraph_selected("+buildid+","+testid+",true)>Zoom out</a>");
    $("#graph").load("ajax/showtesttimegraph.php?testid="+testid+"&buildid="+buildid,{},function(){
      $("#graph_holder").fadeIn('slow');
      $("#graph_options").show();
      });
    }
  else if(measurementname == 'TestPassingGraph')
    {
    $("#graph_holder").attr("style","width:800px;height:400px;");

    if(zoomout)
      {
      $("#graph").load("ajax/showtestpassinggraph.php?testid="+testid+"&buildid="+buildid+"&zoomout=1");
      return;
      }

    $("#graph").fadeIn('slow');
    $("#graph").html("fetching...<img src=images/loading.gif></img>");

    $("#graph_options").html("<a href=javascript:displaygraph_selected("+buildid+","+testid+",true)>Zoom out</a>");
    $("#graph").load("ajax/showtestpassinggraph.php?testid="+testid+"&buildid="+buildid,{},function(){
      $("#graph_holder").fadeIn('slow');
      $("#graph_options").show();
      });
    }
  else
    {
    $("#graph_holder").attr("style","width:800px;height:400px;");
    if(zoomout)
      {
      $("#graph").load("ajax/showtestmeasurementdatagraph.php?testid="+testid+"&buildid="+buildid+"&measurement="+measurementname+"&zoomout=1");
      return;
      }
    $("#graph").fadeIn('slow');
    $("#graph").html("fetching...<img src=images/loading.gif></img>");
    $("#graph_options").html("<a href=javascript:displaygraph_selected('"+buildid+"','"+testid+"','"+measurementname+"',true)>Zoom out</a> \n\
                              <br/> <a href='ajax/showtestmeasurementdatagraph.php?testid="+testid+"&buildid="+buildid+"&measurement="+measurementname+"&export=csv'>Export as CSV File</a>");
    $("#graph").load("ajax/showtestmeasurementdatagraph.php?testid="+testid+"&buildid="+buildid+"&measurement="+measurementname,{},function(){
      $("#graph_holder").fadeIn('slow');
      $("#graph_options").show();
      });
    }
}

