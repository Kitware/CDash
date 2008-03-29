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
include('login.php');
include("common.php");
include("version.php");

@$buildid = $_GET["buildid"];
@$fileid = $_GET["fileid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
  
include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$build_array = mysql_fetch_array(mysql_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
    
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
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
  $build = mysql_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = mysql_fetch_array($build);
  $siteid = $build_array["siteid"];
  $site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= add_XML_value("buildtime",$build_array["starttime"]); 
  $xml .= "</build>";
  
  // coverage
  $coveragefile_array = mysql_fetch_array(mysql_query("SELECT * FROM coveragefile WHERE id='$fileid'"));

  $xml .= "<coverage>";
  $xml .= add_XML_value("fullpath",$coveragefile_array["fullpath"]);
  $file = $coveragefile_array["file"];
  
  // Generating the html file
  $file_array = explode("<br>",$file);
  $i = 0;
  foreach($file_array as $line)
    {
    $coveragefilelog = mysql_query("SELECT line,code FROM coveragefilelog WHERE fileid='$fileid' AND buildid='$buildid' AND line='$i'");
    if(mysql_num_rows($coveragefilelog)>0)
      {
      $coveragefilelog_array = mysql_fetch_array($coveragefilelog);
      $file_array[$i] = str_pad($coveragefilelog_array["code"],8, "0", STR_PAD_LEFT)."&nbsp;&nbsp;&nbsp;".$line;
      }
    else
      {
      $file_array[$i] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$line;
      }
    $i++;
    }
  
  $file = implode("<br>",$file_array);
  
  $xml .= "<file>".htmlspecialchars($file)."</file>";
  $xml .= "</coverage>";
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewCoverageFile");
?>
