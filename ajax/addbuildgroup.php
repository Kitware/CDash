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

$buildid = $_GET["buildid"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$build = mysql_query("SELECT name,type,siteid,projectid FROM build WHERE id='$buildid'");
$build_array = mysql_fetch_array($build);

$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$projectid = $build_array["projectid"];

@$submit = $_POST["submit"];

@$groupid = $_POST["groupid"];
@$expected = $_POST["expected"];
if($submit)
{
// Remove the group
$prevgroup = mysql_fetch_array(mysql_query("SELECT groupid as id FROM build2group WHERE buildid='$buildid'"));
$prevgroupid = $prevgroup["id"];	
																				
mysql_query("DELETE FROM build2group WHERE groupid='$prevgroupid' AND buildid='$buildid'");
echo mysql_error();
// Insert into the group
mysql_query("INSERT INTO build2group(groupid,buildid,expected) VALUES ('$groupid','$buildid','$expected')");
echo mysql_error();		
return;
}

// Find the groups available for this project
$group = mysql_query("SELECT name,id FROM buildgroup WHERE id NOT IN 
                     (SELECT groupid as id FROM build2group WHERE buildid='$buildid') 
																					 AND projectid='$projectid'");
		
	// AND b2g.groupid=bg.id  AND (b2g.buildname!='$buildname' AND b2g.buildsiteid!='$siteid')																							
?>

<head>
<style type="text/css">
  .submitLink {
   color: #00f;
   background-color: transparent;
   text-decoration: underline;
   border: none;
   cursor: pointer;
   cursor: hand;
  }
</style>
</head>

<script type="text/javascript" charset="utf-8">
var expectedbuild = 0;
function addexpectedbuild_click(value)
{
  expectedbuild = value;
}

function addbuildgroup_click(buildid,groupid)
{
  var group = "#buildgroup_"+buildid;
		$(group).html("addinggroup");
		$.post("ajax/addbuildgroup.php?buildid="+buildid,{submit:"1",groupid:groupid,expected:expectedbuild});
		$(group).html("added to group!");
	 $(group).fadeOut('slow');
		window.location = "";
}
</script>
	<form method="post" action="">

		<table width="100%"  border="0">
<?php
while($group_array = mysql_fetch_array($group))
  {
?>
	 <tr>
    <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $group_array["name"] ?></b>:		</font></td>
    <td bgcolor="#DDDDDD" width="20%"><font size="2"><input onclick="javascript:addexpectedbuild_click(this.value)" name="expected" type="checkbox" value=\"1\"/> expected</font></td>
    <td bgcolor="#DDDDDD" width="45%"><font size="2"><a href="#" onclick="javascript:addbuildgroup_click(<?php echo $buildid ?>,<?php echo $group_array["id"]?>)">[move to group]</a></font></td>
  </tr>
<?php
		}
?>
</table>
		</form>
</html>