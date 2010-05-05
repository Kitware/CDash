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

// Checks if the project id is set
if(!isset($projectid) || !is_numeric($projectid))
  {
  checkUserPolicy(@$_SESSION['cdash']['loginid'],0);
  }
else
  {
  checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
  } 
  
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

if($projectid)
  {
  $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
  if(pdo_num_rows($project)>0)
    {
    $project_array = pdo_fetch_array($project);  
    $projectname = $project_array["name"];  
    }
  }
else
  {
  $projectname = 'Global';
  }
$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>Error Log - ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

if($projectid)
  {
  $xml .= get_cdash_dashboard_xml(get_project_name($projectid),$date);
 
  // Get the errors
  $query = pdo_query("SELECT resourcetype,date,resourceid,description,type,buildid,projectid
                     FROM errorlog WHERE projectid=".qnum($projectid)." AND date>'".$date."' ORDER BY date DESC");
  }
else
  { 
  $query = pdo_query("SELECT resourcetype,date,resourceid,errorlog.description,type,buildid,projectid,project.name AS projectname
                     FROM errorlog LEFT JOIN project ON (project.id=errorlog.projectid) WHERE date>'".$date."' ORDER BY date DESC");
  echo pdo_error();
  }

while($query_array = pdo_fetch_array($query))
  {
  $xml .= "<error>";
  $xml .= add_XML_value("date",$query_array["date"]);
  $xml .= add_XML_value("resourceid",$query_array["resourceid"]);
  $xml .= add_XML_value("resourcetype",$query_array["resourcetype"]);
  $xml .= add_XML_value("description",$query_array["description"]);
  $xml .= add_XML_value("type",$query_array["type"]);
  $xml .= add_XML_value("buildid",$query_array["buildid"]);
  $xml .= add_XML_value("projectid",$query_array["projectid"]);
  
  if(isset($query_array["projectname"]))
    {
    $xml .= add_XML_value("projectname",$query_array["projectname"]);  
    }
  
  $xml .= "</error>";
  }
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewErrorLog");
?>
