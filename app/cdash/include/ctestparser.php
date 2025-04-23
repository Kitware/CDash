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

use App\Exceptions\BadSubmissionException;
use App\Models\BuildFile;
use App\Utils\SubmissionUtils;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Determine the descriptive filename for a submission file. */
function generateBackupFileName($projectname, $subprojectname, $buildname,
    $sitename, $stamp, $fileNameWithExt)
{
    // Generate a timestamp to include in the filename.
    $currenttimestamp = microtime(true) * 100;

    // Escape the sitename, buildname, and projectname.
    $sitename_escaped = preg_replace('/[^\w\-~_]+/u', '-', $sitename);
    $buildname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $buildname);
    $projectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $projectname);
    $subprojectname_escaped = preg_replace('/[^\w\-~_]+/u', '-', $subprojectname ?? '');

    // Separate the extension from the filename.
    $ext = '.' . pathinfo($fileNameWithExt, PATHINFO_EXTENSION);
    $file = pathinfo($fileNameWithExt, PATHINFO_FILENAME);

    $filename = $projectname_escaped . '_';
    if ($file != 'Project') {
        // Project.xml files aren't associated with a particular build, so we
        // only record the site and buildname for other types of submissions.
        $filename .= $subprojectname_escaped . '_' . $sitename_escaped . '_' . $buildname_escaped . '_' . $stamp . '_';
    }
    $filename .= $currenttimestamp . '_' . $file . $ext;

    // Make sure we don't generate a filename that's too long, otherwise
    // fopen() will fail later.
    $maxChars = 250;
    $textLength = strlen($filename);
    if ($textLength > $maxChars) {
        $filename = substr_replace($filename, '', $maxChars / 2, $textLength - $maxChars);
    }

    return $filename;
}

/** Function to handle new style submissions via HTTP PUT */
function parse_put_submission(string $filename, int $projectid, ?string $expected_md5, ?int $buildid): AbstractSubmissionHandler|false
{
    $db = Database::getInstance();

    if ($expected_md5 === null) {
        return false;
    }

    if ($buildid === null) {
        $buildfile = BuildFile::where(['md5' => $expected_md5])->first();
    } else {
        $buildfile = BuildFile::where(['buildid' => $buildid, 'md5' => $expected_md5])->first();
    }
    if ($buildfile === null) {
        return false;
    }

    // Save a backup file for this submission.
    $row = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=? LIMIT 1', [$projectid]);
    if (empty($row)) {
        return false;
    }
    $projectname = $row['name'];

    $row = $db->executePreparedSingleRow('SELECT name, stamp FROM build WHERE id=? LIMIT 1', [$buildfile->buildid]);
    if (empty($row)) {
        return false;
    }
    $buildname = $row['name'];
    $stamp = $row['stamp'];

    $row = $db->executePreparedSingleRow('SELECT name FROM site WHERE id=
                                             (SELECT siteid FROM build WHERE id=?) LIMIT 1', [$buildfile->buildid]);
    if (empty($row)) {
        return false;
    }
    $sitename = $row['name'];

    // Include the handler file for this type of submission.
    $include_file = 'xml_handlers/' . $buildfile->type . '_handler.php';
    $valid_types = [
        'BazelJSON',
        'BuildPropertiesJSON',
        'GcovTar',
        'JavaJSONTar',
        'JSCoverTar',
        'OpenCoverTar',
        'SubProjectDirectories',
    ];
    if (stream_resolve_include_path($include_file) === false || !in_array($buildfile->type, $valid_types, true)) {
        Log::error("Project: $projectid.  No handler include file for {$buildfile->type} (tried $include_file)");
        $buildfile->delete();
        return false;
    }
    require_once $include_file;

    // Instantiate the handler.
    $className = $buildfile->type . 'Handler';
    if (!class_exists($className)) {
        Log::error("Project: $projectid.  No handler class for {$buildfile->type}");
        $buildfile->delete();
        return false;
    }

    $build = new Build();
    $build->Id = $buildfile->buildid;
    $handler = new $className($build);

    // Make sure the file exists.
    if (!Storage::exists($filename)) {
        Log::error("Failed to locate file {$filename}");
        return false;
    }

    // Parse the file.
    if ($handler->Parse($filename) === false) {
        Log::error("Failed to parse file {$filename}");
        return false;
    }

    $buildfile->delete();

    $handler->backupFileName = generateBackupFileName($projectname, '', $buildname, $sitename, $stamp, $buildfile->filename);

    return $handler;
}

/**
 * Main function to parse the incoming xml from ctest
 *
 * @throws BadSubmissionException
 */
function ctest_parse($filehandle, string $filename, $projectid, $expected_md5 = '', ?int $buildid = null): AbstractSubmissionHandler|false
{
    // Try to get the IP of the build.
    $ip = null;
    if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $Project = new Project();
    $Project->Id = $projectid;
    $xml_info = [];
    // Figure out what type of XML file this is.
    $xml_info = SubmissionUtils::get_xml_type($filehandle, $filename);

    $handler_ref = $xml_info['xml_handler'];
    $file = $xml_info['xml_type'];
    $handler = isset($handler_ref) ? new $handler_ref($Project) : null;

    rewind($filehandle);
    $content = fread($filehandle, 8192);

    if ($handler == null) {
        // TODO: Add as much context as possible to this message
        Log::error('error: could not create handler based on xml content');

        $Project->SendEmailToAdmin('Cannot create handler based on XML content',
            'An XML submission from ' . $ip . ' to the project ' . get_project_name($projectid) . ' cannot be parsed. The content of the file is as follows: ' . $content);

        abort(400, 'Could not create handler based on xml content');
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
    $db = Database::getInstance();
    $rows = $db->executePrepared("SELECT id FROM blockbuild WHERE projectid=?
                                      AND (buildname='' OR buildname=?)
                                      AND (sitename='' OR sitename=?)
                                      AND (ipaddress='' OR ipaddress=?)",
        [$projectid, $buildname, $sitename, $ip]);
    if (!empty($rows)) {
        echo 'The submission is banned from this CDash server.';
        Log::info('Blocked prohibited submission.', [
            'projectid' => $projectid,
            'build' => $buildname,
            'site' => $sitename,
            'ip' => $ip,
        ]);
        return false;
    }

    while (!feof($filehandle)) {
        $content = fread($filehandle, 8192);
        xml_parse($parser, $content, false);
    }
    xml_parse($parser, '', true);
    xml_parser_free($parser);
    unset($parser);

    // Generate a pretty, "relative to storage" filepath and store it in the handler.
    $backup_filename = generateBackupFileName(
        $projectname, $subprojectname, $buildname, $sitename, $stamp, $file . '.xml');
    $handler->backupFileName = $backup_filename;

    return $handler;
}
