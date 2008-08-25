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
$noforcelogin = 1;
include("config.php");
require_once("pdo.php");
include('login.php');
include_once("common.php");
include("version.php"); 

@$projectname = $_GET["project"];
if(!isset($projectname))
  {
  die("Error: project not specified<br>\n");
  }
@$date = $_GET["date"];
 
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$projectname = pdo_real_escape_string($projectname);
$project = pdo_query("SELECT id,nightlytime FROM project WHERE name='$projectname'");
$project_array = pdo_fetch_array($project);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>".$projectname." : Test Overview</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

$nightlytime = $project_array["nightlytime"];
// We select the builds
list ($previousdate, $currentstarttime, $nextdate,$today) = get_dates($date,$nightlytime);
$xml .= "<menu>";
$xml .= add_XML_value("previous","buildOverview.php?project=".$projectname."&date=".$previousdate);
if($date!="" && date("Ymd", $currentstarttime)!=date("Ymd"))
  {
  $xml .= add_XML_value("next","buildOverview.php?project=".$projectname."&date=".$nextdate);
  }
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= add_XML_value("current","buildOverview.php?project=".$projectname."&date=");
$xml .= add_XML_value("back","index.php?project=".$projectname."&date=".get_dashboard_date_from_project($projectname,$date));
$xml .= "</menu>";

// Get some information about the specified project
$projectname = pdo_real_escape_string($projectname);
$projectQuery = "SELECT id, nightlytime FROM project WHERE name = '$projectname'";
$projectResult = pdo_query($projectQuery);
if(!$projectRow = pdo_fetch_array($projectResult))
  {
  die("Error:  project $projectname not found<br>\n");
  }
$projectid = $projectRow["id"];
$nightlytime = $projectRow["nightlytime"];

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

// Return the available groups
@$groupSelection = $_POST["groupSelection"];
if(!isset($groupSelection))
  {  
  $groupSelection = 0;
  }
  
$buildgroup = pdo_query("SELECT id,name FROM buildgroup WHERE projectid='$projectid'");
while($buildgroup_array = pdo_fetch_array($buildgroup))
{
  $xml .= "<group>";
  $xml .= add_XML_value("id",$buildgroup_array["id"]);
  $xml .= add_XML_value("name",$buildgroup_array["name"]);
  if($groupSelection == $buildgroup_array["id"])
    {
    $xml .= add_XML_value("selected","1");
    }
  $xml .= "</group>";
}

$groupSelectionSQL = "";
if($groupSelection>0)
  {
  $groupSelectionSQL = " AND b2g.buildid=build.id AND b2g.groupid='$groupSelection' ";
  }  

// Get each build that was submitted on this date
$rlike = "RLIKE";
if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE == "pgsql")
   {
   $rlike = "~";
   }
$buildQuery = "SELECT id FROM build,build2group as b2g WHERE stamp ".$rlike." '^$date-' AND projectid = '$projectid'".$groupSelectionSQL; 
$buildResult = pdo_query($buildQuery);
$builds = array();
while($buildRow = pdo_fetch_array($buildResult))
  {
  array_push($builds, $buildRow["id"]);
  }

//find all the tests that were performed for this project on this date
//skip tests that passed on all builds
$firstTime = TRUE;
$testQuery = "";
foreach($builds as $id)
{
if($firstTime)
  {
  $testQuery =
    "SELECT DISTINCT test.name FROM test,build2test WHERE (build2test.buildid='$id'";
  $firstTime = FALSE;
  }
else
  {
  $testQuery .= " OR build2test.buildid='$id'";
  }
}
$testQuery .= ") AND build2test.testid=test.id AND build2test.status NOT LIKE 'passed'";

@$testResult = pdo_query($testQuery);
if($testResult !== FALSE)
  {
  $tests = array();
  while($testRow = pdo_fetch_array($testResult))
    {
    array_push($tests, $testRow["name"]);
    }
  natcasesort($tests);
  
  //now generate some XML
  $xml .= "<tests>\n";
  $previousLetter = "";
  $firstSection = TRUE;
  foreach($tests as $testName)
    {
    $letter = strtolower(substr($testName, 0, 1));
    if($letter != $previousLetter)
      {
      if($firstSection)
        {
        $xml .= "<section>\n";
        $firstSection = FALSE;
        }
      else
        {
        $xml .= "</section>\n<section>";
        }
      $xml .= add_XML_value("sectionName", $letter) . "\n";
      $previousLetter = $letter;
      }
    $xml .= "<test>\n";
    $xml .= add_XML_value("testName", $testName) . "\n";
    $summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";
    $xml .= add_XML_value("summaryLink", $summaryLink) . "\n";
    $xml .= "</test>\n";
    }
    
  if(count($tests)>0)
    {
    $xml .= "</section>\n";
    }
  $xml .= "</tests>\n";
  }  
$xml .= "</cdash>";

generate_XSLT($xml, "testOverview");
?>
