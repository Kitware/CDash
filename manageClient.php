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
require_once("cdash/pdo.php");
include('login.php');
include_once('cdash/common.php');
include("cdash/version.php");

include_once('models/project.php');
include_once ("models/clientsite.php");
include_once ("models/clientjob.php");
include_once ("models/clientos.php");
require_once ("models/project.php");
require_once ("models/constants.php");
require_once ("models/user.php");

if ($session_OK) 
  {
  if(!$CDASH_MANAGE_CLIENTS)
    {
    echo "CDash has not been setup to allow client management";
    exit();
    }   
  
  $userid = $_SESSION['cdash']['loginid'];
    
  /** If we should remove a job */  
  if(isset($_GET['removejob']))
    { 
    $User = new User();
    $User->Id = $userid;
    $ClientJob = new ClientJob();
    $ClientJob->Id = $_GET['removejob'];
    
    if(!$User->IsAdmin() || $ClientJob->GetOwner()!=$userid)
      {
      echo "You cannot access this job";
      exit();
      }
    $ClientJob->Remove();
    echo "<script language=\"javascript\">window.location='user.php'</script>";
    } // end remove job
  
  if(!isset($_GET['projectid']))
    {
    echo "Projectid is not set";
    exit();
    }
  
  $xml = "<cdash>";
  $xml .= add_XML_value("cssfile",$CDASH_CSS_FILE);
  $xml .= add_XML_value("version",$CDASH_VERSION);
  $xml .= add_XML_value("manageclient",$CDASH_MANAGE_CLIENTS);
  
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);
  $xml .= add_XML_value("title","CDash - Manage Clients");
  $xml .= add_XML_value("menutitle","CDash");
  $xml .= add_XML_value("menusubtitle","Clients");
  
  $xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
  $xml .= "<date>".date("r")."</date>";

  $xml .= "<user>";
  $userid = $_SESSION['cdash']['loginid'];
  $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
  $user_array = pdo_fetch_array($user);
  $xml .= add_XML_value("id", $userid);
  $xml .= add_XML_value("admin", $user_array["admin"]);
  $xml .= "</user>";
  
  $Project = new Project();
  $Project->Id = $_GET['projectid'];
  $repositories = $Project->GetRepositories();
  $xml .= '<project>';
  $xml .= add_XML_value("name", $Project->getName());
  $xml .= add_XML_value("name_encoded", urlencode($Project->getName()));
  $xml .= add_XML_value("id", $Project->Id);
  foreach ($repositories as $repository)
    {
    $xml .= '<repository>';
    $xml .= add_XML_value("url", $repository['url']);
    $xml .= '</repository>';
    }
  $xml .= '</project>';
  
  // Send the OS version 
  $clientOS = new ClientOS();
  $osids = $clientOS->getAll();
  
  foreach($osids as $osid)
    {
    $xml .= '<system>';
    $clientOS->Id = $osid;
    $xml .= add_XML_value("os",$clientOS->GetName()."-".$clientOS->GetVersion()."-".$clientOS->GetBits()."bits");
    $xml .= add_XML_value("osid",$osid);
    $xml .= '</system>';  
    }
  $xml .= "</cdash>";
  
  // Schedule the task
  if(!empty($_POST['submit']))
    {
    $clientJob = new ClientJob();
    $clientJob->OsId = $_POST['system'];
    $clientJob->UserId = $userid;
    $clientJob->ProjectId = $Project->Id;
    $clientJob->ScheduleDate = date("Y-m-d h:i:s");
    $clientJob->Type = $_POST['type'];
    $clientJob->RepeatTime = $_POST['interval'];
    $clientJob->Status = CDASH_JOB_SCHEDULED; // scheduled
    $clientJob->CMakeId = $_POST['cmake'];
    $clientJob->CompilerId = $_POST['compiler'];
    $clientJob->Save();
    
    // Add the libraries
    if(isset($_POST['library']))
      {
      foreach($_POST['library'] as $libraryid)
        {
        $clientJob->AddLibrary($libraryid);
        }
      }
      
    // Add the toolkit
    if(isset($_POST['toolkitconfiguration']))
      {
      foreach($_POST['toolkitconfiguration'] as $toolkitconfigurationid)
        {
        $clientJob->AddToolkitConfiguration($toolkitconfigurationid);
        }
      }  
    echo "<script language=\"javascript\">window.location='user.php'</script>";
    }
  generate_XSLT($xml, "manageClient", true);
  } // end session is OK
?>
