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
include("../config.php");
include("../common.php");

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$siteid = $_GET["siteid"];
$buildname = mysql_real_escape_string($_GET["buildname"]);
$projectid = $_GET["projectid"];
$buildtype = mysql_real_escape_string($_GET["buildtype"]);
$currenttime = mysql_real_escape_string($_GET["currenttime"]);

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
    
$project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = mysql_fetch_array($project);

$currentUTCtime =  gmdate("YmdHis",$currenttime);
    
// Find the last build corresponding to thie siteid and buildid
$lastbuild = mysql_query("SELECT starttime FROM build
                          WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                          AND projectid='$projectid' AND starttime<='$currentUTCtime' ORDER BY starttime DESC LIMIT 1");

if(mysql_num_rows($lastbuild)>0)
  {
  $lastbuild_array = mysql_fetch_array($lastbuild);              
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
    $date = substr($datelastbuild,0,4).substr($datelastbuild,5,2).substr($datelastbuild,8,2);
    echo "This build has not been submitting since <b><a href=\"index.php?project=".$project_array["name"]."&date=".$date."\">".$datelastbuild."</a> (".$lastsbuilddays." days)</b>";
    }
  ?>
  </font></td>
  </tr>
</table>
  </form>
</html>

