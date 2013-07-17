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
include_once("models/subproject.php");
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

$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Manage Subproject</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Subprojects</menusubtitle>";

@$projectid = $_GET["projectid"];
if ($projectid != NULL)
  {
    $projectid = pdo_real_escape_numeric($projectid);
  }

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
if(!isset($projectid))
  {
  $projectid = -1;
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

$Subproject = new Subproject();   
$Subproject->SetProjectId($projectid);

// If submit has been pressed
@$addSubproject = $_POST["addSubproject"];
if(isset($addSubproject))
  {
  $Subproject->Name = $_POST["newsubproject"];
  $Subproject->Save();
  }

// If delete is requested
if(isset($_GET["delete"]))
  {
  $Subproject->Id = pdo_real_escape_numeric($_GET["delete"]);
  $Subproject->Delete();
  }

// If we should remove a dependency
if(isset($_GET["removeDependency"]))
  {
  $Subproject->Id = pdo_real_escape_numeric($_GET["dependency"]);
  $Subproject->RemoveDependency(pdo_real_escape_numeric($_GET["removeDependency"]));
  }


// If we should add a dependency
if(isset($_POST["addDependency"]))
  {
  $Subproject->Id = $_POST["dependencyid"];
  $Subproject->AddDependency($_POST["dependency_selection_".$Subproject->Id]);
  }

/** We start generating the XML here */
// List the available project
if($projectid>=0)
  {
  $xml .= "<project>";
  $xml .= add_XML_value("id",$Project->Id);
  $xml .= add_XML_value("name_encoded",urlencode($Project->GetName()));
  
  if($projectid>0)
    {
    $xml .= add_XML_value("name",$Project->GetName());
    
    $subprojectids = $Project->GetSubProjects();
    foreach($subprojectids as $subprojectid)
      {
      $SubProject1 = new SubProject();
      $SubProject1->Id = $subprojectid;
      $xml .= "<subproject>";
      $xml .= add_XML_value("id",$subprojectid);
      $xml .= add_XML_value("name",$SubProject1->GetName());
      
      $dependencies = $SubProject1->GetDependencies();
      
      foreach($dependencies as $dependency)
        {
        $Dependency = new SubProject();
        $Dependency->Id = $dependency;
        $xml .= "<dependency>";
        $xml .= add_XML_value("id",$dependency);
        $xml .= add_XML_value("name",$Dependency->GetName());
        $xml .= "</dependency>";
        }
      
      foreach($subprojectids as $dependency)
        {
        if($dependency == $subprojectid 
           || in_array($dependency,$dependencies)
           )
           {
           continue;
           }
        $Dependency = new SubProject();
        $Dependency->Id = $dependency;
        $xml .= "<availabledependency>";
        $xml .= add_XML_value("id",$dependency);
        $xml .= add_XML_value("name",$Dependency->GetName());
        $xml .= "</availabledependency>";
        }
      $xml .= "</subproject>";
      }
    } // end projectid > 0
  $xml .= add_XML_value("id",$Project->Id);
  $xml .= "</project>";
  }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageSubproject");

} // end session OK
?>

