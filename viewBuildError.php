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
  
$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
$date = date("Y-m-d", strtotime($build_array["starttime"]));

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);  
  $projectname = $project_array["name"];  
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);
 
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
 $xml .= add_XML_value("starttime",date("Y-m-d H:i:s T",strtotime($build_array["starttime"]."UTC")));
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= "</build>";
  
  @$type = $_GET["type"];
  if(!isset($type))
    {
    $type = 0;
    }
  // Set the error
  if($type == 0)
    {
    $xml .= add_XML_value("errortypename","Error");
    $xml .= add_XML_value("nonerrortypename","Warning");
    $xml .= add_XML_value("nonerrortype","1"); 
    }
  else
    {
    $xml .= add_XML_value("errortypename","Warning");
    $xml .= add_XML_value("nonerrortypename","Error");
    $xml .= add_XML_value("nonerrortype","0"); 
    } 
  
  $xml .= "<errors>";
  
  $errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='$type' ORDER BY logline ASC");
  while($error_array = pdo_fetch_array($errors))
    {
    $xml .= "<error>";
    $xml .= add_XML_value("logline",$error_array["logline"]);
    $xml .= add_XML_value("text",$error_array["text"]);
    $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
    $xml .= add_XML_value("sourceline",$error_array["sourceline"]);
    $xml .= add_XML_value("precontext",$error_array["precontext"]);
    $xml .= add_XML_value("postcontext",$error_array["postcontext"]);
  
    $projectCvsUrl = $project_array["cvsurl"];
    $file = basename($error_array["sourcefile"]);
    $directory = dirname($error_array["sourcefile"]);  
    $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file);


    $xml .= add_XML_value("cvsurl",$cvsurl);
    $xml .= "</error>";
    }

  $xml .= "</errors>";
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewBuildError");
?>
