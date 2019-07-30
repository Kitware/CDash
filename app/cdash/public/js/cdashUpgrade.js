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
      $(divname).html(text+": <img src=\"img/check.gif\"/>");
      nextstep();
      }
    else if(prefix == "WARNING")
      {
      $(divname).html(text+": "+error+" <img src=\"img/check.gif\"/>");
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
  // Go directly to the appropriate version
  nextstep = '';
  if(version < 0.8)
    {
    nextstep = upgrade_0_8;
    }
  else if(version < 1.0)
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
  else if(version < 1.8)
   {
   nextstep = upgrade_1_8;
   }
  else if(version < 2.0)
   {
   nextstep = upgrade_2_0;
   }
  else if(version < 2.2)
   {
   nextstep = upgrade_2_2;
   }
  else if(version < 2.4)
   {
   nextstep = upgrade_2_4;
   }
  else if(version < 2.6)
   {
   nextstep = upgrade_2_6;
   }
  else if(version < 2.8)
   {
   nextstep = upgrade_2_8;
   }
  else if(version < 3.0)
   {
   nextstep = upgrade_3_0;
   }

  if(nextstep != '')
    {
    var text = "Upgrading tables";
    $("#Upgrade-Tables-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
    sendAjax("#Upgrade-Tables-Status","upgrade.php?upgrade-tables=1",text,nextstep);
    }
  else
    {
    $("#Upgrade-Tables-Status").html("Your installation is already up to date");
    }
}

function upgrade_0_8()
{
  var text = "Applying 0.8 patches";
  $("#Upgrade-0-8-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-0-8-Status","upgrade.php?upgrade-0-8=1",text,upgrade_1_0);
}

function upgrade_1_0()
{
  var text = "Applying 1.0 patches";
  $("#Upgrade-1-0-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-0-Status","upgrade.php?upgrade-1-0=1",text,upgrade_1_2);
}

function upgrade_1_2()
{
  var text = "Applying 1.2 patches";
  $("#Upgrade-1-2-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-2-Status","upgrade.php?upgrade-1-2=1",text,upgrade_1_4);
}

function upgrade_1_4()
{
  var text = "Applying 1.4 patches";
  $("#Upgrade-1-4-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-4-Status","upgrade.php?upgrade-1-4=1",text,upgrade_1_6);
}

function upgrade_1_6()
{
  var text = "Applying 1.6 patches";
  $("#Upgrade-1-6-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-6-Status","upgrade.php?upgrade-1-6=1",text,upgrade_1_8);
}

function upgrade_1_8()
{
  var text = "Applying 1.8 patches";
  $("#Upgrade-1-8-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-1-8-Status","upgrade.php?upgrade-1-8=1",text,upgrade_2_0);
}

function upgrade_2_0()
{
  var text = "Applying 2.0 patches";
  $("#Upgrade-2-0-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-2-0-Status","upgrade.php?upgrade-2-0=1",text,upgrade_2_2);
}

function upgrade_2_2()
{
  var text = "Applying 2.2 patches";
  $("#Upgrade-2-2-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-2-2-Status","upgrade.php?upgrade-2-2=1",text,done);
}

function upgrade_2_4()
{
  var text = "Applying 2.4 patches";
  $("#Upgrade-2-4-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-2-4-Status","upgrade.php?upgrade-2-4=1",text,done);
}

function upgrade_2_6()
{
  var text = "Applying 2.6 patches";
  $("#Upgrade-2-6-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-2-6-Status","upgrade.php?upgrade-2-6=1",text,done);
}

function upgrade_2_8()
{
  var text = "Applying 2.8 patches";
  $("#Upgrade-2-8-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-2-8-Status","upgrade.php?upgrade-2-8=1",text,done);
}

function upgrade_3_0()
{
  var text = "Applying 3.0 patches";
  $("#Upgrade-3-0-Status").html("<img src=\"img/loading.gif\"/> "+text+"...");
  sendAjax("#Upgrade-3-0-Status","upgrade.php?upgrade-3-0=1",text,done);
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
