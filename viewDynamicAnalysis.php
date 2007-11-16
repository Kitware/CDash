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
  
$build_array = mysql_fetch_array(mysql_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
    
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $homeurl = $project_array["homeurl"];
  $bugurl = $project_array["bugtrackerurl"];   
  $projectname = $project_array["name"];  
  }

list ($previousdate, $currenttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
$logoid = getLogoID($projectid);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .="<dashboard>
  <datetime>".date("D, d M Y H:i:s",strtotime($build_array["starttime"]))."</datetime>
  <date>".$date."</date>
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
  
  // dynamic analysis
  $i=0;
  $dynamicanalysis = mysql_query("SELECT * FROM dynamicanalysis WHERE buildid='$buildid' ORDER BY status DESC");
  while($dynamicanalysis_array = mysql_fetch_array($dynamicanalysis))
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
    $defects = mysql_query("SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid='$dynid'");
    while($defects_array = mysql_fetch_array($defects))
      {
      $xml .= add_XML_value(str_replace(" ","_",$defects_array["type"]),$defects_array["value"]);
      }
    
    $xml .= "</dynamicanalysis>";
    }
    
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewDynamicAnalysis");
?>
