// Main function
function addnote_click(buildid,userid)
{
  if(userid=='')
    {
    alert("You must be logged in to add a note");
    return;
    }

  if($("#addnote").html() != "")
    {
    $("#addnote").hide();
    $("#addnote").html("");
    return;
    }

  $("#addnote").fadeIn('slow');
  $("#addnote").html("fetching...<img src=img/loading.gif></img>");
  $("#addnote").load("ajax/addnote.php?buildid="+buildid+"&userid="+userid,{},function(){$(this).fadeIn('slow');});
  return;
}
