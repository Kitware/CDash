<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.
  Copyright (c) 2012 Volkan Gezer <volkangezer@gmail.com>
=========================================================================*/
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include_once("models/project.php");

if ($session_OK)
{
$projectid = $_REQUEST["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

// Checks
if(!isset($projectid) || !is_numeric($projectid))
  {
  echo "Not a valid projectid!";
  return;
  }

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];
  $nightlytime = $project_array["nightlytime"];
  }

$submit=$_POST['submit'];
$nameN=$_POST['nameN'];
$showTN=$_POST['showTN'];
$showSN=$_POST['showSN'];

$id=$_POST['id'];
$name=$_POST['name'];

// Start operation if it is submitted
if($submit=="Save")
  {
  if($nameN)
    {
    pdo_query("INSERT INTO measurement (projectid,name,testpage,summarypage) VALUES ('$projectid','$nameN','$showTN','$showSN')"); // only write a new entry if new field is filled
    }
  $i=0;

  if(count($_POST['name']))
    {
    foreach($name as $newName)
      { // everytime update all test attributes
      $showT=$_POST["showT"];
      $showS=$_POST["showS"];
      if($showT[$id[$i]]=='') $showT[$id[$i]]=0;
      if($showS[$id[$i]]=='') $showS[$id[$i]]=0;
      pdo_query("UPDATE measurement SET name='$newName', testpage='".$showT[$id[$i]]."', summarypage='".$showS[$id[$i]]."' WHERE id='".$id[$i]."'");
      $i++;
      }
    }
  }
$selection=$_POST['select'];
if($_POST['del'] && count($selection)>0)
  { // if user chose any named measurement delete them
  foreach($selection as $del)
    {
    pdo_query("DELETE FROM measurement WHERE id='$del'");
    }
  }

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - ".$projectname." Measurements</title>";
$xml .= "<menutitle>".$projectname."</menutitle>";
$xml .= "<menusubtitle>Measurements</menusubtitle>";

if($projectid>0)
  {
  $Project = new Project;
  $Project->Id = $projectid;
  $xml .= "<project>";
  $xml .= add_XML_value("id",$projectid);
  $xml .= add_XML_value("name",$Project->GetName());
  $xml .= add_XML_value("name_encoded",urlencode($Project->GetName()));

  $xml .= "</project>";
  }

// Menu
$xml .= "<menu>";

$nightlytime = get_project_property($projectname,"nightlytime");
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime));

  $xml .= add_XML_value("noprevious","1");
  $xml .= add_XML_value("nonext","1");
  $xml .= "</menu>";
   {
   $xml .= "<user>";
   $userid = $_SESSION['cdash']['loginid'];
   $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
   $user_array = pdo_fetch_array($user);
   $xml .= add_XML_value("id",$userid);
   $xml .= add_XML_value("admin",$user_array["admin"]);
   $xml .= "</user>";
   }

//get any measurements associated with this test
$xml .= "<measurements>";
$query = "SELECT id,name,testpage,summarypage FROM measurement WHERE projectid='$projectid' ORDER BY name ASC";
$result = pdo_query($query);
while($row = pdo_fetch_array($result))
  {
  $xml .= "<measurement>";
  $xml .= add_XML_value("id", $row["id"]);
  $xml .= add_XML_value("name", $row["name"]);
  $xml .= add_XML_value("showT", $row["testpage"]);
  $xml .= add_XML_value("showS", $row["summarypage"]);
  $xml .= "</measurement>";
  }
$xml .= "</measurements>";
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageMeasurements");
} // end if session
?>