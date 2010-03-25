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
include('login.php');
include('cdash/version.php');
include_once("models/project.php");
include_once("models/banner.php");
include_once("models/user.php");

if ($session_OK) 
{
@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$userid = $_SESSION['cdash']['loginid'];
// Checks
if(!isset($userid) || !is_numeric($userid))
  {
  echo "Not a valid userid!";
  return;
  }
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Manage Banner</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Banner</menusubtitle>";
  
@$projectid = $_GET["projectid"];
$Project = new Project;
     
// If the projectid is not set and there is only one project we go directly to the page
if(isset($edit) && !isset($projectid))
  {
  $projectids = $Project->GetIds();
  if(count($projectids)==1)
    {
    $projectid = $projectids[0];
    }
  }

$User = new User;
$User->Id = $userid;
$Project->Id = $projectid;
  
$role = $Project->GetUserRole($userid);
     
if($User->IsAdmin()===FALSE && $role<=1)
  {
  echo "You don't have the permissions to access this page";
  return;
  }
    
// If user is admin then we can add a banner for all projects
if($User->IsAdmin() == true)
  {
  $xml .= "<availableproject>";
  $xml .= add_XML_value("id","0");
  $xml .= add_XML_value("name","All");
  if($projectid==0)
    {
    $xml .= add_XML_value("selected","1");
    }
  $xml .= "</availableproject>";
  }
  
  
$sql = "SELECT id,name FROM project";
if($User->IsAdmin() == false)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)"; 
  }
$projects = pdo_query($sql);
while($project_array = pdo_fetch_array($projects))
   {
   $xml .= "<availableproject>";
   $xml .= add_XML_value("id",$project_array['id']);
   $xml .= add_XML_value("name",$project_array['name']);
   if($project_array['id']==$projectid)
      {
      $xml .= add_XML_value("selected","1");
      }
   $xml .= "</availableproject>";
   }
   
$Banner = new Banner();   
$Banner->SetProjectId($projectid);

// If submit has been pressed
@$updateMessage = $_POST["updateMessage"];
if(isset($updateMessage))
  {
  $Banner->SetText($_POST["message"]);
  }


/** We start generating the XML here */
// List the available project
if($projectid>=0)
  {
  $xml .= "<project>";
  $xml .= add_XML_value("id",$Project->Id);
  $xml .= add_XML_value("text",$Banner->GetText());

  if($projectid>0)
    {
    $xml .= add_XML_value("name",$Project->GetName());
    $xml .= add_XML_value("name_encoded",urlencode($Project->GetName()));
    }
  $xml .= add_XML_value("id",$Project->Id);
  $xml .= "</project>";
  }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageBanner");

} // end session OK
?>

