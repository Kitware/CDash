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

// Helper function to display the message
function displayReturnStatus($statusarray)
{
    include 'cdash/version.php';
    echo "<cdash version=\"$CDASH_VERSION\">\n";
    foreach ($statusarray as $key=>$value) {
        echo "  <".$key.">".$value."</".$key.">\n";
    }
    echo "</cdash>\n";
}

/** Function used to write a submitted file to our backup directory with a
  * descriptive name. */
function writeBackupFile($filehandler, $content, $projectname, $buildname,
                         $sitename, $stamp, $fileNameWithExt)
{
    global $CDASH_BACKUP_DIRECTORY;

  // Append a timestamp for the file
  $currenttimestamp = microtime(true)*100;

    $backupDir = $CDASH_BACKUP_DIRECTORY;
    if (!file_exists($backupDir)) {
        // try parent dir as well (for asynch submission)
    $backupDir = "../$backupDir";

        if (!file_exists($backupDir)) {
            trigger_error(
        "function writeBackupFile cannot process files when backup directory ".
        "does not exist: CDASH_BACKUP_DIRECTORY='$CDASH_BACKUP_DIRECTORY'",
        E_USER_ERROR);
            return false;
        }
    }

  // We escape the sitename and buildname
  $sitename_escaped = preg_replace('/[^\w\-~_]+/u', '-', $sitename);
    $buildname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $buildname);
    $projectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $projectname);

  // Separate the extension from the filename.
  $ext = "." . pathinfo($fileNameWithExt, PATHINFO_EXTENSION);
    $file = pathinfo($fileNameWithExt, PATHINFO_FILENAME);

    if ($file == "Project") {
        $filename = $backupDir."/".$projectname_escaped."_".$currenttimestamp."_".$file.$ext;
    } else {
        $filename = $backupDir."/".$projectname_escaped."_".$sitename_escaped."_".$buildname_escaped."_".$stamp."_".$currenttimestamp.'_'.$file.$ext;
    }

  // If the file is other we append a number until we get a non existing file
  $i=1;
    while (file_exists($filename)) {
        $filename = $backupDir."/".$projectname_escaped."_".$sitename_escaped."_".$buildname_escaped."_".$stamp.'_'.$currenttimestamp."_".$file."_".$i.$ext;
        $i++;
    }

  // Make sure the file is in the right directory
  $pos = strpos(realpath(dirname($filename)), realpath($backupDir));
    if ($pos === false || $pos!=0) {
        echo "File cannot be stored in backup directory: $filename";
        add_log("File cannot be stored in backup directory: $filename (realpath = ".realpath($backupDir).")", "writeBackupFile", LOG_ERR);
        return false;
    }

    if (!$handle = fopen($filename, 'w')) {
        echo "Cannot open file ($filename)";
        add_log("Cannot open file ($filename)", "writeBackupFile", LOG_ERR);
        return false;
    }

  // Write the file.
  if (fwrite($handle, $content) === false) {
      echo "ERROR: Cannot write to file ($filename)";
      add_log("Cannot write to file ($filename)", "writeBackupFile", LOG_ERR);
      fclose($handle);
      unset($handle);
      return false;
  }

    while (!feof($filehandler)) {
        $content = fread($filehandler, 8192);
        if (fwrite($handle, $content) === false) {
            echo "ERROR: Cannot write to file ($filename)";
            add_log("Cannot write to file ($filename)", "writeBackupFile", LOG_ERR);
            fclose($handle);
            unset($handle);
            return false;
        }
    }
    fclose($handle);
    unset($handle);
    return $filename;
}

/** Function to handle new style submissions via HTTP PUT */
function parse_put_submission($filehandler, $projectid, $expected_md5)
{
    if (!$expected_md5) {
        return false;
    }

    $buildfile_row = pdo_single_row_query(
    "SELECT * FROM buildfile WHERE md5='$expected_md5'");
    if (empty($buildfile_row)) {
        return false;
    }

  // Save a backup file for this submission.
  $row = pdo_single_row_query("SELECT name FROM project WHERE id=$projectid");
    $projectname = $row["name"];

    $buildid = $buildfile_row['buildid'];
    $row = pdo_single_row_query(
    "SELECT name, stamp FROM build WHERE id=$buildid");
    $buildname = $row["name"];
    $stamp = $row["stamp"];

    $row = pdo_single_row_query(
    "SELECT name FROM site WHERE id=
     (SELECT siteid FROM build WHERE id=$buildid)");
    $sitename = $row["name"];

    writeBackupFile($filehandler, "", $projectname, $buildname, $sitename,
                  $stamp, $buildfile_row["filename"]);

  // Include the handler file for this type of submission.
  $type = $buildfile_row['type'];
    $include_file = "xml_handlers/" . $type . "_handler.php";
    if (stream_resolve_include_path($include_file) === false) {
        add_log("No handler include file for $type (tried $include_file)",
      "parse_put_submission",
      LOG_ERR, $projectid);
        return true;
    }
    require_once($include_file);

  // Instantiate the handler.
  $className = $type . "Handler";
    if (!class_exists($className)) {
        add_log("No handler class for $type", "parse_put_submission",
      LOG_ERR, $projectid);
        return true;
    }
    $handler = new $className($buildid);

  // Parse the file.
  $handler->Parse($filehandler);
    return true;
}

/** Main function to parse the incoming xml from ctest */
function ctest_parse($filehandler, $projectid, $expected_md5='', $do_checksum=true,
                     $scheduleid=0)
{
    include 'cdash/config.php';
    require_once 'cdash/common.php';
    require_once 'models/project.php';
    include 'cdash/version.php';

    if ($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php")) {
        require_once("local/ctestparser.php");
        $localParser = new LocalParser();
        $localParser->SetProjectId($projectid);
        $localParser->BufferSizeMB =8192/(1024*1024);
    }

  // Check if this is a new style PUT submission.
  if (parse_put_submission($filehandler, $projectid, $expected_md5)) {
      return true;
  }

    $content = fread($filehandler, 8192);
    $handler = null;
    $parser = xml_parser_create();
    $file = "";

    if (preg_match('/<Update/', $content)) {
        // Should be first otherwise confused with Build

    $handler = new UpdateHandler($projectid, $scheduleid);
        $file = "Update";
    } elseif (preg_match('/<Build/', $content)) {
        $handler = new BuildHandler($projectid, $scheduleid);
        $file = "Build";
    } elseif (preg_match('/<Configure/', $content)) {
        $handler = new ConfigureHandler($projectid, $scheduleid);
        $file = "Configure";
    } elseif (preg_match('/<Testing/', $content)) {
        $handler = new TestingHandler($projectid, $scheduleid);
        $file = "Test";
    } elseif (preg_match('/<CoverageLog/', $content)) {
        // Should be before coverage

    $handler = new CoverageLogHandler($projectid, $scheduleid);
        $file = "CoverageLog";
    } elseif (preg_match('/<Coverage/', $content)) {
        $handler = new CoverageHandler($projectid, $scheduleid);
        $file = "Coverage";
    } elseif (preg_match('/<report/', $content)) {
        $handler = new CoverageJUnitHandler($projectid, $scheduleid);
        $file = "Coverage";
    } elseif (preg_match('/<Notes/', $content)) {
        $handler = new NoteHandler($projectid, $scheduleid);
        $file = "Notes";
    } elseif (preg_match('/<DynamicAnalysis/', $content)) {
        $handler = new DynamicAnalysisHandler($projectid, $scheduleid);
        $file = "DynamicAnalysis";
    } elseif (preg_match('/<Project/', $content)) {
        $handler = new ProjectHandler($projectid, $scheduleid);
        $file = "Project";
    } elseif (preg_match('/<Upload/', $content)) {
        $handler = new UploadHandler($projectid, $scheduleid);
        $file = "Upload";
    } elseif (preg_match('/<test-results/', $content)) {
        $handler = new TestingNUnitHandler($projectid, $scheduleid);
        $file = "Test";
    } elseif (preg_match('/<testsuite/', $content)) {
        $handler = new TestingJUnitHandler($projectid, $scheduleid);
        $file = "Test";
    }

    if ($handler == null) {
        echo "no handler found";
        add_log('error: could not create handler based on xml content', 'ctest_parse', LOG_ERR);
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
    $stamp = "";
    if ($file != "Project") {
        // projects don't have some of these fields.

    $sitename = $handler->getSiteName();
        $buildname = $handler->getBuildName();
        $stamp = $handler->getBuildStamp();
    }

  // Check if the build is in the block list
  $query = pdo_query("SELECT id FROM blockbuild WHERE projectid=".qnum($projectid)."
                         AND (buildname='' OR buildname='".$buildname."')
                         AND (sitename='' OR sitename='".$sitename."')
                         AND (ipaddress='' OR ipaddress='".$_SERVER['REMOTE_ADDR']."')");

    if (pdo_num_rows($query)>0) {
        echo $query_array['id'];
        echo "The submission is banned from this CDash server.";
        add_log("Submission is banned from this CDash server", "ctestparser");
        return;
    }

  // Write the file to the backup directory.
  $filename = writeBackupFile($filehandler, $content, $projectname, $buildname,
                              $sitename, $stamp, $file . ".xml");
    if ($filename === false) {
        return $handler;
    }

    $statusarray = array();
    $statusarray['status'] = 'OK';
    $statusarray['message'] = '';
    if ($do_checksum == true) {
        $md5sum = md5_file($filename);
        $md5error = false;
        if ($expected_md5 == '' || $expected_md5 == $md5sum) {
            $statusarray['status'] = 'OK';
        } else {
            $statusarray['status'] = 'ERROR';
            $statusarray['message'] = 'Checksum failed for file. Expected '.$expected_md5.' but got '.$md5sum;
            $md5error = true;
        }

        $statusarray['md5'] = $md5sum;
        if ($md5error) {
            displayReturnStatus($statusarray);
            add_log("Checksum failure on file: $filename", "ctest_parse", LOG_ERR, $projectid);
            return false;
        }
    }

    $parsingerror = '';
    if ($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php")) {
        $parsingerror = $localParser->StartParsing();
        if ($parsingerror != '') {
            $statusarray['status'] = 'ERROR';
            $statusarray['message'] = $parsingerror;
            displayReturnStatus($statusarray);
            exit();
        }
    }
    if (!$parseHandle = fopen($filename, 'r')) {
        $statusarray['status'] = 'ERROR';
        $statusarray['message'] = "ERROR: Cannot open file ($filename)";
        displayReturnStatus($statusarray);
        add_log("Cannot open file ($filename)", "parse_xml_file", LOG_ERR);
        return $handler;
    }

  //burn the first 8192 since we have already parsed it
  $content = fread($parseHandle, 8192);
    while (!feof($parseHandle)) {
        $content = fread($parseHandle, 8192);

        if ($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php")) {
            $parsingerror = $localParser->ParseFile();
            if ($parsingerror != '') {
                $statusarray['status'] = 'ERROR';
                $statusarray['message'] = $parsingerror;
                displayReturnStatus($statusarray);
                exit();
            }
        }
        xml_parse($parser, $content, false);
    }
    xml_parse($parser, null, true);
    xml_parser_free($parser);
    fclose($parseHandle);
    unset($parseHandle);

    if ($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/ctestparser.php")) {
        $parsingerror = $localParser->EndParsingFile();
    }

    displayReturnStatus($statusarray);
    return $handler;
}
