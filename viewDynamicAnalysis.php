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

@$buildid = $_GET["buildid"];
if ($buildid != NULL)
  {
  $buildid = pdo_real_escape_numeric($buildid);
  }

@$date = $_GET["date"];
if ($date != NULL)
  {
  $date = htmlspecialchars(pdo_real_escape_string($date));
  }

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
  
$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid,siteid,type,name FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
if(!isset($projectid) || $projectid==0)
  {
  echo "This build doesn't exist. Maybe it has been deleted.";
  exit();
  }
  
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
    
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];  
  }

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
 
$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]));
$previousbuildid = get_previous_buildid_dynamicanalysis($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewDynamicAnalysis.php?buildid=".$previousbuildid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }
$xml .= add_XML_value("current","viewDynamicAnalysis.php?buildid=".get_last_buildid_dynamicanalysis($projectid,$siteid,$buildtype,$buildname,$starttime));  
$nextbuildid = get_next_buildid_dynamicanalysis($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewDynamicAnalysis.php?buildid=".$nextbuildid);
  }  
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";
  
  // Build
  $xml .= "<build>";
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("buildid",$buildid);
  $xml .= add_XML_value("buildtime",$build_array["starttime"]);  
  $xml .= "</build>";
  
  // dynamic analysis
  $i=0;
  $dynamicanalysis = pdo_query("SELECT * FROM dynamicanalysis WHERE buildid='$buildid' ORDER BY status DESC");
  while($dynamicanalysis_array = pdo_fetch_array($dynamicanalysis))
    {
    $xml .= "<dynamicanalysis>";
    if($i%2==0)
      {
      $xml .= add_XML_value("bgcolor","#b0c4de");
      }
    $i++;
    $xml .= add_XML_value("status",ucfirst($dynamicanalysis_array["status"]));
    $xml .= add_XML_value("name",$dynamicanalysis_array["name"]);
    $xml .= add_XML_value("id",$dynamicanalysis_array["id"]);
    
    $dynid = $dynamicanalysis_array["id"];
    $defects = pdo_query("SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid='$dynid'");
    while($defects_array = pdo_fetch_array($defects))
      {
      $xml .= add_XML_value(str_replace(" ","_",$defects_array["type"]),$defects_array["value"]);
      }

    $xml .= get_labels_xml_from_query_results(
      "SELECT text FROM label, label2dynamicanalysis WHERE ".
      "label.id=label2dynamicanalysis.labelid AND ".
      "label2dynamicanalysis.dynamicanalysisid='$dynid' ".
      "ORDER BY text ASC"
      );

    $xml .= "</dynamicanalysis>";
    }
    
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewDynamicAnalysis");
?>
