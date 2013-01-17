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
include_once("cdash/repository.php");
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
  return;
}

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

// If we have a fileid we download it
if(isset($_GET["fileid"]) && is_numeric($_GET["fileid"]))
  {
  $result = pdo_query("SELECT id,value,name FROM testmeasurement WHERE testid=$testid AND type='file' ORDER BY id");
  for($i=0;$i<$_GET["fileid"];$i++)
    {
    $result_array = pdo_fetch_array($result);
    }
  header("Content-type: tar/gzip");
  header('Content-Disposition: attachment; filename="'.$result_array['name'].'.tgz"');

  if($CDASH_DB_TYPE == "pgsql")
    {
    $buf = "";
    while(!feof($result_array["value"]))
      {
      $buf .= fread($result_array["value"], 2048);
      }
    $buf = stripslashes($buf);
    }
  else
    {
    $buf = $result_array["value"];
    }
  echo base64_decode($buf);
  flush();
  return;
  }

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

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";
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
$xml .= add_XML_value("siteid", $siteid);
$xml .= add_XML_value("test", $testName);
$xml .= add_XML_value("time", $testRow["time"]);
$xml .= add_XML_value("command", $testRow["command"]);
$xml .= add_XML_value("details", $testRow["details"]);

if($CDASH_USE_COMPRESSION)
  {
  if($CDASH_DB_TYPE == "pgsql")
    {
    if(is_resource($testRow["output"]))
      {
      $testRow["output"] = base64_decode(stream_get_contents($testRow["output"]));
      }
    else
      {
      $testRow["output"] = base64_decode($testRow["output"]);
      }
    }
  @$uncompressedrow = gzuncompress($testRow["output"]);
  if($uncompressedrow !== false)
    {
    $xml .= add_XML_value("output",$uncompressedrow);
    }
  else
    {
    $xml .= add_XML_value("output", $testRow['output']);
    }
  }
else
  {
  $xml .= add_XML_value("output", $testRow['output']);
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

// Find the repository revision
$xml .= "<update>";
// Return the status
$status_array = pdo_fetch_array(pdo_query("SELECT status,revision,priorrevision,path
                                              FROM buildupdate,build2update AS b2u
                                              WHERE b2u.updateid=buildupdate.id
                                              AND b2u.buildid='$buildid'"));
if(strlen($status_array["status"]) > 0 && $status_array["status"]!="0")
  {
  $xml .= add_XML_value("status",$status_array["status"]);
  }
else
  {
  $xml .= add_XML_value("status",""); // empty status
  }
$xml .= add_XML_value("revision",$status_array["revision"]);
$xml .= add_XML_value("priorrevision",$status_array["priorrevision"]);
$xml .= add_XML_value("path",$status_array["path"]);
$xml .= add_XML_value("revisionurl",
        get_revision_url($projectid, $status_array["revision"], $status_array["priorrevision"]));
$xml .= add_XML_value("revisiondiff",
        get_revision_url($projectid, $status_array["priorrevision"], '')); // no prior prior revision...
$xml .= "</update>";

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
$query = "SELECT imgid,role FROM test2image WHERE testid = '$testid' ORDER BY id";
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
$query = "SELECT name,type,value FROM testmeasurement WHERE testid = '$testid' ORDER BY id";
$result = pdo_query($query);
$fileid = 1;
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
  else if($row["type"] == "file")
    {
    $xml .= add_XML_value("fileid",$fileid++);
    }
  // Add nl2br for type text/plain and text/string
  if($row["type"] == "text/plain" || $row["type"] == "text/string")
   {
   $value = nl2br($value);
   }

  // If the type is a file we just don't pass the text (too big) to the output
  if($row["type"] == "file")
    {
    $value = "";
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
