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
use Bernard\Message\DefaultMessage;
use Bernard\Producer;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Serializer;
use CDash\Middleware\Queue;
use CDash\Middleware\Queue\DriverFactory as QueueDriverFactory;
use CDash\Middleware\Queue\SubmissionService;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once 'include/pdo.php';
require_once 'include/do_submit.php';
require_once 'include/version.php';

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\ServiceContainer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\InputStream;

$config = Config::getInstance();
$service = ServiceContainer::getInstance();

// Check if we can connect to the database.
try {
    $pdo = \DB::connection()->getPdo();
} catch (\Exception $e) {
    $message = "<cdash version=\"{$config->get('CDASH_VERSION')}\">
    <status>ERROR</status>
    <message>Cannot connect to the database./message>
    </cdash>";
    return response($message, Response::HTTP_SERVICE_UNAVAILABLE);
}

@set_time_limit(0);

// If we have a POST we forward to the new submission process
if (isset($_POST['project'])) {
    return post_submit();
}
if (isset($_GET['buildid'])) {
    return put_submit_file();
}

$stmt = $pdo->prepare(
    'SELECT id, authenticatesubmissions FROM project WHERE name = ?');

$projectid = null;
$projectname = $_GET['project'];
if (pdo_execute($stmt, [$projectname])) {
    $row = $stmt->fetch();
    if ($row) {
        $projectid = $row['id'];
        $authenticate_submissions = $row['authenticatesubmissions'];
    }
}

// If not a valid project we return
if (!$projectid) {
    echo '<cdash version="' . $config->get('CDASH_VERSION') . "\">\n";
    echo " <status>ERROR</status>\n";
    echo " <message>Not a valid project.</message>\n";
    echo "</cdash>\n";
    add_log('Not a valid project. projectname: ' . $projectname, 'global:submit.php');
    return;
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
    $message = "<cdash version=\"{$config->get('CDASH_VERSION')}\">
    <status>ERROR</status>
    <message>Invalid Token</message>
    </cdash>";
    return response($message, Response::HTTP_FORBIDDEN);
}

$expected_md5 = isset($_GET['MD5']) ? htmlspecialchars(pdo_real_escape_string($_GET['MD5'])) : '';

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

// Save the incoming file in the inbox directory.
$filename = "inbox/{$projectname}_" . \Illuminate\Support\Str::uuid()->toString() . '.xml';
$fp = request()->getContent(true);
if (!Storage::put($filename, $fp)) {
    \Log::error("Failed to save submission to inbox for $projectname (md5=$expected_md5)");
    $message = '<cdash version="' . config('cdash.version') . ">
        <status>ERROR</status>
        <message>Failed to save submission file.</message>
        </cdash>";
    return response($message, Response::HTTP_INTERNAL_SERVER_ERROR);
}

if ($config->get('CDASH_BERNARD_SUBMISSION')) {
    // Use a message queue for asynchronous submission processing.
    do_submit_queue($fp, $projectid, $buildid, $expected_md5);
} else {
    if (!is_null($buildid)) {
        $pendingSubmissions->Increment();
    }
    ProcessSubmission::dispatch($filename, $projectid, $buildid, $expected_md5);
}
fclose($fp);
unset($fp);
