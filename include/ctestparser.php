<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
  =========================================================================*/

require_once 'xml_handlers/build_handler.php';
require_once 'xml_handlers/configure_handler.php';
require_once 'xml_handlers/testing_handler.php';
require_once 'xml_handlers/update_handler.php';
require_once 'xml_handlers/coverage_handler.php';
require_once 'xml_handlers/coverage_log_handler.php';
require_once 'xml_handlers/done_handler.php';
require_once 'xml_handlers/note_handler.php';
require_once 'xml_handlers/dynamic_analysis_handler.php';
require_once 'xml_handlers/project_handler.php';
require_once 'xml_handlers/upload_handler.php';
require_once 'xml_handlers/testing_nunit_handler.php';
require_once 'xml_handlers/testing_junit_handler.php';
require_once 'xml_handlers/coverage_junit_handler.php';

use CDash\Config;
use CDash\Lib\Parser\CTest\BuildParser;
use CDash\Lib\Parser\CTest\ConfigureParser;
use CDash\Lib\Parser\CTest\CoverageLogParser;
use CDash\Lib\Parser\CTest\CoverageParser;
use CDash\Lib\Parser\CTest\DynamicAnalysisParser;
use CDash\Lib\Parser\CTest\NoteParser;
use CDash\Lib\Parser\CTest\ProjectParser;
use CDash\Lib\Parser\CTest\TestingParser;
use CDash\Lib\Parser\CTest\UpdateParser;
use CDash\Lib\Parser\CTest\UploadParser;
use CDash\Lib\Parser\JUnit\CoverageParser as JUnitCoverageParser;
use CDash\Lib\Parser\JUnit\TestingParser as JUnitTestingParser;
use CDash\Lib\Parser\NUnit\TestingParser as NUnitTestingParser;

use CDash\Model\BuildFile;
use CDash\Model\Project;
use CDash\Lib\Parsing\JUnit\TestingParser as JUnitTestParser;

class CDashParseException extends RuntimeException
{
}

// Helper function to display the message
function displayReturnStatus($statusarray)
{
    include 'include/version.php';

    $config = Config::getInstance();

    echo "<cdash version=\"{$config->get('CDASH_VERSION')}\">\n";
    foreach ($statusarray as $key => $value) {
        echo '  <' . $key . '>' . $value . '</' . $key . ">\n";
    }
    echo "</cdash>\n";
}

/** Determine the descriptive filename for a submission file.
  * Called by writeBackupFile().
  **/
function generateBackupFileName($projectname, $buildname, $sitename, $stamp,
                                $fileNameWithExt)
{
    // Generate a timestamp to include in the filename.
    $currenttimestamp = microtime(true) * 100;

    // Escape the sitename, buildname, and projectname.
    $sitename_escaped = preg_replace('/[^\w\-~_]+/u', '-', $sitename);
    $buildname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $buildname);
    $projectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $projectname);

    // Separate the extension from the filename.
    $ext = '.' . pathinfo($fileNameWithExt, PATHINFO_EXTENSION);
    $file = pathinfo($fileNameWithExt, PATHINFO_FILENAME);

    $filename = $projectname_escaped . '_';
    if ($file != 'Project') {
        // Project.xml files aren't associated with a particular build, so we
        // only record the site and buildname for other types of submissions.
        $filename .= $sitename_escaped . '_' . $buildname_escaped . '_' . $stamp . '_';
    }
    $filename .=  $currenttimestamp . '_' . $file . $ext;
    return $filename;
}

/** Safely write a backup file, taking care to avoid writing two files
  * to the same destination. Called by writeBackupFile().
  **/
function safelyWriteBackupFile($filehandler, $content, $filename)
{
    // If the file exists we append a number until we get a nonexistent file.
    $config = Config::getInstance();
    $backupDir = $config->get('CDASH_BACKUP_DIRECTORY');
    $got_lock = false;
    $i = 1;
    while (!$got_lock) {
        $lockfilename = $filename . '.lock';
        $lockfp = fopen($lockfilename, 'w');
        flock($lockfp, LOCK_EX | LOCK_NB, $wouldblock);
        if ($wouldblock) {
            $path_parts = pathinfo($filename);
            $filename = $path_parts['dirname'] . '/' . $path_parts['filename'] . "_$i." . $part_parts['extension'];
            $i++;
        } else {
            $got_lock = true;
            // realpath() always returns false for Google Cloud Storage.
            if (realpath($config->get('CDASH_DATA_ROOT_DIRECTORY')) !== false) {
                // Make sure the file is in the right directory.
                $pos = strpos(realpath(dirname($filename)), realpath($backupDir));
                if ($pos === false || $pos != 0) {
                    echo "File cannot be stored in backup directory: $filename";
                    add_log("File cannot be stored in backup directory: $filename (realpath = " . realpath($backupDir) . ')', 'writeBackupFile', LOG_ERR);
                    flock($lockfp, LOCK_UN);
                    unlink($lockfilename);
                    return false;
                }
            }

            if (!$handle = fopen($filename, 'w')) {
                echo "Cannot open file ($filename)";
                add_log("Cannot open file ($filename)", 'writeBackupFile', LOG_ERR);
                flock($lockfp, LOCK_UN);
                unlink($lockfilename);
                return false;
            }
        }
        flock($lockfp, LOCK_UN);
        if (file_exists($lockfilename)) {
            unlink($lockfilename);
        }
    }

    // Write the file.
    if (fwrite($handle, $content) === false) {
        echo "ERROR: Cannot write to file ($filename)";
        add_log("Cannot write to file ($filename)", 'writeBackupFile', LOG_ERR);
        fclose($handle);
        unset($handle);
        return false;
    }

    while (!feof($filehandler)) {
        $content = fread($filehandler, 8192);
        if (fwrite($handle, $content) === false) {
            echo "ERROR: Cannot write to file ($filename)";
            add_log("Cannot write to file ($filename)", 'writeBackupFile', LOG_ERR);
            fclose($handle);
            unset($handle);
            return false;
        }
    }
    fclose($handle);
    unset($handle);
    return $filename;
}

/** Function used to write a submitted file to our backup directory with a
 * descriptive name. */
function writeBackupFile($filehandler, $content, $projectname, $buildname,
                         $sitename, $stamp, $fileNameWithExt)
{
    // Make sure the backup directory exists.
    $config = Config::getInstance();
    $backupDir = $config->get('CDASH_BACKUP_DIRECTORY');
    if (!file_exists($backupDir)) {
        // try parent dir as well (for asynch submission)
        $backupDir = "../$backupDir";

        if (!file_exists($backupDir)) {
            trigger_error(
                'function writeBackupFile cannot process files when backup directory ' .
                "does not exist: CDASH_BACKUP_DIRECTORY='{$config->get('CDASH_BACKUP_DIRECTORY')}'",
                E_USER_ERROR);
            return false;
        }
    }

    $filename = $backupDir . '/';
    $filename .= generateBackupFileName($projectname, $buildname, $sitename, $stamp, $fileNameWithExt);
    return safelyWriteBackupFile($filehandler, $content, $filename);
}

/** Function to handle new style submissions via HTTP PUT */
function parse_put_submission($filehandler, $projectid, $expected_md5)
{
    if (!$expected_md5) {
        return false;
    }

    $buildfile_row = pdo_single_row_query(
        "SELECT * FROM buildfile WHERE md5='$expected_md5' LIMIT 1");
    if (empty($buildfile_row)) {
        return false;
    }

    // Save a backup file for this submission.
    $row = pdo_single_row_query("SELECT name FROM project WHERE id=$projectid");
    $projectname = $row['name'];

    $buildid = $buildfile_row['buildid'];
    $row = pdo_single_row_query(
        "SELECT name, stamp FROM build WHERE id=$buildid");
    $buildname = $row['name'];
    $stamp = $row['stamp'];

    $row = pdo_single_row_query(
        "SELECT name FROM site WHERE id=
            (SELECT siteid FROM build WHERE id=$buildid)");
    $sitename = $row['name'];

    $config = Config::getInstance();
    if ($config->get('CDASH_BACKUP_TIMEFRAME') == '0') {
        // We do not save submission files after they are parsed.
        // Work directly off the open file handle.
        $meta_data = stream_get_meta_data($filehandler);
        $filename = $meta_data['uri'];
        $backup_filename = generateBackupFileName($projectname, $buildname,
                $sitename, $stamp, $buildfile_row['filename']);
    } else {
        $filename = writeBackupFile($filehandler, '', $projectname, $buildname,
            $sitename, $stamp, $buildfile_row['filename']);
        $backup_filename = $filename;
    }

    // Instantiate a buildfile object so we can delete it from the database
    // once we're done parsing it.
    $buildfile = new BuildFile();
    $buildfile->BuildId = $buildid;
    $buildfile->md5 = $expected_md5;

    // Include the handler file for this type of submission.
    $type = $buildfile_row['type'];
    $include_file = 'xml_handlers/' . $type . '_handler.php';
    if (stream_resolve_include_path($include_file) === false) {
        add_log("No handler include file for $type (tried $include_file)",
            'parse_put_submission',
            LOG_ERR, $projectid);
        check_for_immediate_deletion($filename);
        $buildfile->Delete();
        return true;
    }
    require_once $include_file;

    // Instantiate the handler.
    $className = $type . 'Handler';
    if (!class_exists($className)) {
        add_log("No handler class for $type", 'parse_put_submission',
            LOG_ERR, $projectid);
        check_for_immediate_deletion($filename);
        $buildfile->Delete();
        return true;
    }
    $handler = new $className($buildid);

    // Parse the file.
    if ($handler->Parse($filename) === false) {
        throw new CDashParseException('Failed to parse file ' . $filename);
    }

    check_for_immediate_deletion($filename);
    $buildfile->Delete();
    $handler->backupFileName = $backup_filename;
    return $handler;
}

/** Main function to parse the incoming xml from ctest */
function ctest_parse($filehandler, $projectid, $buildid = null,
                     $expected_md5 = '', $do_checksum = true, $scheduleid = 0)
{
    require_once 'include/common.php';
    include 'include/version.php';

    $config = Config::getInstance();
    if ($config->get("CDASH_USE_LOCAL_DIRECTORY") && file_exists('local/ctestparser.php')) {
        require_once 'local/ctestparser.php';
        $localParser = new LocalParser();
        $localParser->SetProjectId($projectid);
        $localParser->BufferSizeMB = 8192 / (1024 * 1024);
    }

    // Check if this is a new style PUT submission.
    try {
        $handler = parse_put_submission($filehandler, $projectid, $expected_md5);
        if ($handler) {
            return $handler;
        }
    } catch (CDashParseException $e) {
        add_log($e->getMessage(), 'ctest_parse', LOG_ERR);
        return false;
    }

    $content = fread($filehandler, 8192);
    $handler = null;
    $parser = xml_parser_create();
    $file = '';

    if (preg_match('/<Update/', $content)) {
        // Should be first otherwise confused with Build
         $handler = new UpdateParser($projectid);
        $file = 'Update';
    } elseif (preg_match('/<Build/', $content)) {
        $handler = new BuildParser($projectid);
        $file = 'Build';
    } elseif (preg_match('/<Configure/', $content)) {
        $handler = new ConfigureParser($projectid);
        $file = 'Configure';
    } elseif (preg_match('/<Testing/', $content)) {
        $handler = new TestingParser($projectid);
        $file = 'Test';
    } elseif (preg_match('/<CoverageLog/', $content)) {
        // Should be before coverage
        $handler = new CoverageLogParser($projectid);
        $file = 'CoverageLog';
    } elseif (preg_match('/<Coverage/', $content)) {
        $handler = new CoverageParser($projectid);
        $file = 'Coverage';
    } elseif (preg_match('/<report/', $content)) {
        $handler = new JUnitCoverageParser($projectid);
        $file = 'Coverage';
    } elseif (preg_match('/<Notes/', $content)) {
        $handler = new NoteParser($projectid);
        $file = 'Notes';
    } elseif (preg_match('/<DynamicAnalysis/', $content)) {
        $handler = new DynamicAnalysisParser($projectid);
        $file = 'DynamicAnalysis';
    } elseif (preg_match('/<Project/', $content)) {
        $handler = new ProjectParser($projectid);
        $file = 'Project';
    } elseif (preg_match('/<Upload/', $content)) {
        $handler = new UploadParser($projectid);
        $file = 'Upload';
    } elseif (preg_match('/<test-results/', $content)) {
        $handler = new NUnitTestingParser($projectid);
        $file = 'Test';
    } elseif (preg_match('/<testsuite/', $content)) {
        $handler = new JUnitTestingParser($projectid);
        $file = 'Test';
    } elseif (preg_match('/<Done/', $content)) {
        $handler = new DoneHandler($projectid, $scheduleid);
        $file = 'Done';
    }

    // Try to get the IP of the build
    $ip = null;
    $config = Config::getInstance();
    if ($config->get('CDASH_REMOTE_ADDR')) {
        $ip = $config->get('CDASH_REMOTE_ADDR');
    } elseif (array_key_exists('REMOTE_ADDR', $_SERVER)) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    if ($handler == null) {
        echo 'no handler found';
        add_log('error: could not create handler based on xml content', 'ctest_parse', LOG_ERR);
        $Project = new Project();
        $Project->Id = $projectid;

        $Project->SendEmailToAdmin('Cannot create handler based on XML content',
            'An XML submission from ' . $ip . ' to the project ' . get_project_name($projectid) . ' cannot be parsed. The content of the file is as follow: ' . $content);
        return false;
    }

    xml_set_element_handler($parser, array($handler, 'startElement'), array($handler, 'endElement'));
    xml_set_character_data_handler($parser, array($handler, 'text'));
    xml_parse($parser, $content, false);

    $projectname = get_project_name($projectid);

    $sitename = '';
    $buildname = '';
    $stamp = '';
    if ($file != 'Project') {
        // projects don't have some of these fields.

        $sitename = $handler->getSiteName();
        $buildname = $handler->getBuildName();
        $stamp = $handler->getBuildStamp();
    }

    // Check if the build is in the block list
    $query = pdo_query('SELECT id FROM blockbuild WHERE projectid=' . qnum($projectid) . "
            AND (buildname='' OR buildname='" . $buildname . "')
            AND (sitename='' OR sitename='" . $sitename . "')
            AND (ipaddress='' OR ipaddress='" . $ip . "')");

    if (pdo_num_rows($query) > 0) {
        echo 'The submission is banned from this CDash server.';
        add_log('Submission is banned from this CDash server', 'ctestparser');
        return false;
    }

    // If backups are disabled, switch the filename to that of the existing handle
    // Otherwise, create a backup file and process from that
    if ($config->get('CDASH_BACKUP_TIMEFRAME') == '0') {
        $meta_data = stream_get_meta_data($filehandler);
        $filename = $meta_data['uri'];
        $backup_filename = generateBackupFileName($projectname, $buildname,
                $sitename, $stamp, $file . '.xml');
    } else {
        $filename = writeBackupFile($filehandler, $content, $projectname, $buildname,
                                    $sitename, $stamp, $file . '.xml');
        $backup_filename = $filename;
        if ($filename === false) {
            return $handler;
        }
    }

    $statusarray = [];
    $statusarray['status'] = 'OK';
    $statusarray['message'] = '';
    if (!is_null($buildid)) {
        $statusarray['buildId'] = $buildid;
    }
    if ($do_checksum == true) {
        if (!file_exists($filename)) {
            // Parsing cannot continue if this file does not exist.
            // Perhaps another process already parsed it.
            add_log("File does not exist, checksum cannot continue: $filename",
                    'ctest_parse', LOG_INFO, $projectid);
            return false;
        }
        $md5sum = md5_file($filename);
        $md5error = false;
        if ($expected_md5 == '' || $expected_md5 == $md5sum) {
            $statusarray['status'] = 'OK';
        } else {
            $statusarray['status'] = 'ERROR';
            $statusarray['message'] = 'Checksum failed for file. Expected ' . $expected_md5 . ' but got ' . $md5sum;
            $md5error = true;
        }

        $statusarray['md5'] = $md5sum;
        if ($md5error) {
            displayReturnStatus($statusarray);
            add_log("Checksum failure on file: $filename", 'ctest_parse', LOG_ERR, $projectid);
            return false;
        }
    }

    $parsingerror = '';
    if ($config->get('CDASH_USE_LOCAL_DIRECTORY') && file_exists('local/ctestparser.php')) {
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
        add_log("Cannot open file ($filename)", 'parse_xml_file', LOG_ERR);
        return $handler;
    }

    //burn the first 8192 since we have already parsed it
    $content = fread($parseHandle, 8192);
    while (!feof($parseHandle)) {
        $content = fread($parseHandle, 8192);
        if ($config->get('CDASH_USE_LOCAL_DIRECTORY') && file_exists('local/ctestparser.php')) {
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
    unset($parser);
    fclose($parseHandle);
    unset($parseHandle);

    if ($config->get('CDASH_USE_LOCAL_DIRECTORY') && file_exists('local/ctestparser.php')) {
        $parsingerror = $localParser->EndParsingFile();
    }

    $requeued = false;
    if ($handler instanceof DoneHandler && $handler->shouldRequeue()) {
        require_once 'include/do_submit.php';
        $retry_handler = new RetryHandler($filename);
        $retry_handler->Increment();
        $build = get_build_from_handler($handler);
        $requeued = requeue_submission_file($filename, $projectid, $build->Id,
                                            md5_file($filename), $ip);
    }
    if (!$requeued) {
        check_for_immediate_deletion($filename);
    }
    displayReturnStatus($statusarray);
    $handler->backupFileName = $backup_filename;
    return $handler;
}

function check_for_immediate_deletion($filename)
{
    // Delete this file as soon as its been parsed (or an error occurs)
    // if CDASH_BACKUP_TIMEFRAME is set to '0'.
    $config = Config::getInstance();
    if ($config->get('CDASH_BACKUP_TIMEFRAME') === '0' && is_file($filename)) {
        unlink($filename);
    }
}
