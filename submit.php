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
//error_reporting(0); // disable error reporting

include("cdash/ctestparser.php");
include_once("cdash/common.php");
include_once("cdash/createRSS.php");
include("cdash/sendemail.php");

// Open the database connection
include("cdash/config.php");
require_once("cdash/pdo.php");
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
set_time_limit(0);
$fp = fopen('php://input', 'r');
//$fp = fopen('backup/TestCovLog2.xml', 'r');

$projectname = $_GET["project"];
$projectid = get_project_id($projectname);

// If not a valid project we return
if($projectid == -1)
  {
  echo "Not a valid project";
  add_log('Not a valid project. projectname: ' . $projectname, 'global:submit.php');
  exit();
  }

// We find the daily updates
// If we have php curl we do it asynchronously
if(function_exists("curl_init") == TRUE)
  {
  $currentPort="";

  if($_SERVER['SERVER_PORT']!=80)
    {
    $currentPort=":".$_SERVER['SERVER_PORT'];
    }
    
  /** Server should be local */
  /*
  $serverName = $CDASH_SERVER_NAME;
  if(strlen($serverName) == 0)
    {
    $serverName = $_SERVER['SERVER_NAME'];
    }*/
  
  $serverName = "localhost";  
  
  $prefix =  "http://";
  if($CDASH_USE_HTTPS)
    {
    $prefix =  "https://";
    }
    
  $currentURI =  $prefix.$serverName.$currentPort.$_SERVER['REQUEST_URI']; 
  $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
  
  $request = $currentURI."/cdash/dailyupdatescurl.php?projectid=".$projectid;
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $request);
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 1);
  curl_exec($ch);
  curl_close($ch);
  }
else // synchronously
  {
  include("cdash/dailyupdates.php");
  addDailyChanges($projectid);
  }

// Parse the XML file
$handler = ctest_parse($fp,$projectid);

// Send the emails if necessary
if($handler instanceof TestingHandler || $handler instanceof UpdateHandler )
  {
  sendemail($handler->getSiteAttributes(), $projectid);
  }

// Create the RSS feed
CreateRSSFeed($projectid);
fclose($fp);
?>
