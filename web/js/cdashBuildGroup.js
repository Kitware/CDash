// Main function
function URLencode(sStr)
{
    return escape(sStr)
       .replace(/\+/g, '%2B')
          .replace(/\"/g,'%22')
             .replace(/\'/g, '%27');
}

function buildgroup_click(buildid)
{
  var group = "#buildgroup_"+buildid;

  //if($(group).html() != "" && $(group).html() != "added to group!")
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }

  $(group).fadeIn('slow');
  $(group).html("fetching...<img src=images/loading.gif></img>");
  $(group).load("ajax/addbuildgroup.php?buildid="+buildid,{},function(){$(this).fadeIn('slow');});
  return;
}

function buildnosubmission_click(siteid,buildname,divname,buildgroupid,buildtype)
{
  buildname = URLencode(buildname);
  buildtype = URLencode(buildtype);

  var group = "#infoexpected_"+divname;
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }

  $(group).fadeIn('slow');
  $(group).html("fetching...<img src=images/loading.gif></img>");
  $(group).load("ajax/expectedbuildgroup.php?siteid="+siteid+"&buildname="+buildname+"&buildtype="+buildtype+"&buildgroup="+buildgroupid+"&divname="+divname,{},function(){$(this).fadeIn('slow');});
  return;
}

function buildinfo_click(buildid)
{
  var group = "#buildgroup_"+buildid;
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }

  $(group).fadeIn('slow');
  $(group).html("fetching...<img src=images/loading.gif></img>");
  $(group).load("ajax/buildinfogroup.php?buildid="+buildid,{},function(){$(this).fadeIn('slow');});
  return;
}



function expectedinfo_click(siteid,buildname,divname,projectid,buildtype,currentime)
{
  buildname = URLencode(buildname);

  var group = "#infoexpected_"+divname;
  if($(group).html() != "" && $(group).is(":visible"))
    {
    $(group).fadeOut('medium');
    return;
    }
  $(group).fadeIn('slow');
  $(group).html("fetching...<img src=images/loading.gif></img>");
  $(group).load("ajax/expectedinfo.php?siteid="+siteid+"&buildname="+buildname+"&projectid="+projectid+"&buildtype="+buildtype+"&currenttime="+currentime,{},function(){$(this).fadeIn('slow');});
  return;
}

function removebuild_click(buildid)
{
  if(confirm("Are you sure you want to remove this build?"))
    {
    var group = "#buildgroup_"+buildid;
    $(group).html("updating...");
    $.post("ajax/addbuildgroup.php?buildid="+buildid,{removebuild:"1",buildid:buildid},
        function(data){
          $(group).html("deleted.");
          $(group).fadeOut('slow');
          location.reload();
          return false;
          });
    }
}

function markasexpected_click(buildid,groupid,expected)
{
  var group = "#buildgroup_"+buildid;
  $(group).html("updating...");
  $.post("ajax/addbuildgroup.php?buildid="+buildid,{markexpected:"1",groupid:groupid,expected:expected},
        function(data){
          $(group).html("updated.");
          $(group).fadeOut('slow');
          location.reload();
          return false;
        });
}

function addbuildgroup_click(buildid,groupid,definerule)
{
  var expected = "expected_"+buildid+"_"+groupid;
  var t = document.getElementById(expected);

  var expectedbuild = 0;
  if(t.checked)
    {
    expectedbuild = 1;
    }

  var group = "#buildgroup_"+buildid;
  $(group).html("addinggroup");
  $.post("ajax/addbuildgroup.php?buildid="+buildid,{submit:"1",groupid:groupid,expected:expectedbuild,definerule:definerule},
        function(data){
          $(group).html("added to group.");
          $(group).fadeOut('slow');
          location.reload();
          return false;
        });
}
