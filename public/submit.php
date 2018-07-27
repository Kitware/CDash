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

use Bernard\Message\DefaultMessage;
use Bernard\Producer;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Serializer;
use CDash\Middleware\Queue;
use CDash\Middleware\Queue\DriverFactory as QueueDriverFactory;
use CDash\Middleware\Queue\SubmissionService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Ramsey\Uuid\Uuid;

// Open the database connection
include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/do_submit.php';
include 'include/clientsubmit.php';
include 'include/version.php';

use CDash\Model\Project;

$pdo = get_link_identifier()->getPdo();
if (!$pdo) {
    echo '<cdash version="' . $CDASH_VERSION . "\">\n";
    echo " <status>ERROR</status>\n";
    echo " <message>Cannot connect to the database.</message>\n";
    echo "</cdash>\n";
    return;
}
@set_time_limit(0);

// Send to the client submit
if (client_submit()) {
    return;
}

// If we have a POST we forward to the new submission process
if (isset($_POST['project'])) {
    post_submit();
    return;
}
if (isset($_GET['buildid'])) {
    put_submit_file();
    return;
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
    echo '<cdash version="' . $CDASH_VERSION . "\">\n";
    echo " <status>ERROR</status>\n";
    echo " <message>Not a valid project.</message>\n";
    echo "</cdash>\n";
    add_log('Not a valid project. projectname: ' . $projectname, 'global:submit.php');
    return;
}

// Do not process this submission if the project has too many builds.
$project = new Project();
$project->Name = $projectname;
$project->Id = $projectid;
$message = '';
if ($project->HasTooManyBuilds($message)) {
    echo '<cdash version="' . $CDASH_VERSION . "\">\n";
    echo " <status>ERROR</status>\n";
    echo " <message>$message</message>\n";
    echo "</cdash>\n";
    return;
}

// Catch the fatal errors during submission
register_shutdown_function('PHPErrorHandler', $projectid);

// Check for valid authentication token if this project requires one.
if ($authenticate_submissions && !valid_token_for_submission($projectid)) {
    return;
}

$expected_md5 = isset($_GET['MD5']) ? htmlspecialchars(pdo_real_escape_string($_GET['MD5'])) : '';
$file_path = 'php://input';
$fp = fopen($file_path, 'r');

if ($CDASH_BERNARD_SUBMISSION) {
    $buildSubmissionId = Uuid::uuid4()->toString();
    $destinationFilename = $CDASH_BACKUP_DIRECTORY . '/' . $buildSubmissionId . '.xml';

    if (copy('php://input', $destinationFilename)) {
        $driver = QueueDriverFactory::create();
        $queue = new Queue($driver);

        $message = SubmissionService::createMessage([
            'file' => $destinationFilename,
            'project' => $projectid,
            'md5' => $expected_md5,
            'checksum' => true,
        ]);

        $queue->produce($message);

        echo '<cdash version="' . $CDASH_VERSION . "\">\n";
        echo " <status>OK</status>\n";
        echo " <message>Build submitted successfully.</message>\n";
        echo " <submissionId>$buildSubmissionId</submissionId>\n";
        echo "</cdash>\n";
    } else {
        add_log('Failed to copy build submission XML', 'global:submit.php', LOG_ERR);
        header('HTTP/1.1 500 Internal Server Error');
        echo '<cdash version="' . $CDASH_VERSION . "\">\n";
        echo " <status>ERROR</status>\n";
        echo " <message>Failed to copy build submission XML.</message>\n";
        echo "</cdash>\n";
    }
} elseif ($CDASH_ASYNCHRONOUS_SUBMISSION) {
    // If the submission is asynchronous we store in the database
    do_submit_asynchronous($fp, $projectid, $expected_md5);
} else {
    do_submit($fp, $projectid, $expected_md5, true);
}
fclose($fp);
unset($fp);
