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
require_once("cdash/defines.php");
require_once("cdash/pdocore.php");
require_once("models/errorlog.php");

/** Add information to the log file */
function add_log($text, $function, $type=LOG_INFO, $projectid=0, $buildid=0,
                 $resourcetype=0, $resourceid=0)
{
  global $CDASH_LOG_FILE;
  global $CDASH_LOG_FILE_MAXSIZE_MB;
  $logFile = $CDASH_LOG_FILE;
  if($buildid == 0 && isset($GLOBALS['PHP_ERROR_BUILD_ID'])) //use the global build id as a default if it's set
    {
    $buildid = $GLOBALS['PHP_ERROR_BUILD_ID'];
    }

  if(!file_exists(dirname($logFile)))
    {
    $paths = explode(PATH_SEPARATOR, get_include_path());
    // Search the include path for the log file
    foreach($paths as $path)
      {
      if(file_exists(dirname("$path/$CDASH_LOG_FILE")))
        {
        $logFile = "$path/$CDASH_LOG_FILE";
        break;
        }
      }
    }

  if(strlen($text)==0)
    {
    return;
    }

  // If the size of the log file is bigger than 10 times the allocated memory
  // we rotate
  $maxlogsize = $CDASH_LOG_FILE_MAXSIZE_MB*1024*1024/10.0;
  if(file_exists($logFile) && filesize($logFile)>$maxlogsize)
    {
    for($i=9;$i>=0;$i--)
      {
      // If we don't have compression we just rename the files
      if(!function_exists("gzwrite"))
        {
        $currentfile = $logFile.".".$i.".txt";
        $j = $i+1;
        $newfile = $logFile.".".$j.".txt";
        if(file_exists($newfile))
          {
          unlink($newfile);
          }
        if(file_exists($currentfile))
          {
          rename($currentfile,$newfile);
          }
        }
      else
        {
        $currentfile = $logFile.".".$i.".gz";
        $j = $i+1;
        $newfile = $logFile.".".$j.".gz";
        if(file_exists($newfile))
          {
          unlink($newfile);
          }
        if(file_exists($currentfile))
          {
          $gz = gzopen($newfile,'wb');
          $f = fopen($currentfile,'rb');
          while($f && !feof($f))
            {
            gzwrite($gz, fread($f, 8192));
            }
          fclose($f);
          gzclose($gz);
          }
        }
      }

    // Move the current backup
    if(!function_exists("gzwrite"))
      {
      rename($logFile,$logFile.'.0.txt');
      }
    else
      {
      $gz = gzopen($logFile.'.0.gz','wb');
      $f = fopen($logFile,'rb');
      while($f && !feof($f))
        {
        gzwrite($gz, fread($f, 8192));
        }
      fclose($f);
      gzclose($gz);
      }
    }

  $error = "";
  if($type != LOG_TESTING)
    {
    $error = "[".date(FMT_DATETIME)."]";
    }

  // This is parsed by the testing
  switch($type)
    {
    case LOG_INFO: $error.="[INFO]"; break;
    case LOG_WARNING: $error.="[WARNING]"; break;
    case LOG_ERR: $error.="[ERROR]"; break;
    case LOG_TESTING: $error.="[TESTING]";break;
    }
  $error .= "[pid=".getmypid()."]";
  $error .= "(".$function."): ".$text."\n";

  $log_pre_exists = file_exists($logFile);

  error_log($error, 3, $logFile);

  // If we just created the logFile, then give it group write permissions
  // so that command-line invocations of CDash functions can also write to
  // the same log file.
  //
  if (!$log_pre_exists)
    {
    chmod($logFile, 0664);
    }

  // Insert in the database
  if($type == LOG_WARNING || $type==LOG_ERR)
    {
    $ErrorLog = new ErrorLog;
    $ErrorLog->ProjectId = $projectid;
    $ErrorLog->BuildId = $buildid;
    switch($type)
      {
      // case LOG_INFO: $ErrorLog->Type = 6; break;
      case LOG_WARNING: $ErrorLog->Type = 5; break;
      case LOG_ERR: $ErrorLog->Type = 4; break;
      }
    $ErrorLog->Description = "(".$function."): ".$text;
    $ErrorLog->ResourceType = $resourcetype;
    $ErrorLog->ResourceId = $resourceid;

    $ErrorLog->Insert();

    // Clean the log more than 10 days
    $ErrorLog->Clean(10);
    }
}


function begin_timer($context)
{
  global $cdash_timer_stack;
  if (!isset($cdash_timer_stack))
    {
    $cdash_timer_stack = array();
    }

  $timer_entry = array();
  $timer_entry[] = $context;
  $timer_entry[] = microtime_float();

  $cdash_timer_stack[] = $timer_entry;
}


function end_timer($context, $threshold = -0.001)
{
  global $cdash_timer_stack;
  if (!isset($cdash_timer_stack))
    {
    trigger_error(
      'end_timer called before begin_timer',
      E_USER_WARNING);
    }

  $end_time = microtime_float();

  $n = count($cdash_timer_stack)-1;
  $timer_entry = $cdash_timer_stack[$n];
  $begin_context = $timer_entry[0];
  $begin_time = $timer_entry[1];

  if ($context != $begin_context)
    {
    trigger_error(
      'end_timer called with different context than begin_timer',
      E_USER_WARNING);
    }

  array_pop($cdash_timer_stack);

  $text = '';
  for($i = 0; $i < $n; ++$i)
    {
    $text .= '  ';
    }

  $delta = $end_time - $begin_time;

  $text .= $context . ', ' . round($delta, 3) . ' seconds';

  if ($delta > $threshold)
    {
    add_log($text, "end_timer");
    }
}
?>
