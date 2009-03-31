<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: submit.php 1582 2009-03-19 21:05:00Z jjomier $
  Language:  PHP
  Date:      $Date: 2009-03-19 17:05:00 -0400 (Thu, 19 Mar 2009) $
  Version:   $Revision: 1582 $

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

function do_submit($filehandle, $projectid)
{

  include('cdash/config.php');
  
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
    echo $currentURI;
    echo "<br>";
    $request = $currentURI."/cdash/dailyupdatescurl.php?projectid=".$projectid;
    echo "$request <br>";
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
  
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/submit.php"))
    {
    include("local/submit.php");
    }

// Parse the XML file
  $handler = ctest_parse($filehandle,$projectid);
  
// Send the emails if necessary
  if($handler instanceof UpdateHandler ||
     $handler instanceof TestingHandler ||
     $handler instanceof BuildHandler ||
     $handler instanceof ConfigureHandler)
    {
    sendemail($handler, $projectid);
    }
  
// Create the RSS feed
  CreateRSSFeed($projectid);
  fclose($filehandle);
}
?>
