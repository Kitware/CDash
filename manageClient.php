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
include_once ("models/clientjobschedule.php");
include_once ("models/clientos.php");
include_once ("models/clientcmake.php");
include_once ("models/clientcompiler.php");
include_once ("models/clientlibrary.php");
include_once ("models/clienttoolkit.php");
include_once ("models/clienttoolkitversion.php");
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
  if(isset($_GET['removeschedule']))
    { 
    $User = new User();
    $User->Id = $userid;
    $ClientJobSchedule = new ClientJobSchedule();
    $ClientJobSchedule->Id = $_GET['removeschedule'];
    
    if(!$User->IsAdmin() || $ClientJobSchedule->GetOwner()!=$userid)
      {
      echo "You cannot access this job";
      exit();
      }
    $ClientJobSchedule->Remove();
    echo "<script language=\"javascript\">window.location='user.php'</script>";
    } // end remove job
  
  if(!isset($_GET['projectid']) && !isset($_GET['scheduleid']))
    {
    echo "Projectid or Schedule id not set";
    exit();
    }

  if(isset($_GET['projectid']))
    {
    $projectid = $_GET['projectid'];
    }   
  else
    {
    $scheduleid = $_GET['scheduleid'];
    $ClientJobSchedule = new ClientJobSchedule();  
    $ClientJobSchedule->Id = $scheduleid;
    $projectid = $ClientJobSchedule->GetProjectId();
    }
      
  $xml = "<cdash>";
  $xml .= add_XML_value("cssfile",$CDASH_CSS_FILE);
  $xml .= add_XML_value("version",$CDASH_VERSION);
  $xml .= add_XML_value("manageclient",$CDASH_MANAGE_CLIENTS);
  
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);
  $xml .= add_XML_value("title","CDash - Schedule Build");
  $xml .= add_XML_value("menutitle","CDash");
  $xml .= add_XML_value("menusubtitle","Schedule Build");
  
  $xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
  $xml .= "<date>".date("r")."</date>";
  $xml .= "<backurl>user.php</backurl>";
  
  $xml .= "<user>";
  $userid = $_SESSION['cdash']['loginid'];
  $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
  $user_array = pdo_fetch_array($user);
  $xml .= add_XML_value("id", $userid);
  $xml .= add_XML_value("admin", $user_array["admin"]);
  $xml .= "</user>";
  
  if(isset($scheduleid))
    {
    $xml .= add_XML_value("edit","1");
    $xml .= add_XML_value("startdate",$ClientJobSchedule->GetStartDate());  
    $xml .= add_XML_value("enddate",$ClientJobSchedule->GetEndDate());  
    $xml .= add_XML_value("starttime",$ClientJobSchedule->GetStartTime());    
    $xml .= add_XML_value("type",$ClientJobSchedule->GetType());
    $xml .= add_XML_value("repeat",$ClientJobSchedule->GetRepeatTime());
    $xml .= add_XML_value("cmakecache",$ClientJobSchedule->GetCMakeCache());
    $xml .= add_XML_value("enable",$ClientJobSchedule->GetEnable());
    $libraries = $ClientJobSchedule->GetLibraries();
    $toolkits = $ClientJobSchedule->GetToolkitConfigurations();
    $cmakes = $ClientJobSchedule->GetCMakes();
    $compilers = $ClientJobSchedule->GetCompilers();
    $sites = $ClientJobSchedule->GetSites();
    $systems = $ClientJobSchedule->GetSystems();
    }
  else
    {
    $xml .= add_XML_value("startdate",date("Y-m-d H:i:s"));  
    $xml .= add_XML_value("enddate",date("1980-01-01 00:00:00"));  
    $xml .= add_XML_value("starttime","21:00:00");    
    $xml .= add_XML_value("type","0"); // experimental   
    $xml .= add_XML_value("cmakecache","");
    $xml .= add_XML_value("repeat","0");
    $xml .= add_XML_value("enable","1");
    }  
    
  $Project = new Project();
  $Project->Id = $projectid;
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
  
  // OS versions
  $clientOS = new ClientOS();
  $osids = $clientOS->getAll();
  foreach($osids as $osid)
    {
    $xml .= '<os>';
    $clientOS->Id = $osid;
    $xml .= add_XML_value("name",$clientOS->GetName()."-".$clientOS->GetVersion()."-".$clientOS->GetBits()."bits");
    $xml .= add_XML_value("id",$osid);
    if(isset($systems) && array_search($osid,$systems) !== false)
      {
      $xml .= add_XML_value("selected","1");  
      }
    $xml .= '</os>';  
    }
 
  // Compiler versions
  $Compiler = new ClientCompiler();
  $compilerids = $Compiler->getAll();
  foreach($compilerids as $compilerid)
    {
    $xml .= '<compiler>';
    $Compiler->Id = $compilerid;
    $xml .= add_XML_value("name",$Compiler->GetName()."-".$Compiler->GetVersion());
    $xml .= add_XML_value("id",$compilerid);
    if(isset($compilers) && array_search($compilerid,$compilers) !== false)
      {
      $xml .= add_XML_value("selected","1");  
      }
    $xml .= '</compiler>';  
    } 

  // CMake versions
  $CMake = new ClientCMake();
  $cmakeids = $CMake->getAll();
  foreach($cmakeids as $cmakeid)
    {
    $xml .= '<cmake>';
    $CMake->Id = $cmakeid;
    $xml .= add_XML_value("version",$CMake->GetVersion());
    $xml .= add_XML_value("id",$cmakeid);
    if(isset($cmakes) && array_search($cmakeid,$cmakes) !== false)
      {
      $xml .= add_XML_value("selected","1");  
      }
    $xml .= '</cmake>';  
    } 

  // Sites 
  $Site = new ClientSite();
  $siteids = $Site->getAll();
  foreach($siteids as $siteid)
    {
    $xml .= '<site>';
    $Site->Id = $siteid;
    $xml .= add_XML_value("name",$Site->GetName()."-".$Site->GetSystemName());
    $xml .= add_XML_value("id",$siteid);
    if(isset($sites) && array_search($siteid,$sites) !== false)
      {
      $xml .= add_XML_value("selected","1");  
      }
    $xml .= '</site>';  
    }   
  
  // Libraries 
  $Library = new ClientLibrary();
  $libraryids = $Library->getAll();
  
  foreach($libraryids as $libraryid)
    {
    $xml .= '<library>';
    $Library->Id = $libraryid;
    $xml .= add_XML_value("name",$Library->GetName()."-".$Library->GetVersion());
    $xml .= add_XML_value("id",$libraryid);
    if(isset($libraries) && array_search($libraryid,$libraries) !== false)
      {
      $xml .= add_XML_value("selected","1");  
      }
    $xml .= '</library>';  
    }     
    
  // Toolkits 
  $Toolkit = new ClientToolkit();
  $toolkitids = $Toolkit->getAll();
  foreach($toolkitids as $toolkitid)
    {
    $Toolkit->Id = $toolkitid;
    $versionids = $Toolkit->GetVersions();
    foreach($versionids as $versionid)
      { 
      $ToolkitVersion = new ClientToolkitVersion();
      $ToolkitVersion->Id = $versionid;
      $configurationids = $ToolkitVersion->GetConfigurations();
      foreach($configurationids as $configurationid)
        { 
        $ToolkitConfiguration = new ClientToolkitConfigure();
        $ToolkitConfiguration->Id = $configurationid;
      
        $xml .= '<toolkit>';
        $Toolkit->Id = $toolkitid;
        $xml .= add_XML_value("name",$Toolkit->GetName()."-".$ToolkitVersion->GetName()."-".$ToolkitConfiguration->GetName());
        $xml .= add_XML_value("id",$configurationid);
        if(isset($toolkits) && array_search($toolkitid,$toolkits) !== false)
          {
          $xml .= add_XML_value("selected","1");  
          }
        $xml .= '</toolkit>';
        }
      }
    }    
  $xml .= "</cdash>";
  
  // Schedule the build
  if(!empty($_POST['submit']) || !empty($_POST['update']))
    {
    $clientJobSchedule = new ClientJobSchedule();
    $clientJobSchedule->UserId = $userid;
    $clientJobSchedule->ProjectId = $Project->Id;
    if(isset($_POST['enable']))
      {
      $clientJobSchedule->Enable = 1;
      }
    $clientJobSchedule->StartDate = $_POST['startdate'];
    if(empty($clientJobSchedule->StartDate))
      {
      $clientJobSchedule->StartDate = date("Y-m-d H:i:s");  
      }
    $clientJobSchedule->EndDate = $_POST['enddate'];
    if(empty($clientJobSchedule->EndDate))
      {
      $clientJobSchedule->EndDate = '1980-01-01 00:00:00';  
      }
    $clientJobSchedule->StartTime = $_POST['starttime'];
    $clientJobSchedule->Type = $_POST['type'];
    $clientJobSchedule->RepeatTime = $_POST['repeat'];
    $clientJobSchedule->CMakeCache = $_POST['cmakecache'];
    $clientJobSchedule->Enable = 1;
    if(!empty($_POST['update']))
      {
      $clientJobSchedule->Id = $scheduleid;
      }
    $clientJobSchedule->Save();
    
    // Remove everything and add them back in
    $clientJobSchedule->RemoveDependencies();

    // Add the os
    if(isset($_POST['system']))
      {
      foreach($_POST['system'] as $osid)
        {
        $clientJobSchedule->AddOS($osid);
        }
      }
      
    // Add the compiler
    if(isset($_POST['compiler']))
      {
      foreach($_POST['compiler'] as $compilerid)
        {
        $clientJobSchedule->AddCompiler($compilerid);
        }
      }
    
    // Add the cmake
    if(isset($_POST['cmake']))
      {
      foreach($_POST['cmake'] as $cmakeid)
        {
        $clientJobSchedule->AddCMake($cmakeid);
        }
      }
      
    // Add the site
    if(isset($_POST['site']))
      {
      foreach($_POST['site'] as $siteid)
        {
        $clientJobSchedule->AddSite($siteid);
        }
      }
       
    // Add the libraries
    if(isset($_POST['library']))
      {
      foreach($_POST['library'] as $libraryid)
        {
        $clientJobSchedule->AddLibrary($libraryid);
        }
      }
      
    // Add the toolkit
    if(isset($_POST['toolkitconfiguration']))
      {
      foreach($_POST['toolkitconfiguration'] as $toolkitconfigurationid)
        {
        $clientJobSchedule->AddToolkitConfiguration($toolkitconfigurationid);
        }
      }  
    echo "<script language=\"javascript\">window.location='user.php'</script>";
    }
  generate_XSLT($xml, "manageClient", true);
  } // end session is OK
?>
