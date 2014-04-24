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


function cdash_unlink($filename)
{
  $success = unlink($filename);

//  $try_count = 1;
//
//  while(file_exists($filename) && $try_count < 60)
//  {
//    usleep(1000000); // == 1000 ms, == 1.0 seconds
//
//    $success = unlink($filename);
//    $try_count++;
//  }

  if (file_exists($filename))
  {
    throw new Exception("file still exists after unlink: $filename");
  }

  if (!$success)
  {
    throw new Exception("unlink returned non-success: $success for $filename");
  }

  return $success;
}


/** Add information to the log file */
function add_log($text, $function, $type=LOG_INFO, $projectid=0, $buildid=0,
                 $resourcetype=0, $resourceid=0)
{
  global $CDASH_LOG_FILE;
  global $CDASH_LOG_FILE_MAXSIZE_MB;
  global $CDASH_LOG_LEVEL;
  
  // Check if we are within the log level
  if($type!= LOG_TESTING && $type<$CDASH_LOG_LEVEL)
    {
    return;
    }
  
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
    $tmplogfile = $logFile.".tmp";
    if(!file_exists($tmplogfile))
      {
      rename($logFile,$tmplogfile); // This should be quick so we can keep logging

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
            cdash_unlink($newfile);
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
            cdash_unlink($newfile);
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
            unset($f);
            gzclose($gz);
            unset($gz);
            }
          }
        }

      // Move the current backup
      if(!function_exists("gzwrite"))
        {
        rename($tmplogfile,$logFile.'.0.txt');
        }
      else
        {
        $gz = gzopen($logFile.'.0.gz','wb');
        $f = fopen($tmplogfile,'rb');
        while($f && !feof($f))
          {
          gzwrite($gz, fread($f, 8192));
          }
        fclose($f);
        unset($f);
        gzclose($gz);
        unset($gz);
        cdash_unlink($tmplogfile);
        }
      } // end tmp file doesn't exist
    } // end log rotation

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

  $logged = error_log($error, 3, $logFile);

  // If there was a problem logging to cdash.log, echo and send it to
  // PHP's system log:
  //
  if (!$logged)
    {
    echo "warning: problem logging error to $logFile\n";
    echo "  $error\n";
    echo "\n";
    echo "attempting to send to PHP's system log now\n";
    echo "\n";

    error_log($error, 0);
    }

  // If we just created the logFile, then give it group write permissions
  // so that command-line invocations of CDash functions can also write to
  // the same log file.
  //
  if (!$log_pre_exists && $logged && file_exists($logFile))
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
