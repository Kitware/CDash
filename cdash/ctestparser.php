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

/** Main function to parse the incoming xml from ctest */
function ctest_parse($filehandler, $projectid,$onlybackup=false,$expected_md5='')
{
  include 'cdash/config.php';
  require_once 'cdash/common.php';
  require_once 'models/project.php';
  include 'cdash/version.php';

  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
    {
    include("local/ctestparser.php");
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
    $handler = new UpdateHandler($projectid);
    $file = "Update";
    }
  else if(ereg('<Build', $content))
    {
    $handler = new BuildHandler($projectid);
    $file = "Build";
    }
  else if(preg_match('/<Configure/', $content))
    {
    $handler = new ConfigureHandler($projectid);
    $file = "Configure";
    }
  else if(preg_match('/<Testing/', $content))
    {
    $handler = new TestingHandler($projectid);
    $file = "Test";
    }
  else if(preg_match('/<CoverageLog/', $content)) // Should be before coverage
    {
    $handler = new CoverageLogHandler($projectid);
    $file = "CoverageLog";
    }
  else if(preg_match('/<Coverage/', $content))
    {
    $handler = new CoverageHandler($projectid);
    $file = "Coverage";
    }
  else if(preg_match('/<Notes/', $content))
    {
    $handler = new NoteHandler($projectid);
    $file = "Notes";
    }
  else if(preg_match('/<DynamicAnalysis/', $content))
    {
    $handler = new DynamicAnalysisHandler($projectid);
    $file = "DynamicAnalysis";
    }
  else if(preg_match('/<Project/', $content))
    {
    $handler = new ProjectHandler($projectid);
    $file = "Project";
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

  // Clean the backup directory
  clean_backup_directory();


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

  if($file == "Project")
    {
    $filename = $CDASH_BACKUP_DIRECTORY."/".$projectname."_".$currenttimestamp."_".$file.".xml";
    }
  else
    {
    $filename = $CDASH_BACKUP_DIRECTORY."/".$projectname."_".$sitename."_".$buildname."_".$handler->getBuildStamp()."_".$currenttimestamp.'_'.$file.".xml";
    }

  // If the file is other we append a number until we get a non existing file
  $i=1;
  while(file_exists($filename))
    {
    $filename = $CDASH_BACKUP_DIRECTORY."/".$projectname."_".$sitename."_".$buildname."_".$handler->getBuildStamp().'_'.$currenttimestamp."_".$file."_".$i.".xml";
    $i++;
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
    echo "Cannot write to file ($filename)";
    add_log("Cannot write to file ($filename)", "ctest_parse",LOG_ERR);
    fclose($handle);
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
      return $filename;
      }
    }
  fclose($handle);
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

  if($onlybackup) //asynchronous_submit
    {
    return $filename;
    }
  else //synchronous_submit (parse now)
    {
    // Set the handler
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
    
    //burn the first 8192 since we have already read it
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
    
    if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
      {
      $localParser->EndParsingFile();
      }
    }

  return $handler;
}
?>
