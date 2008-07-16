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

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
  
$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
    
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];  
  }

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
  
  // Build
  $xml .= "<build>";
  $build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = pdo_fetch_array($build); 
  $siteid = $build_array["siteid"];
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("buildid",$build_array["id"]);
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
    $xml .= add_XML_value("filename",$dynamicanalysis_array["name"]);
    $xml .= add_XML_value("id",$dynamicanalysis_array["id"]);
    
    $dynid = $dynamicanalysis_array["id"];
    $defects = pdo_query("SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid='$dynid'");
    while($defects_array = pdo_fetch_array($defects))
      {
      $xml .= add_XML_value(str_replace(" ","_",$defects_array["type"]),$defects_array["value"]);
      }
    
    $xml .= "</dynamicanalysis>";
    }
    
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewDynamicAnalysis");
?>
