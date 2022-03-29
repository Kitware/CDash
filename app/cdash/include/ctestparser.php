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
require_once 'xml_handlers/testing_junit_handler.php';
require_once 'xml_handlers/coverage_junit_handler.php';

use App\Jobs\ProcessSubmission;
use CDash\Config;
use CDash\Model\Build;
use CDash\Model\BuildFile;
use CDash\Model\Project;
use Illuminate\Support\Facades\Storage;

class CDashParseException extends RuntimeException
{
}

/** Determine the descriptive filename for a submission file.
  * Called by writeBackupFile().
  **/
function generateBackupFileName($projectname, $subprojectname, $buildname,
                                $sitename, $stamp, $fileNameWithExt)
{
    // Generate a timestamp to include in the filename.
    $currenttimestamp = microtime(true) * 100;

    // Escape the sitename, buildname, and projectname.
    $sitename_escaped = preg_replace('/[^\w\-~_]+/u', '-', $sitename);
    $buildname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $buildname);
    $projectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $projectname);
    $subprojectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $subprojectname);

    // Separate the extension from the filename.
    $ext = '.' . pathinfo($fileNameWithExt, PATHINFO_EXTENSION);
    $file = pathinfo($fileNameWithExt, PATHINFO_FILENAME);

    $filename = $projectname_escaped . '_';
    if ($file != 'Project') {
        // Project.xml files aren't associated with a particular build, so we
        // only record the site and buildname for other types of submissions.
        $filename .= $subprojectname_escaped . '_' . $sitename_escaped . '_' . $buildname_escaped . '_' . $stamp . '_';
    }
    $filename .=  $currenttimestamp . '_' . $file . $ext;

    // Make sure we don't generate a filename that's too long, otherwise
    // fopen() will fail later.
    $maxChars = 250;
    $textLength = strlen($filename);
    if ($textLength > $maxChars) {
        $filename = substr_replace($filename, '', $maxChars/2, $textLength-$maxChars);
    }

    return $filename;
}

/** Safely write a backup file, taking care to avoid writing two files
  * to the same destination. Called by writeBackupFile().
  **/
function safelyWriteBackupFile($filehandler, $content, $filename)
{
    // If the file exists we append a number until we get a nonexistent file.
    $filepath = Storage::path($filename);
    $inboxDir = Storage::path('inbox');
    $got_lock = false;
    $i = 1;
    while (!$got_lock) {
        $lockfilename = Storage::path("{$filename}.lock");
        $lockfp = fopen($lockfilename, 'w');
        flock($lockfp, LOCK_EX | LOCK_NB, $wouldblock);
        if ($wouldblock) {
            $path_parts = pathinfo($filepath);
            $filepath = $path_parts['dirname'] . '/' . $path_parts['filename'] . "_$i." . $path_parts['extension'];
            $i++;
        } else {
            $got_lock = true;
            // realpath() always returns false for Google Cloud Storage.
            if (realpath($inboxDir) !== false) {
                // Make sure the file is in the right directory.
                $pos = strpos(realpath(dirname($filepath)), realpath($inboxDir));
                if ($pos === false || $pos != 0) {
                    \Log::error("File cannot be stored in inbox directory: $filepath (realpath = " . realpath($inboxDir) . ')');
                    flock($lockfp, LOCK_UN);
                    unlink($lockfilename);
                    return false;
                }
            }
        }
        flock($lockfp, LOCK_UN);
        if (file_exists($lockfilename)) {
            unlink($lockfilename);
        }
    }

    // Write the file.
    if (!Storage::put($filename, $filehandler)) {
        \Log::error("Cannot write to file ($filename)");
        return false;
    }
    return $filename;
}

/** Function used to write a submitted file to our backup directory with a
 * descriptive name. */
function writeBackupFile($filehandler, $content, $projectname, $subprojectname,
                         $buildname, $sitename, $stamp, $fileNameWithExt)
{
    $filename = 'inbox/';
    $filename .= generateBackupFileName($projectname, $subprojectname, $buildname, $sitename, $stamp, $fileNameWithExt);
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
    if (empty($row)) {
        return false;
    }
    $buildname = $row['name'];
    $stamp = $row['stamp'];

    $row = pdo_single_row_query(
        "SELECT name FROM site WHERE id=
            (SELECT siteid FROM build WHERE id=$buildid)");
    $sitename = $row['name'];

    // Work directly off the open file handle.
    $meta_data = stream_get_meta_data($filehandler);
    $filename = $meta_data['uri'];

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
        $buildfile->Delete();
        return true;
    }
    require_once $include_file;

    // Instantiate the handler.
    $className = $type . 'Handler';
    if (!class_exists($className)) {
        add_log("No handler class for $type", 'parse_put_submission',
            LOG_ERR, $projectid);
        $buildfile->Delete();
        return true;
    }
    $handler = new $className($buildid);

    // Parse the file.
    if (file_exists($filename)) {
        $filepath = $filename;
    } elseif (Storage::exists($filename)) {
        $filepath = Storage::path($filename);
    } else {
        throw new CDashParseException('Failed to locate file ' . $filename);
    }

    if ($handler->Parse($filepath) === false) {
        throw new CDashParseException('Failed to parse file ' . $filename);
    }

    $buildfile->Delete();

    $handler->backupFileName = generateBackupFileName(
        $projectname, '', $buildname, $sitename, $stamp, $buildfile_row['filename']);

    return $handler;
}

/** Main function to parse the incoming xml from ctest */
function ctest_parse($filehandle, $projectid, $buildid = null,
                     $expected_md5 = '')
{
    require_once 'include/common.php';
    include 'include/version.php';

    // Check if this is a new style PUT submission.
    try {
        $handler = parse_put_submission($filehandle, $projectid, $expected_md5);
        if ($handler) {
            return $handler;
        }
    } catch (CDashParseException $e) {
        add_log($e->getMessage(), 'ctest_parse', LOG_ERR);
        return false;
    }

    // Try to get the IP of the build.
    $ip = null;
    $config = Config::getInstance();
    if ($config->get('CDASH_REMOTE_ADDR')) {
        $ip = $config->get('CDASH_REMOTE_ADDR');
    } elseif (array_key_exists('REMOTE_ADDR', $_SERVER)) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // Figure out what type of XML file this is.
    $handler = null;
    $file = '';
    while (is_null($handler) && !feof($filehandle)) {
        $content = fread($filehandle, 8192);
        if (strpos($content, '<Update') !== false) {
            // Should be first otherwise confused with Build
            $handler = new UpdateHandler($projectid);
            $file = 'Update';
        } elseif (strpos($content, '<Build') !== false) {
            $handler = new BuildHandler($projectid);
            $file = 'Build';
        } elseif (strpos($content, '<Configure') !== false) {
            $handler = new ConfigureHandler($projectid);
            $file = 'Configure';
        } elseif (strpos($content, '<Testing') !== false) {
            $handler = new TestingHandler($projectid);
            $file = 'Test';
        } elseif (strpos($content, '<CoverageLog') !== false) {
            // Should be before coverage

            $handler = new CoverageLogHandler($projectid);
            $file = 'CoverageLog';
        } elseif (strpos($content, '<Coverage') !== false) {
            $handler = new CoverageHandler($projectid);
            $file = 'Coverage';
        } elseif (strpos($content, '<report') !== false) {
            $handler = new CoverageJUnitHandler($projectid);
            $file = 'Coverage';
        } elseif (strpos($content, '<Notes') !== false) {
            $handler = new NoteHandler($projectid);
            $file = 'Notes';
        } elseif (strpos($content, '<DynamicAnalysis') !== false) {
            $handler = new DynamicAnalysisHandler($projectid);
            $file = 'DynamicAnalysis';
        } elseif (strpos($content, '<Project') !== false) {
            $handler = new ProjectHandler($projectid);
            $file = 'Project';
        } elseif (strpos($content, '<Upload') !== false) {
            $handler = new UploadHandler($projectid);
            $file = 'Upload';
        } elseif (strpos($content, '<testsuite') !== false) {
            $handler = new TestingJUnitHandler($projectid);
            $file = 'Test';
        } elseif (strpos($content, '<Done') !== false) {
            $handler = new DoneHandler($projectid);
            $file = 'Done';
        }
    }

    rewind($filehandle);
    $content = fread($filehandle, 8192);

    if ($handler == null) {
        echo 'no handler found';
        add_log('error: could not create handler based on xml content', 'ctest_parse', LOG_ERR);
        $Project = new Project();
        $Project->Id = $projectid;

        $Project->SendEmailToAdmin('Cannot create handler based on XML content',
            'An XML submission from ' . $ip . ' to the project ' . get_project_name($projectid) . ' cannot be parsed. The content of the file is as follow: ' . $content);
        return false;
    }

    $parser = xml_parser_create();
    xml_set_element_handler($parser, [$handler, 'startElement'], [$handler, 'endElement']);
    xml_set_character_data_handler($parser, [$handler, 'text']);
    xml_parse($parser, $content, false);

    $projectname = get_project_name($projectid);

    $sitename = '';
    $buildname = '';
    $subprojectname = '';
    $stamp = '';
    if ($file != 'Project') {
        // projects don't have some of these fields.

        $sitename = $handler->getSiteName();
        $buildname = $handler->getBuildName();
        $subprojectname = $handler->getSubProjectName();
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

    while (!feof($filehandle)) {
        $content = fread($filehandle, 8192);
        xml_parse($parser, $content, false);
    }
    xml_parse($parser, null, true);
    xml_parser_free($parser);
    unset($parser);

    // Generate a pretty, "relative to storage" filepath and store it in the handler.
    $backup_filename = generateBackupFileName(
            $projectname, $subprojectname, $buildname, $sitename, $stamp, $file . '.xml');
    $handler->backupFileName = $backup_filename;

    return $handler;
}
