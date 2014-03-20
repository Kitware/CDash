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

require_once 'xml_handlers/build_handler.php';
require_once 'xml_handlers/configure_handler.php';
require_once 'xml_handlers/testing_handler.php';
require_once 'xml_handlers/update_handler.php';
require_once 'xml_handlers/coverage_handler.php';
require_once 'xml_handlers/coverage_log_handler.php';
require_once 'xml_handlers/note_handler.php';
require_once 'xml_handlers/dynamic_analysis_handler.php';
require_once 'xml_handlers/project_handler.php';
require_once 'xml_handlers/upload_handler.php';
require_once 'xml_handlers/testing_nunit_handler.php';
require_once 'xml_handlers/testing_junit_handler.php';
require_once 'xml_handlers/coverage_junit_handler.php';

/** Main function to parse the incoming xml from ctest */
function ctest_parse($filehandler, $projectid, $expected_md5='', $do_checksum=true,
                     $scheduleid=0)
{
  include 'cdash/config.php';
  require_once 'cdash/common.php';
  require_once 'models/project.php';
  include 'cdash/version.php';

  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
    {
    require_once("local/ctestparser.php");
    $localParser = new LocalParser();
    $localParser->SetProjectId($projectid);
    $localParser->BufferSizeMB =8192/(1024*1024);
    }

  $content = fread($filehandler, 8192);
  $handler = null;
  $parser = xml_parser_create();
  $file = "";

  if(preg_match('/<Update/', $content)) // Should be first otherwise confused with Build
    {
    $handler = new UpdateHandler($projectid, $scheduleid);
    $file = "Update";
    }
  else if(preg_match('/<Build/', $content))
    {
    $handler = new BuildHandler($projectid, $scheduleid);
    $file = "Build";
    }
  else if(preg_match('/<Configure/', $content))
    {
    $handler = new ConfigureHandler($projectid, $scheduleid);
    $file = "Configure";
    }
  else if(preg_match('/<Testing/', $content))
    {
    $handler = new TestingHandler($projectid, $scheduleid);
    $file = "Test";
    }
  else if(preg_match('/<CoverageLog/', $content)) // Should be before coverage
    {
    $handler = new CoverageLogHandler($projectid, $scheduleid);
    $file = "CoverageLog";
    }
  else if(preg_match('/<Coverage/', $content))
    {
    $handler = new CoverageHandler($projectid, $scheduleid);
    $file = "Coverage";
    }
  else if(preg_match('/<report/', $content))
    {
    $handler = new CoverageJUnitHandler($projectid, $scheduleid);
    $file = "Coverage";
    }
  else if(preg_match('/<Notes/', $content))
    {
    $handler = new NoteHandler($projectid, $scheduleid);
    $file = "Notes";
    }
  else if(preg_match('/<DynamicAnalysis/', $content))
    {
    $handler = new DynamicAnalysisHandler($projectid, $scheduleid);
    $file = "DynamicAnalysis";
    }
  else if(preg_match('/<Project/', $content))
    {
    $handler = new ProjectHandler($projectid, $scheduleid);
    $file = "Project";
    }
  else if(preg_match('/<Upload/', $content))
    {
    $handler = new UploadHandler($projectid, $scheduleid);
    $file = "Upload";
    }
  else if(preg_match('/<test-results/', $content))
    {
    $handler = new TestingNUnitHandler($projectid, $scheduleid);
    $file = "Test";
    }
  else if(preg_match('/<testsuite/', $content))
    {
    $handler = new TestingJUnitHandler($projectid, $scheduleid);
    $file = "Test";
    }

  if($handler == NULL)
    {
    echo "no handler found";
    add_log('error: could not create handler based on xml content', 'ctest_parse',LOG_ERR);
    $Project = new Project();
    $Project->Id = $projectid;

    // Try to get the IP of the build
    $ip = $_SERVER['REMOTE_ADDR'];

    $Project->SendEmailToAdmin('Cannot create handler based on XML content',
                               'An XML submission from '.$ip.' to the project '.get_project_name($projectid).' cannot be parsed. The content of the file is as follow: '.$content);
    return;
    }

  xml_set_element_handler($parser, array($handler, 'startElement'), array($handler, 'endElement'));
  xml_set_character_data_handler($parser, array($handler, 'text'));
  xml_parse($parser, $content, false);

  $projectname = get_project_name($projectid);

  $sitename = "";
  $buildname = "";
  if($file != "Project") // projects don't have site and build name
    {
    $sitename = $handler->getSiteName();
    $buildname = $handler->getBuildName();
    }
  // Check if the build is in the block list
  $query = pdo_query("SELECT id FROM blockbuild WHERE projectid=".qnum($projectid)."
                         AND (buildname='' OR buildname='".$buildname."')
                         AND (sitename='' OR sitename='".$sitename."')
                         AND (ipaddress='' OR ipaddress='".$_SERVER['REMOTE_ADDR']."')");

  if(pdo_num_rows($query)>0)
    {
    echo $query_array['id'];
    echo "The submission is banned from this CDash server.";
    add_log("Submission is banned from this CDash server","ctestparser");
    return;
    }

  // Append a timestamp for the file
  $currenttimestamp = microtime(true)*100;

  $backupDir = $CDASH_BACKUP_DIRECTORY;
  if(!file_exists($backupDir))
    {
    // try parent dir as well (for asynch submission)
    $backupDir = "../$backupDir";

    if(!file_exists($backupDir))
      {
      trigger_error(
        "function ctest_parse cannot process files when backup directory ".
        "does not exist: CDASH_BACKUP_DIRECTORY='$CDASH_BACKUP_DIRECTORY'",
        E_USER_ERROR);
      return;
      }
    }

  // We escape the sitename and buildname
  $sitename_escaped = preg_replace('/[^\w\-~_]+/u', '-', $sitename);
  $buildname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $buildname);
  $projectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $projectname);

  if($file == "Project")
    {
    $filename = $backupDir."/".$projectname_escaped."_".$currenttimestamp."_".$file.".xml";
    }
  else
    {
    $filename = $backupDir."/".$projectname_escaped."_".$sitename_escaped."_".$buildname_escaped."_".$handler->getBuildStamp()."_".$currenttimestamp.'_'.$file.".xml";
    }

  // If the file is other we append a number until we get a non existing file
  $i=1;
  while(file_exists($filename))
    {
    $filename = $backupDir."/".$projectname_escaped."_".$sitename_escaped."_".$buildname_escaped."_".$handler->getBuildStamp().'_'.$currenttimestamp."_".$file."_".$i.".xml";
    $i++;
    }

  // Make sure the file is in the right directory
  $pos = strpos(realpath(dirname($filename)),realpath($backupDir));
  if($pos === FALSE || $pos!=0)
    {
    echo "File cannot be stored in backup directory: $filename";
    add_log("File cannot be stored in backup directory: $filename (realpath = ".realpath($backupDir).")", "ctest_parse",LOG_ERR);
    return $handler;
    }

  if(!$handle = fopen($filename, 'w'))
    {
    echo "Cannot open file ($filename)";
    add_log("Cannot open file ($filename)", "ctest_parse",LOG_ERR);
    return $handler;
    }

  // Write the file.
  if(fwrite($handle, $content) === FALSE)
    {
    echo "ERROR: Cannot write to file ($filename)";
    add_log("Cannot write to file ($filename)", "ctest_parse",LOG_ERR);
    fclose($handle);
    unset($handle);
    return $handler;
    }

  while(!feof($filehandler))
    {
    $content = fread($filehandler, 8192);
    if (fwrite($handle, $content) === FALSE)
      {
      echo "ERROR: Cannot write to file ($filename)";
      add_log("Cannot write to file ($filename)", "ctest_parse",LOG_ERR);
      fclose($handle);
      unset($handle);
      return $filename;
      }
    }
  fclose($handle);
  unset($handle);

  if($do_checksum == true)
    {
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
      add_log("Checksum failure on file: $filename", "ctest_parse", LOG_ERR, $projectid);
      return FALSE;
      }
    }

  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
    {
    $localParser->StartParsing();
    }
  if(!$parseHandle = fopen($filename, 'r'))
    {
    echo "ERROR: Cannot open file ($filename)";
    add_log("Cannot open file ($filename)", "parse_xml_file",LOG_ERR);
    return $handler;
    }

  //burn the first 8192 since we have already parsed it
  $content = fread($parseHandle, 8192);
  while(!feof($parseHandle))
    {
    $content = fread($parseHandle, 8192);

    if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
      {
      $localParser->ParseFile();
      }
    xml_parse($parser,$content, false);
    }
  xml_parse($parser, null, true);
  xml_parser_free($parser);
  fclose($parseHandle);
  unset($parseHandle);

  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
    {
    $localParser->EndParsingFile();
    }
  return $handler;
}
?>
