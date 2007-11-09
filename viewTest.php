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
include("config.php");
include("common.php");

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
		
$build_array = mysql_fetch_array(mysql_query("SELECT * FROM build WHERE id='$buildid'"));		
$projectid = $build_array["projectid"];

if(!isset($date) || strlen($date)==0)
  { 
  $date = date("Ymd", strtotime($build_array["starttime"]));
  }
				
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $homeurl = $project_array["homeurl"];
  $bugurl = $project_array["bugtrackerurl"];			
  $projectname	= $project_array["name"];		
  }

list ($previousdate, $date, $nextdate) = get_dates($date);
$currenttime = mktime("23","59","0",substr($date,4,2),substr($date,6,2),substr($date,0,4));
$logoid = getLogoID($projectid);

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .="<dashboard>
  <datetime>".date("D, d M Y H:i:s",$currenttime)."</datetime>
  <date>".date("l, F d Y",$currenttime)."</date>
  <svn>".$svnurl."</svn>
  <bugtracker>".$bugurl."</bugtracker>	
  <home>".$homeurl."</home>
  <projectid>".$projectid."</projectid>	
  <logoid>".$logoid."</logoid>
  <projectname>".$projectname."</projectname>	
  <previousdate>".$previousdate."</previousdate>	
  <nextdate>".$nextdate."</nextdate>	
  </dashboard>
  ";
		
#$siteid = $build_array["siteid"];
#$site_array =
#  mysql_fetch_array(mysql_query("SELECT * FROM build WHERE id='$siteid'"));
$siteid = $build_array["siteid"];
$site_array =
  mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= "<build>\n";
$xml .= add_XML_value("site",$site_array["name"]) . "\n";
$xml .= add_XML_value("buildname",$build_array["name"]) . "\n";
$xml .= add_XML_value("buildid",$build_array["id"]) . "\n";
$xml .= add_XML_value("testtime", $build_array["endtime"]) . "\n";
$xml .= "</build>\n";

// Gather test info
$xml .= "<tests>\n";
$result = mysql_query("SELECT * FROM test WHERE buildid='$buildid' ORDER BY status,name");
$numPassed = 0;
$numFailed = 0;
$numNotRun = 0;
$color = FALSE;
while($row = mysql_fetch_array($result))
  {
  $xml .= "<test>\n";
  $testName = $row["name"];
  $xml .= add_XML_value("name", $testName) . "\n";
  $xml .= add_XML_value("execTime", $row["time"]) . "\n";
  $xml .= add_XML_value("details", $row["details"]) . "\n"; 
  $summaryLink = "testSummary.php?project=$projectid&amp;name=$testName&amp;date=$date";
  $xml .= add_XML_value("summaryLink", $summaryLink) . "\n";
  $testid = $row["id"]; 
  $detailsLink = "testDetails.php?test=$testid";
  $xml .= add_XML_value("detailsLink", $detailsLink) . "\n";
  if($color)
    {
    $xml .= add_XML_value("class", "tr-even") . "\n";
    }
  else
    {
    $xml .= add_XML_value("class", "tr-odd") . "\n";
    }
  $color = !$color;
  switch($row["status"])
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed") . "\n";
      $numPassed++;
      break; 
    case "failed":
      $xml .= add_XML_value("status", "Failed") . "\n";
      $numFailed++;
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run") . "\n";
      $numNotRun++;
      break;
    }
  $xml .= "</test>\n";
  }
$xml .= "</tests>\n";
$xml .= add_XML_value("numPassed", $numPassed) . "\n";
$xml .= add_XML_value("numFailed", $numFailed) . "\n";
$xml .= add_XML_value("numNotRun", $numNotRun) . "\n";
$xml .= "</cdash>\n";

// Now doing the xslt transition
generate_XSLT($xml,"viewTest");
?>
