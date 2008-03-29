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
include("config.php");
include("common.php");
include("version.php");

@$projectname = $_GET["project"];
if(!isset($projectname) || strlen($projectname)==0)
  {
  die("Error: project not specified<br>\n");
  }
@$date = $_GET["date"];
if(!isset($date) or $date == "")
  {
  $date = date("Ymd",time());
  }
 
 
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>".$projectname." : Build Overview</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

//get some information about the specified project
$project = mysql_query("SELECT id, nightlytime FROM project WHERE name = '$projectname'");
if(!$project_array = mysql_fetch_array($project))
  {
  die("Error:  project $projectname not found<br>\n");
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);

$projectid = $project_array["id"];
$nightlytime = $project_array["nightlytime"];

// We select the builds
list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$nightlytime);
// Check the builds
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime+3600*24;

$beginning_UTCDate = gmdate("YmdHis",$beginning_timestamp);
$end_UTCDate = gmdate("YmdHis",$end_timestamp);                                                      
  
$sql =  "SELECT s.name,b.name as buildname,be.type,be.sourcefile,be.sourceline,be.text
                         FROM build AS b,builderror as be,site AS s
                         WHERE b.starttime<$end_UTCDate AND b.starttime>$beginning_UTCDate
                         AND b.projectid='$projectid' AND be.buildid=b.id 
                         AND s.id=b.siteid
                         ORDER BY be.sourcefile ASC,be.type ASC,be.sourceline ASC";
    
$builds = mysql_query($sql);
echo mysql_error();

if(mysql_num_rows($builds)==0)
  {
  $xml .= "<message>No warnings or errors today!</message>";
  }

$current_file = "ThisIsMyFirstFile";
while($build_array = mysql_fetch_array($builds))
{
  if($build_array["sourcefile"] != $current_file)
    {
    if($current_file != "ThisIsMyFirstFile")
      {
      $xml .= "</sourcefile>";
      }
    $xml .= "<sourcefile>";
    $xml .= "<name>".$build_array["sourcefile"]."</name>";
    $current_file = $build_array["sourcefile"];
    }

  if($build_array["type"] == 0)
    {
    $xml .= "<error>";
    }
  else
    {
    $xml .= "<warning>";
    }
  $xml .= "<line>".$build_array["sourceline"]."</line>";
  $text = htmlentities($build_array["text"]);
  $textarray = explode("\n",$text);
  foreach($textarray as $text)
    {
    if(strlen($text)>0)
      {
      $xml .= "<text>".$text."</text>";
      }
    }
  $xml .= "<sitename>".$build_array["name"]."</sitename>";  
  $xml .= "<buildname>".$build_array["buildname"]."</buildname>";
 if($build_array["type"] == 0)
    {
    $xml .= "</error>";
    }
  else
    {
    $xml .= "</warning>";
    }
}

if(mysql_num_rows($builds)>0)
  {
  $xml .= "</sourcefile>";
  }



$xml .= "</cdash>";


generate_XSLT($xml, "buildOverview");
?>
