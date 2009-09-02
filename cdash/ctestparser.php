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

/** Main function to parse the incoming xml from ctest */
function ctest_parse($filehandler, $projectid,$onlybackup=false)
{ 
  include 'cdash/config.php';
  require_once 'cdash/common.php';
  require_once 'models/project.php';
  
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
    add_log("Content = ".$content, 'ctest_parse');
    $Project = new Project();
    $Project->Id = $projectid;
    
    // Try to get the IP of the build
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $Project->SendEmailToAdmin('Cannot create handler based on XML content',
                               'An XML submission from '.$ip.' to the project'.get_project_name($projectid).' cannot be parsed. The content of the file is as follow: '.$content);
    exit();
    }

  xml_set_element_handler($parser, array($handler, 'startElement'), array($handler, 'endElement'));
  xml_set_character_data_handler($parser, array($handler, 'text'));
  xml_parse($parser, $content, false);

  // Clean the backup directory
  clean_backup_directory();
  
  // Append a timestamp for the file
  $currenttimestamp = microtime(true)*100;
  
  if($file == "Project")
    {
    $filename = $CDASH_BACKUP_DIRECTORY."/".get_project_name($projectid)."_".$currenttimestamp."_".$file.".xml";
    }
  else
    {  
    $filename = $CDASH_BACKUP_DIRECTORY."/".get_project_name($projectid)."_".$handler->getSiteName()."_".$handler->getBuildName()."_".$handler->getBuildStamp()."_".$currenttimestamp.'_'.$file.".xml";
    }
    
  // If the file is other we append a number until we get a non existing file
  $i=1;
  while(file_exists($filename))
    {
    $filename = $CDASH_BACKUP_DIRECTORY."/".get_project_name($projectid)."_".$handler->getSiteName()."_".$handler->getBuildName()."_".$handler->getBuildStamp().'_'.$currenttimestamp."_".$file."_".$i.".xml";
    $i++;
    }
   
  if(!$handle = fopen($filename, 'w')) 
    {
    echo "Cannot open file ($filename)";
    add_log("Cannot open file ($filename)", "backup_xml_file",LOG_ERR);
    return $handler;
    }
  
  // Write the file.
  if(fwrite($handle, $content) === FALSE)  
    {
    echo "Cannot write to file ($contents)";
    add_log("Cannot write to file ($$contents)", "backup_xml_file",LOG_ERR);
    fclose($handle);
    return $handler;
    }
  
  // If it's only the backup we write it
  if($onlybackup)
    {
    while(!feof($filehandler))
      {
      $content = fread($filehandler, 8192);
      if (fwrite($handle, $content) === FALSE)  
        {
        echo "Cannot write to file ($contents)";
        add_log("Cannot write to file ($$contents)", "backup_xml_file",LOG_ERR);
        fclose($handle);
        return $filename;
        }
      }
    return $filename;  
    }
  else
    {
    // Set the handler
    if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
      {
      $localParser->StartParsing();
      }
        
    while(!feof($filehandler))
      {
      $content = fread($filehandler, 8192);
      
      if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
        {
        $localParser->ParseFile();
        }
      
      if (fwrite($handle, $content) === FALSE)  
        {
        echo "Cannot write to file ($contents)";
        add_log("Cannot write to file ($$contents)", "backup_xml_file",LOG_ERR);
        fclose($handle);
        return $handler;
        }
      xml_parse($parser,$content, false);
      }
    xml_parse($parser, null, true);
    xml_parser_free($parser);
    
    if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php"))
      {
      $localParser->EndParsingFile();
      }
    }
  fclose($handle);
  
  return $handler;
}
?>
