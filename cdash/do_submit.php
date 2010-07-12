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

function do_submit($filehandle, $projectid, $expected_md5='', $do_checksum=true)
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
    
    // Where to send to curl request
    $serverName = "localhost";     
    if(!$CDASH_CURL_REQUEST_LOCALHOST)
      {
      $serverName = $CDASH_SERVER_NAME;
      if(strlen($serverName) == 0)
        {
        $serverName = $_SERVER['SERVER_NAME'];
        }
      }
      
    $prefix =  "http://";
    if($CDASH_USE_HTTPS)
      {
      $prefix =  "https://";
      }
    
    $currentURI =  $prefix.$serverName.$currentPort.$CDASH_CURL_LOCALHOST_PREFIX.$_SERVER['REQUEST_URI']; 
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

  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/submit.php"))
    {
    include("local/submit.php");
    }

  // Parse the XML file
  $handler = ctest_parse($filehandle,$projectid, $expected_md5, $do_checksum);
  //this is the md5 checksum fail case
  if($handler == FALSE)
    {
    //no need to log an error since ctest_parse already did
    return;
    }
  
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

/** Asynchronous submission */
function do_submit_asynchronous($filehandle, $projectid, $expected_md5='')
{
  include('cdash/config.php');
  
  do 
    { 
    $filename = $CDASH_BACKUP_DIRECTORY."/".mt_rand().".xml";
    $fp = @fopen($filename, 'x'); 
    } 
  while(!$fp); 
  fclose($fp);
  
  $outfile = fopen($filename, 'w');

  // Save the file in the backup directory
  while(!feof($filehandle))
    {
    $content = fread($filehandle, 8192);
    if (fwrite($outfile, $content) === FALSE)
      {
      echo "ERROR: Cannot write to file ($filename)";
      add_log("Cannot write to file ($filename)", "do_submit_asynchronous",LOG_ERR);
      fclose($outfile);
      fclose($filehandle);
      return;
      }
    }
  fclose($outfile);
  fclose($filehandle);

  $md5sum = md5_file($filename);
  $md5error = false;
  
  echo "<cdash version=\"$CDASH_VERSION\">\n";
  if($expected_md5 == '' || $expected_md5 == $md5sum)
    {
    echo "  <status>OK</status>\n";
    echo "  <message></message>\n";
    }
  else
    {
    echo "  <status>ERROR</status>\n";
    echo "  <message>Checksum failed for file.  Expected $expected_md5 but got $md5sum.</message>\n";
    $md5error = true;
    }
  echo "  <md5>$md5sum</md5>\n";
  echo "</cdash>\n";

  if($md5error)
    {
    add_log("Checksum failure on file: $filename", "do_submit_asynchronous", LOG_ERR, $projectid);
    return;
    }

  // Insert the filename in the database
  pdo_query("INSERT INTO submission (filename,projectid,status) VALUES ('".$filename."','".$projectid."','0')");
  
  // We find the daily updates
  // If we have php curl we do it asynchronously
  if(function_exists("curl_init") == TRUE)
    {
    $currentPort="";
    if($_SERVER['SERVER_PORT']!=80)
      {
      $currentPort=":".$_SERVER['SERVER_PORT'];
      }
    
    // Where to send to curl request
    $serverName = "localhost";     
    if(!$CDASH_CURL_REQUEST_LOCALHOST)
      {
      $serverName = $CDASH_SERVER_NAME;
      if(strlen($serverName) == 0)
        {
        $serverName = $_SERVER['SERVER_NAME'];
        }
      }
      
    $prefix =  "http://";
    if($CDASH_USE_HTTPS)
      {
      $prefix =  "https://";
      }
    
    $currentURI =  $prefix.$serverName.$currentPort.$CDASH_CURL_LOCALHOST_PREFIX.$_SERVER['REQUEST_URI']; 
    $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
    $request = $currentURI."/cdash/processsubmissions.php?projectid=".$projectid;

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
    add_log("do_submit_asynchronous","Cannot submit asynchronously",LOG_ERR);
    }
}

?>
