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
// Insert into the group
mysql_query("INSERT INTO build2group(groupid,buildtype,buildname,buildsiteid,expected) VALUES
             ('$groupid','$buildtype','$buildname','$siteid','$expected')");
return;
}




// Find the groups available for this project
$group = mysql_query("SELECT name,id FROM buildgroup WHERE id NOT IN 
                     (SELECT groupid as id FROM build2group WHERE buildname='$buildname' AND buildsiteid='$siteid') 
																					AND projectid='$projectid'");
		
	// AND b2g.groupid=bg.id  AND (b2g.buildname!='$buildname' AND b2g.buildsiteid!='$siteid')																							
?>
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
}
</script>
<?php
while($group_array = mysql_fetch_array($group))
  {
		echo "<td bgcolor=#DDDDDD> Add build to group <b>".$group_array["name"]."</b>: 
		<input onclick=\"javascript:addexpectedbuild_click(this.value)\" name=\"expected\" type=\"checkbox\" value=\"1\"> expected
		<input onclick=\"javascript:addbuildgroup_click(".$buildid.",".$group_array["id"].")\" type=\"button\" value=\"add to group\"><br></td>
		";
		}
?>
