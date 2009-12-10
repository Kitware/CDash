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

/*
* testDetails.php shows more detailed information for a particular test that
* was run.  This includes test output and image comparison information
*/
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include('cdash/version.php');

$testid = $_GET["test"];
// Checks
if(!isset($testid) || !is_numeric($testid))
  {
  die('Error: no test id supplied in query string');
  }
  
$buildid = $_GET["build"];
if(!isset($buildid) || !is_numeric($buildid))
  {
  die('Error: no build id supplied in query string');
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$testRow = pdo_fetch_array(pdo_query("SELECT * FROM build2test,test WHERE build2test.testid = '$testid' AND build2test.buildid = '$buildid' AND build2test.testid=test.id"));
$buildRow = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id = '$buildid'"));
$projectid = $buildRow["projectid"];

if(!$projectid)
{
  echo "This build doesn't exist.";
  exit();
}

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
$siteid = $buildRow["siteid"];

$project = pdo_query("SELECT name,nightlytime,showtesttime FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project); 
  $projectname = $project_array["name"];  
  }

$projectRow = pdo_fetch_array(pdo_query("SELECT name,testtimemaxstatus FROM project WHERE id = '$projectid'"));
$projectname = $projectRow["name"];

$siteQuery = "SELECT name FROM site WHERE id = '$siteid'";
$siteResult = pdo_query($siteQuery);
$siteRow = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id = '$siteid'"));

$date = get_dashboard_date_from_build_starttime($buildRow["starttime"], $project_array["nightlytime"]);
list ($previousdate, $currenttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
$logoid = getLogoID($projectid);

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
  
$xml .= "<project>";
$xml .= add_XML_value("showtesttime", $project_array["showtesttime"]) . "\n";
$xml .= "</project>";

$testName = $testRow["name"];
$buildtype = $buildRow["type"];
$buildname = $buildRow["name"];
$starttime = $buildRow["starttime"];

// Helper function
function findTest($buildid,$testName)
{
  $test = pdo_query("SELECT build2test.testid FROM build2test,test
                            WHERE build2test.buildid=".qnum($buildid)." 
                            AND test.id=build2test.testid 
                            AND test.name='$testName'"); 
  if(pdo_num_rows($test)>0)
    {
    $test_array = pdo_fetch_array($test); 
    return  $test_array["testid"];
    }
  return 0;    
}

$xml .= "<menu>";
$xml .= add_XML_value("back","viewTest.php?buildid=".$buildid);
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
$gotprevious = false;
if($previousbuildid>0)
  {
  if($previoustestid = findTest($previousbuildid,$testName))
    {          
    $xml .= add_XML_value("previous","testDetails.php?test=".$previoustestid."&build=".$previousbuildid);
    $gotprevious = true;
    }                         
  }
  
if(!$gotprevious)
  {
  $xml .= add_XML_value("noprevious","1");
  }
  
// Find the last build  
$lastbuildid  = get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($lasttestid = findTest($lastbuildid,$testName))
    {          
    $xml .= add_XML_value("current","testDetails.php?test=".$lasttestid."&build=".$lastbuildid);
    $gotprevious = true;
    }      

// Next build
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
$gotnext = false;
if($nextbuildid>0)
  {
  if($nexttestid = findTest($nextbuildid,$testName))
    {          
    $xml .= add_XML_value("next","testDetails.php?test=".$nexttestid."&build=".$nextbuildid);
    $gotnext = true;
    }                         
  }
  
if(!$gotnext)
  {
  $xml .= add_XML_value("nonext","1");
  }

$xml .= "</menu>";

$summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";

$xml .= "<test>";
$xml .= add_XML_value("id",$testid);
$xml .= add_XML_value("buildid", $buildid);
$xml .= add_XML_value("build", $buildname);
$xml .= add_XML_value("buildstarttime", date(FMT_DATETIMESTD, strtotime($starttime." UTC")));
$xml .= add_XML_value("site", $siteRow["name"]);
$xml .= add_XML_value("test", $testName);
$xml .= add_XML_value("time", $testRow["time"]);
$xml .= add_XML_value("command", $testRow["command"]);
$xml .= add_XML_value("details", $testRow["details"]);

if($CDASH_USE_COMPRESSION)
  {
  @$uncompressedrow = gzuncompress($testRow["output"]);
  if($uncompressedrow !== false)
    {
    $xml .= add_XML_value("output",$uncompressedrow);
    }
  else
    {
    $xml .= add_XML_value("output", $testRow["output"]);
    }
  }
else
  {
  $xml .= add_XML_value("output", $testRow["output"]);
  }
      
$xml .= add_XML_value("summaryLink", $summaryLink);
switch($testRow["status"])
  {
  case "passed":
    $xml .= add_XML_value("status", "Passed");
    $xml .= add_XML_value("statusColor", "#00aa00");
    break;
  case "failed":
    $xml .= add_XML_value("status", "Failed");
    $xml .= add_XML_value("statusColor", "#aa0000");
    break;
  case "notrun":
    $xml .= add_XML_value("status", "Not Run");
    $xml .= add_XML_value("statusColor", "#ffcc66");
    break;
  }
  

$xml .= add_XML_value("timemean",$testRow["timemean"]);
$xml .= add_XML_value("timestd",$testRow["timestd"]);
 
$testtimemaxstatus = $projectRow["testtimemaxstatus"];
if($testRow["timestatus"] < $testtimemaxstatus)
  {
  $xml .= add_XML_value("timestatus", "Passed");
  $xml .= add_XML_value("timeStatusColor", "#00aa00");
  }
else
  {
  $xml .= add_XML_value("timestatus", "Failed");
  $xml .= add_XML_value("timeStatusColor", "#aa0000");
  }  

//get any images associated with this test
$xml .= "<images>";
$query = "SELECT * FROM test2image WHERE testid = '$testid'";
$result = pdo_query($query);
while($row = pdo_fetch_array($result))
  {
  $xml .= "<image>";
  $xml .= add_XML_value("imgid", $row["imgid"]);
  $xml .= add_XML_value("role", $row["role"]);
  $xml .= "</image>";
  }
$xml .= "</images>";

//get any measurements associated with this test
$xml .= "<measurements>";
$query = "SELECT * FROM testmeasurement WHERE testid = '$testid'";
$result = pdo_query($query);
while($row = pdo_fetch_array($result))
  {
  $xml .= "<measurement>";
  $xml .= add_XML_value("name", $row["name"]);
  $xml .= add_XML_value("type", $row["type"]);
  
  // ctest base64 encode the type text/plain...
  $value = $row["value"];
  if($row["type"] == "text/plain")
    {
    if(substr($value,strlen($value)-2) == '==')
      {
      $value = base64_decode($value);
      }
    }  
  
  // Add nl2br for type text/plain and text/string
   if($row["type"] == "text/plain" || $row["type"] == "text/string")
    {
    $value = nl2br($value);  
    }
    
  $xml .= add_XML_value("value", $value);
  $xml .= "</measurement>";
  }
$xml .= "</measurements>";
$xml .= "</test>";
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"testDetails");
?>
