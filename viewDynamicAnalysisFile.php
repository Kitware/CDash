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
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");

@$id = $_GET["id"];
@$date = $_GET["date"];


/** Get the previous file id dynamicanalysis*/
function get_previous_fileid_dynamicanalysis($filename,$projectid,$siteid,$buildtype,$buildname,$starttime)
{
  $previousbuild = pdo_query("SELECT dynamicanalysis.id FROM build,dynamicanalysis
                              WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                              AND build.projectid='$projectid' AND build.starttime<'$starttime' 
                              AND dynamicanalysis.buildid=build.id
                              AND dynamicanalysis.name='$filename'
                              ORDER BY build.starttime DESC LIMIT 1");
  
  if(pdo_num_rows($previousbuild)>0)
    {
    $previousbuild_array = pdo_fetch_array($previousbuild);              
    return $previousbuild_array["id"];
    }
  return 0;
}

/** Get the next file id dynamicanalysis*/
function get_next_fileid_dynamicanalysis($filename,$projectid,$siteid,$buildtype,$buildname,$starttime)
{
  $nextbuild = pdo_query("SELECT dynamicanalysis.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid' AND build.starttime>'$starttime' 
                          AND dynamicanalysis.buildid=build.id
                          AND dynamicanalysis.name='$filename'
                          ORDER BY build.starttime ASC LIMIT 1");

  if(pdo_num_rows($nextbuild)>0)
    {
    $nextbuild_array = pdo_fetch_array($nextbuild);              
    return $nextbuild_array["id"];
    }
  return 0;
}

/** Get the last file id dynamicanalysis */
function get_last_fileid_dynamicanalysis($filename,$projectid,$siteid,$buildtype,$buildname,$starttime)
{
 
   $nextbuild = pdo_query("SELECT dynamicanalysis.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid' 
                          AND dynamicanalysis.buildid=build.id
                          AND dynamicanalysis.name='$filename'
                          ORDER BY build.starttime DESC LIMIT 1");

  if(pdo_num_rows($nextbuild)>0)
    {
    $nextbuild_array = pdo_fetch_array($nextbuild);              
    return $nextbuild_array["id"];
    }
  return 0;
}

// Checks
if(!isset($id) || !is_numeric($id))
  {
  echo "Not a valid id!";
  return;
  }
  
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$dyn_array = pdo_fetch_array(pdo_query("SELECT * FROM dynamicanalysis WHERE id='$id'"));
$buildid = $dyn_array["buildid"];

$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
    
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];  
  }

list ($previousdate, $currenttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
$logoid = getLogoID($projectid);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

// Build
$xml .= "<build>";
$build = pdo_query("SELECT starttime,projectid,siteid,type,name FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);
$siteid = $build_array["siteid"];
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= add_XML_value("site",$site_array["name"]);
$xml .= add_XML_value("buildname",$build_array["name"]);
$xml .= add_XML_value("buildid",$buildid);
$xml .= add_XML_value("buildtime",$build_array["starttime"]);  
$xml .= "</build>";
  
$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$xml .= "<menu>";
$xml .= add_XML_value("back","viewDynamicAnalysis.php?buildid=".$buildid);
$previousfileid = get_previous_fileid_dynamicanalysis($dyn_array["name"],$projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousfileid>0)
  {
  $xml .= add_XML_value("previous","viewDynamicAnalysisFile.php?id=".$previousfileid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }
$xml .= add_XML_value("current","viewDynamicAnalysisFile.php?id=".get_last_fileid_dynamicanalysis($dyn_array["name"],$projectid,$siteid,$buildtype,$buildname,$starttime));  
$nextfileid = get_next_fileid_dynamicanalysis($dyn_array["name"],$projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextfileid>0)
  {
  $xml .= add_XML_value("next","viewDynamicAnalysisFile.php?id=".$nextfileid);
  }  
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";
  
 
  // dynamic analysis
  $xml .= "<dynamicanalysis>";
  $xml .= add_XML_value("status",ucfirst($dyn_array["status"]));
  $xml .= add_XML_value("filename",$dyn_array["name"]);
  $xml .= add_XML_value("log",$dyn_array["log"]);
  $href = "testSummary.php?project=".$projectid."&name=".$dyn_array["name"];
  if($date)
    {
    $href .= "&date=".$date;
    }
  else
    {
    $href .= "&date=".date(FMT_DATE);
    }
  $xml .= add_XML_value("href",$href);
  $xml .= "</dynamicanalysis>";
    
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewDynamicAnalysisFile");
?>
