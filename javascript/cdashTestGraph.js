function showtesttimegraph_click(buildid,testid)
{   
  if($("#timegraph").html() != "" && $("#timegrapholder").is(":visible"))
    {
				$("#timegrapholder").hide(); //fadeOut('medium');
    return;
    }
  
	
	$("#timegraph").fadeIn('slow');
  $("#timegraph").html("fetching...<img src=images/loading.gif></img>");
	$("#timegrapholder").attr("style","width:800px;height:400px;");
	$("#timegrapholder").show();
  $("#timegraph").load("ajax/showtesttimegraph.php?testid="+testid+"buildid="+buildid,{},function(){$("#timegrapholder").fadeIn('slow');});
}

function showtestpassinggraph_click(buildid,testid)
{   
  if($("#passinggraph").html() != "" && $("#passinggrapholder").is(":visible"))
    {
				$("#passinggrapholder").hide(); //fadeOut('medium');
    return;
    } 

	$("#passinggraph").fadeIn('slow');
  $("#passinggraph").html("fetching...<img src=images/loading.gif></img>");
  $("#passinggrapholder").attr("style","width:800px;height:400px;");
	$("#passinggrapholder").show();
  $("#passinggraph").load("ajax/showtestpassinggraph.php?testid="+testid+"buildid="+buildid,{},function(){$("#passinggrapholder").fadeIn('slow');});
}
