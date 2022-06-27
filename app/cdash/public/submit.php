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

use App\Jobs\ProcessSubmission;
use App\Services\UnparsedSubmissionProcessor;

require_once 'include/pdo.php';
require_once 'include/do_submit.php';
require_once 'include/version.php';

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\ServiceContainer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\InputStream;

$config = Config::getInstance();
$service = ServiceContainer::getInstance();

/* Catch any PHP fatal errors */
//
// This is a registered shutdown function (see register_shutdown_function help)
// and gets called at script exit time, regardless of reason for script exit.
// i.e. -- it gets called when a script exits normally, too.
//
global $PHP_ERROR_BUILD_ID;
global $PHP_ERROR_RESOURCE_TYPE;
global $PHP_ERROR_RESOURCE_ID;

if (!function_exists('PHPErrorHandler')) {
    function PHPErrorHandler($projectid)
    {
        if (connection_aborted()) {
            add_log('PHPErrorHandler', "connection_aborted()='" . connection_aborted() . "'", LOG_INFO, $projectid);
            add_log('PHPErrorHandler', "connection_status()='" . connection_status() . "'", LOG_INFO, $projectid);
        }

        if ($error = error_get_last()) {
            switch ($error['type']) {
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    if (strlen($GLOBALS['PHP_ERROR_RESOURCE_TYPE']) == 0) {
                        $GLOBALS['PHP_ERROR_RESOURCE_TYPE'] = 0;
                    }
                    if (strlen($GLOBALS['PHP_ERROR_BUILD_ID']) == 0) {
                        $GLOBALS['PHP_ERROR_BUILD_ID'] = 0;
                    }
                    if (strlen($GLOBALS['PHP_ERROR_RESOURCE_ID']) == 0) {
                        $GLOBALS['PHP_ERROR_RESOURCE_ID'] = 0;
                    }

                    add_log('Fatal error:' . $error['message'], $error['file'] . ' (' . $error['line'] . ')',
                        LOG_ERR, $projectid, $GLOBALS['PHP_ERROR_BUILD_ID'],
                        $GLOBALS['PHP_ERROR_RESOURCE_TYPE'], $GLOBALS['PHP_ERROR_RESOURCE_ID']);
                    exit();  // stop the script
                    break;
            }
        }
    }
}

// Helper function to display the message
if (!function_exists('displayReturnStatus')) {
    function displayReturnStatus($statusarray, $response_code)
    {
        // NOTE: we can't use Laravel's response() helper function
        // until CTest learns how to properly parse the XML out of it.
        http_response_code($response_code);

        $version = config('cdash.version');
        echo "<cdash version=\"{$version}\">\n";
        foreach ($statusarray as $key => $value) {
            echo "  <{$key}>{$value}</{$key}>\n";
        }
        echo "</cdash>\n";

        return $response_code;
    }
}

$statusarray = [];

@set_time_limit(0);

// If we have a POST or PUT we defer to the unparsed submission processor.
if (isset($_POST['project'])) {
    $processor = new UnparsedSubmissionProcessor();
    return $processor->postSubmit();
}
if (isset($_GET['buildid'])) {
    $processor = new UnparsedSubmissionProcessor();
    return $processor->putSubmitFile();
}

$projectname = $_GET['project'];
$expected_md5 = isset($_GET['MD5']) ? htmlspecialchars($_GET['MD5']) : '';

// Save the incoming file in the inbox directory.
$filename = "{$projectname}_" . \Illuminate\Support\Str::uuid()->toString() . '.xml';
$fp = request()->getContent(true);
if (!Storage::put("inbox/{$filename}", $fp)) {
    \Log::error("Failed to save submission to inbox for $projectname (md5=$expected_md5)");
    $statusarray['status'] = 'ERROR';
    $statusarray['message'] = 'Failed to save submission file.';
    return displayReturnStatus($statusarray, Response::HTTP_INTERNAL_SERVER_ERROR);
}

// Check that the md5sum of the file matches what we were told to expect.
if ($expected_md5) {
    $md5sum = md5_file(Storage::path("inbox/{$filename}"));
    if ($md5sum != $expected_md5) {
        Storage::delete("inbox/{$filename}");
        $statusarray['status'] = 'ERROR';
        $statusarray['message'] = "md5 mismatch. expected: {$expected_md5}, received: {$md5sum}";
        return displayReturnStatus($statusarray, Response::HTTP_BAD_REQUEST);
    }
}

// Check if we can connect to the database before proceeding any further.
try {
    $pdo = \DB::connection()->getPdo();
} catch (\Exception $e) {
    $statusarray['status'] = 'ERROR';
    $statusarray['message'] = 'Cannot connect to the database.';
    return displayReturnStatus($statusarray, Response::HTTP_SERVICE_UNAVAILABLE);
}

// Get some more info about this project.
$projectid = null;
$stmt = $pdo->prepare(
    'SELECT id, authenticatesubmissions FROM project WHERE name = ?');
if (pdo_execute($stmt, [$projectname])) {
    $row = $stmt->fetch();
    if ($row) {
        $projectid = $row['id'];
        $authenticate_submissions = $row['authenticatesubmissions'];
    }
}

// Return an error message if this is not a valid project.
if (!$projectid) {
    \Log::info("Not a valid project. projectname: $projectname in submit.php");
    $statusarray['status'] = 'ERROR';
    $statusarray['message'] = 'Not a valid project.';
    return displayReturnStatus($statusarray, Response::HTTP_NOT_FOUND);
}

// Catch the fatal errors during submission
register_shutdown_function('PHPErrorHandler', $projectid);

// Remove some old builds if the project has too many.
$project = new Project();
$project->Name = $projectname;
$project->Id = $projectid;
$project->CheckForTooManyBuilds();

// Check for valid authentication token if this project requires one.
if ($authenticate_submissions && !valid_token_for_submission($projectid)) {
    $statusarray['status'] = 'ERROR';
    $statusarray['message'] = 'Invalid Token';
    return displayReturnStatus($statusarray, Response::HTTP_FORBIDDEN);
}

// Check if CTest provided us enough info to assign a buildid.
$pendingSubmissions = $service->create(PendingSubmissions::class);
$buildid = null;
if (isset($_GET['build']) && isset($_GET['site']) && isset($_GET['stamp'])) {
    $build = $service->create(Build::class);
    $build->Name = pdo_real_escape_string($_GET['build']);
    $build->ProjectId = $projectid;
    $build->SetStamp(pdo_real_escape_string($_GET['stamp']));
    $build->StartTime = gmdate(FMT_DATETIME);
    $build->SubmitTime = $build->StartTime;

    if (isset($_GET['subproject'])) {
        $build->SetSubProject(pdo_real_escape_string($_GET['subproject']));
    }

    $site = $service->create(Site::class);
    $site->Name = pdo_real_escape_string($_GET['site']);
    $site->Insert();
    $build->SiteId = $site->Id;
    $pendingSubmissions->Build = $build;

    if ($build->AddBuild()) {
        // Insert row to keep track of how many submissions are waiting to be
        // processed for this build. This value will be incremented
        // (and decremented) later on.
        $pendingSubmissions->NumFiles = 0;
        $pendingSubmissions->Save();
    }
    $buildid = $build->Id;
}

if (!is_null($buildid)) {
    $pendingSubmissions->Increment();
}
ProcessSubmission::dispatch($filename, $projectid, $buildid, $expected_md5);
fclose($fp);
unset($fp);

$statusarray['status'] = 'OK';
$statusarray['message'] = '';
if (!is_null($buildid)) {
    $statusarray['buildId'] = $buildid;
}
return displayReturnStatus($statusarray, Response::HTTP_OK);
