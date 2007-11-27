<html>
<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("../config.php");
include("../common.php");

$siteid = $_GET["siteid"];
$buildname = $_GET["buildname"];
$buildtype = $_GET["buildtype"];
$buildgroupid = $_GET["buildgroup"];
$divname = $_GET["divname"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$currentgroup = mysql_query("SELECT id,name,projectid FROM buildgroup WHERE id='$buildgroupid'");
$currentgroup_array = mysql_fetch_array($currentgroup);

$projectid = $currentgroup_array["projectid"];

@$submit = $_POST["submit"];

@$groupid = $_POST["groupid"];
@$expected = $_POST["expected"];
@$markexpected = $_POST["markexpected"];
@$previousgroupid= $_POST["previousgroupid"];

if($markexpected)
{
  // If a rule already exists we update it
  mysql_query("UPDATE build2grouprule SET expected='$expected' WHERE groupid='$groupid' AND buildtype='$buildtype'
                      AND buildname='$buildname' AND siteid='$siteid' AND endtime='0000-00-00 00:00:00'");
																				
}

if($submit)
{
  // Mark any previous rule as done
  $now = gmdate("Y-m-d H:i:s");
  mysql_query("UPDATE build2grouprule SET endtime='$now'
               WHERE groupid='$previousgroupid' AND buildtype='$buildtype'
               AND buildname='$buildname' AND siteid='$siteid' AND endtime='0000-00-00 00:00:00'");

  // Add the new rule (begin time is set by default by mysql
  mysql_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime) 
               VALUES ('$groupid','$buildtype','$buildname','$siteid','$expected','$now')");

return;
}

// Find the groups available for this project
$group = mysql_query("SELECT name,id FROM buildgroup WHERE id!='$buildgroupid' AND projectid='$projectid'");                    
?>

<script type="text/javascript" charset="utf-8">

function markasnonexpected_click(siteid,buildname,buildtype,groupid,expected,divname)
{
  var group = "#infoexpected_"+divname;
  $(group).html("updating...");
  $.post("ajax/expectedbuildgroup.php?siteid="+siteid+"&buildname="+buildname+"&buildtype="+buildtype+"&divname="+divname,{markexpected:"1",groupid:groupid,expected:expected});
  $(group).html("updated!");
  $(group).fadeOut('slow');
  window.location = "";
}

function movenonexpectedbuildgroup_click(siteid,buildname,buildtype,groupid,previousgroupid,divname,expectedtag)
{
  var tag = "expectednosubmission_"+expectedtag;
	 var t = document.getElementById(tag);
  var expectedbuild = 0;
  if(t.checked)
    {
    expectedbuild = 1;
    }

  var group = "#infoexpected_"+divname;
  $(group).html("addinggroup");
  $.post("ajax/expectedbuildgroup.php?siteid="+siteid+"&buildname="+buildname+"&buildtype="+buildtype+"&divname="+divname,{submit:"1",groupid:groupid,expected:expectedbuild,previousgroupid:previousgroupid});
  $(group).html("added to group!");
  $(group).fadeOut('slow');
  window.location = "";
}
</script>
 <form method="post" action="">

  <table width="100%"  border="0">
  <tr>
  <?php
  // If expected
  // Find the groups available for this project
  $isexpected = 0;
  $currentgroupid = $currentgroup_array["id"];
  
  // This works only for the most recent dashboard (and future)  
  $build2groupexpected = mysql_query("SELECT groupid FROM build2grouprule WHERE groupid='$currentgroupid' AND buildtype='$buildtype'
                                      AND buildname='$buildname' AND siteid='$siteid' AND endtime='0000-00-00 00:00:00' AND expected='1'");
  if(mysql_num_rows($build2groupexpected) > 0 )
    {
    $isexpected = 1;
    }  
  ?>
  <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $currentgroup_array["name"] ?></b>:  </font></td>
  <td bgcolor="#DDDDDD" width="65%" colspan="2"><font size="2"><a href="#" onclick="javascript:markasnonexpected_click('<?php echo $siteid ?>','<?php echo $buildname ?>','<?php echo $buildtype ?>','<?php echo $currentgroup_array["id"]?>',
  <?php if($isexpected) {echo "0";} else {echo "1";} ?>,'<?php echo $divname ?>')">
  [<?php 
  if($isexpected)
    {
    echo "mark as non expected";
    }
  else
    {
    echo "mark as expected";
    }
  
  ?>]</a> </font></td>
  </tr>
<?php
while($group_array = mysql_fetch_array($group))
  {
?>
  <tr>
    <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $group_array["name"] ?></b>:  </font></td>
    <td bgcolor="#DDDDDD" width="20%"><font size="2"><input id="expectednosubmission_<?php $expectedtag = rand(); echo $expectedtag; ?>" type="checkbox"/> expected</font></td>
    <td bgcolor="#DDDDDD" width="45%"><font size="2">	
				<a href="#" onclick="javascript:movenonexpectedbuildgroup_click('<?php echo $siteid ?>','<?php echo $buildname ?>','<?php echo $buildtype ?>','<?php echo $group_array["id"]?>','<?php echo $currentgroup_array["id"]?>','<?php echo $divname ?>','<?php echo $expectedtag ?>')">[move to group]</a>
    </font></td>
  </tr>
<?php
  }
?>
</table>
  </form>
</html>
