// Main function
function buildgroup_click(buildid)
{   
  var group = "#buildgroup_"+buildid;
  
  //if($(group).html() != "" && $(group).html() != "added to group!")
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }
  
  $(group).hide();
  $(group).load("ajax/addbuildgroup.php?buildid="+buildid,{},function(){$(this).fadeIn('slow');});
}

function buildinfo_click(buildid)
{   
  var group = "#buildgroup_"+buildid;
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }
  
  $(group).hide();
  $(group).load("ajax/buildinfogroup.php?buildid="+buildid,{},function(){$(this).fadeIn('slow');});
}

function expectedinfo_click(siteid,buildname,projectid,buildtype,currentime)
{   
  var group = "#infoexpected_"+siteid+"_"+buildname;
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }
  $(group).hide();
  $(group).load("ajax/expectedinfo.php?siteid="+siteid+"&buildname="+buildname+"&projectid="+projectid+"&buildtype="+buildtype+"&currenttime="+currentime,{},function(){$(this).fadeIn('slow');});
}