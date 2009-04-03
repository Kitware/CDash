/* Helper function */
function sendAjax(divname,ajaxurl,text,nextstep)
{
  $.ajax({
   type: "GET",
   url: ajaxurl,
   success: function(msg){
    
    $(divname).html(msg);
    
    var index = msg.indexOf(':');
    var prefix = '';
    var error = '';
    if(index>0)
      {
      prefix = msg.substr(0,index);
      error = msg.substr(index+1);
      }
    
    if(prefix != "ERROR" && prefix != "WARNING")
      {
      $(divname).html(text+": <img src=\"images/check.gif\"/>"); 
      nextstep();
      }
    else if(prefix == "WARNING")
      {
      $(divname).html(text+": "+error+" <img src=\"images/check.gif\"/>");
      nextstep();
      }
    else
      {
      $(divname).html("An error as occured");
      }
   },
   error:function (XMLHttpRequest, textStatus, errorThrown) {
    alert(textStatus+' '+errorThrown);
    }

   });
}

function upgrade_tables()
{
  var text = "Upgrading tables";
  $("#Upgrade-Tables-Status").html("<img src=\"images/loading.gif\"/> "+text+"...");

  // Go directly to the appropriate version
  nextstep = upgrade_0_8;
  if(version < 1.0)
    {
    nextstep = upgrade_1_0;
    }
  else if(version < 1.2)
    {
    nextstep = upgrade_1_2;
    }
  else if(version < 1.4)
    {
    nextstep = upgrade_1_4;
    }
  else if(version < 1.6)
   {
   nextstep = upgrade_1_6;
   }
 
  sendAjax("#Upgrade-Tables-Status","backwardCompatibilityTools.php?upgrade-tables=1",text,nextstep);  
}

function upgrade_0_8()
{
  var text = "Applying 0.8 patches";
  $("#Upgrade-0-8-Status").html("<img src=\"images/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-0-8-Status","backwardCompatibilityTools.php?upgrade-0-8=1",text,upgrade_1_0);  
}

function upgrade_1_0()
{
  var text = "Applying 1.0 patches";
  $("#Upgrade-1-0-Status").html("<img src=\"images/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-0-Status","backwardCompatibilityTools.php?upgrade-1-0=1",text,upgrade_1_2);  
}

function upgrade_1_2()
{
  var text = "Applying 1.2 patches";
  $("#Upgrade-1-2-Status").html("<img src=\"images/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-2-Status","backwardCompatibilityTools.php?upgrade-1-2=1",text,upgrade_1_4);  
}

function upgrade_1_4()
{
  var text = "Applying 1.4 patches";
  $("#Upgrade-1-4-Status").html("<img src=\"images/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-4-Status","backwardCompatibilityTools.php?upgrade-1-4=1",text,done);  
}

function upgrade_1_6()
{
  var text = "Applying 1.6 patches";
  $("#Upgrade-1-6-Status").html("<img src=\"images/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-6-Status","backwardCompatibilityTools.php?upgrade-1-6=1",text,done);  
}
// empty function needed
function done()
{
  $("#DoneStatus").html("<b>CDash Upgrade Successful.</b>");
}

$(document).ready(function() {
  // Trigger the first ajax function
  upgrade_tables();
});
