<html>
<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/config.php");
require_once("cdash/pdo.php");
require_once("cdash/common.php");

$noforcelogin = 1;
include('login.php');

@$userid = $_GET["userid"];
if ($userid != null) {
    $userid = pdo_real_escape_numeric($userid);
}

if (!$userid && !isset($_SESSION['cdash'])) {
    echo "Not a valid user id";
    return;
}

$buildid = pdo_real_escape_numeric($_GET["buildid"]);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo "Not a valid buildid!";
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$projectid = $build_array["projectid"];

@$submit = $_POST["submit"];

@$groupid = $_POST["groupid"];
if ($groupid != null) {
    $groupid = pdo_real_escape_numeric($groupid);
}

@$expected = $_POST["expected"];
if ($expected != null) {
    $expected = pdo_real_escape_numeric($expected);
}

@$definerule = $_POST["definerule"];
@$markexpected = $_POST["markexpected"];

if ($markexpected) {
    // If a rule already exists we update it
  $build2groupexpected = pdo_query("SELECT groupid FROM build2grouprule WHERE groupid='$groupid' AND buildtype='$buildtype'
                                      AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00'");
    if (pdo_num_rows($build2groupexpected) > 0) {
        pdo_query("UPDATE build2grouprule SET expected='$expected' WHERE groupid='$groupid' AND buildtype='$buildtype'
                                        AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00'");
    } elseif ($expected) {
        // we add the grouprule

    $now = gmdate(FMT_DATETIME);
        pdo_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime,endtime) 
                 VALUES ('$groupid','$buildtype','$buildname','$siteid','$expected','$now','1980-01-01 00:00:00')");
    }
}

@$removebuild = $_POST["removebuild"];

if ($removebuild) {
    add_log("Build #".$buildid." removed manualy", "addbuildgroup");
    remove_build($buildid);
}


if ($submit) {
    // Remove the group
$prevgroup = pdo_fetch_array(pdo_query("SELECT groupid as id FROM build2group WHERE buildid='$buildid'"));
    $prevgroupid = $prevgroup["id"];
                    
    pdo_query("DELETE FROM build2group WHERE groupid='$prevgroupid' AND buildid='$buildid'");

// Insert into the group
pdo_query("INSERT INTO build2group(groupid,buildid) VALUES ('$groupid','$buildid')");

    if ($definerule) {
        // Mark any previous rule as done
  $now = gmdate(FMT_DATETIME);
        pdo_query("UPDATE build2grouprule SET endtime='$now'
               WHERE groupid='$prevgroupid' AND buildtype='$buildtype'
               AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00'");

  // Add the new rule (begin time is set by default by mysql
  pdo_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime,endtime) 
               VALUES ('$groupid','$buildtype','$buildname','$siteid','$expected','$now','1980-01-01 00:00:00')");
    }

    return;
}

// Find the groups available for this project
$group = pdo_query("SELECT name,id FROM buildgroup WHERE id NOT IN 
                     (SELECT groupid as id FROM build2group WHERE buildid='$buildid') 
                      AND projectid='$projectid'");
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
 <form method="post" action="">

  <table width="100%"  border="0">
  <tr>
  <?php
  // If expected
  // Find the groups available for this project
  $currentgroup = pdo_query("SELECT g.name,g.id FROM buildgroup AS g,build2group as bg WHERE g.id=bg.groupid  AND bg.buildid='$buildid'");
  $currentgroup_array = pdo_fetch_array($currentgroup);
  $isexpected = 0;
  $currentgroupid = $currentgroup_array ["id"];
  
  // This works only for the most recent dashboard (and future)
  $build2groupexpected = pdo_query("SELECT groupid FROM build2grouprule WHERE groupid='$currentgroupid' AND buildtype='$buildtype'
                                      AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00' AND expected='1'");
  if (pdo_num_rows($build2groupexpected) > 0) {
      $isexpected = 1;
  }
  ?>
  <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $currentgroup_array["name"] ?></b>:  </font></td>
  <td bgcolor="#DDDDDD" width="65%" colspan="2"><font size="2"><a href="#" onclick="javascript:markasexpected_click(<?php echo $buildid ?>,<?php echo $currentgroup_array["id"]?>,
  <?php if ($isexpected) {
    echo "0";
} else {
    echo "1";
} ?>)">
  [<?php 
  if ($isexpected) {
      echo "mark as non expected";
  } else {
      echo "mark as expected";
  }
  
  ?>]</a> </font></td>
  </tr>
<?php
while ($group_array = pdo_fetch_array($group)) {
    ?>
  <tr>
    <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $group_array["name"] ?></b>:  </font></td>
    <td bgcolor="#DDDDDD" width="20%"><font size="2"><input id="expected_<?php echo $buildid."_".$group_array["id"] ?>" type="checkbox"/> expected</font></td>
    <td bgcolor="#DDDDDD" width="45%"><font size="2"> 
    <a href="#" onclick="javascript:addbuildgroup_click(<?php echo $buildid ?>,<?php echo $group_array["id"]?>,1)">[move to group]</a>
    </font></td>
  </tr>
<?php

}
?>
<tr>
    <td bgcolor="#DDDDDD" width="35%" colspan="3"><font size="2">
    <a href="#" onclick="javascript:removebuild_click(<?php echo $buildid ?>)">[remove this build]</a>
    </font></td>
  </tr>
</table>
  </form>
</html>
