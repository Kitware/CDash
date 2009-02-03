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
include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");
include("cdash/version.php");
$noforcelogin = 1;
include('login.php');

@$projectname = $_GET["project"];
if(!isset($projectname) || strlen($projectname)==0)
  {
  die("Error: project not specified<br>\n");
  }
@$date = $_GET["date"];
 
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>".$projectname." : Build Overview</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

// Get some information about the specified project
$project = pdo_query("SELECT id, nightlytime FROM project WHERE name = '$projectname'");
if(!$project_array = pdo_fetch_array($project))
  {
  die("Error:  project $projectname not found<br>\n");
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);

$projectid = $project_array["id"];
$nightlytime = $project_array["nightlytime"];

// We select the builds
list ($previousdate, $currentstarttime, $nextdate,$today) = get_dates($date,$nightlytime);
$xml .= "<menu>";
$xml .= add_XML_value("previous","buildOverview.php?project=".$projectname."&date=".$previousdate);
if($date!="" && date(FMT_DATE, $currentstarttime)!=date(FMT_DATE))
  {
  $xml .= add_XML_value("next","buildOverview.php?project=".$projectname."&date=".$nextdate);
  }
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= add_XML_value("current","buildOverview.php?project=".$projectname."&date=");

$xml .= add_XML_value("back","index.php?project=".$projectname."&date=".$today);
$xml .= "</menu>";

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

// Check the builds
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime+3600*24;

$beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);                                                      
  

$groupSelectionSQL = "";
if($groupSelection>0)
  {
  $groupSelectionSQL = " AND b2g.groupid='$groupSelection' ";
  }  
  
$sql =  "SELECT s.name,b.name as buildname,be.type,be.sourcefile,be.sourceline,be.text
                         FROM build AS b,builderror as be,site AS s, build2group as b2g
                         WHERE b.starttime<'$end_UTCDate' AND b.starttime>'$beginning_UTCDate'
                         AND b.projectid='$projectid' AND be.buildid=b.id 
                         AND s.id=b.siteid AND b2g.buildid=b.id
                         ".$groupSelectionSQL."ORDER BY be.sourcefile ASC,be.type ASC,be.sourceline ASC";
                           
$builds = pdo_query($sql);
echo pdo_error();

if(pdo_num_rows($builds)==0)
  {
  $xml .= "<message>No warnings or errors today!</message>";
  }

$current_file = "ThisIsMyFirstFile";
while($build_array = pdo_fetch_array($builds))
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

if(pdo_num_rows($builds)>0)
  {
  $xml .= "</sourcefile>";
  }
$xml .= "</cdash>";


generate_XSLT($xml, "buildOverview");
?>
