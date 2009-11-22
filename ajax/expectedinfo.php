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
require_once("../cdash/config.php");
require_once("../cdash/pdo.php");
require_once("../cdash/common.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$siteid = $_GET["siteid"];
$buildname = pdo_real_escape_string($_GET["buildname"]);
$projectid = $_GET["projectid"];
$buildtype = pdo_real_escape_string($_GET["buildtype"]);
$currenttime = pdo_real_escape_string($_GET["currenttime"]);

// Checks
if(!isset($siteid) || !is_numeric($siteid))
  {
  echo "Not a valid siteid!";
  return;
  }
if(!isset($projectid) || !is_numeric($projectid))
  {
  echo "Not a valid projectid!";
  return;
  }
    
$project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);

$currentUTCtime =  gmdate(FMT_DATETIME,$currenttime);
    
// Find the last build corresponding to thie siteid and buildid
$lastbuild = pdo_query("SELECT starttime FROM build
                          WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                          AND projectid='$projectid' AND starttime<='$currentUTCtime' ORDER BY starttime DESC LIMIT 1");

if(pdo_num_rows($lastbuild)>0)
  {
  $lastbuild_array = pdo_fetch_array($lastbuild);              
  $datelastbuild = $lastbuild_array["starttime"];
  $lastsbuilddays = round(($currenttime-strtotime($datelastbuild))/(3600*24));
  }
else
  {
  $lastsbuilddays = -1;
  }
?>
  <table width="100%"  border="0">
  <tr>
  <td bgcolor="#DDDDDD" id="nob"><font size="2">
  <?php 
  if($lastsbuilddays == -1)
    {
    echo "This build has never submitted.";
    }
  else if($lastsbuilddays>=0)
    {
    $date = date2year($datelastbuild).date2month($datelastbuild).date2day($datelastbuild);
    echo "This build has not been submitting since <b><a href=\"index.php?project=".urlencode($project_array["name"])."&date=".$date."\">".date('M j, Y ',strtotime($datelastbuild))."</a> (".$lastsbuilddays." days)</b>";
    }
  ?>
  </font></td>
  </tr>
</table>
</html>

