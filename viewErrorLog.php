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
@$projectid = $_GET["projectid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
 
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
    
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);  
  $projectname = $project_array["name"];  
  }

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>Error Log - ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml(get_project_name($projectid),$date);
 
// Get the errors
$query = pdo_query("SELECT resourcetype,date,resourceid,description,type,buildid 
                    FROM errorlog WHERE projectid=".qnum($projectid)." AND date>'".$date."' ORDER BY date DESC");
while($query_array = pdo_fetch_array($query))
  {
  $xml .= "<error>";
  $xml .= add_XML_value("date",$query_array["date"]);
  $xml .= add_XML_value("resourceid",$query_array["resourceid"]);
  $xml .= add_XML_value("resourcetype",$query_array["resourcetype"]);
  $xml .= add_XML_value("description",$query_array["description"]);
  $xml .= add_XML_value("type",$query_array["type"]);
  $xml .= add_XML_value("buildid",$query_array["buildid"]);
  $xml .= "</error>";
  }
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewErrorLog");
?>
