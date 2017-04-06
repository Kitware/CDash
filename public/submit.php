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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Ramsey\Uuid\Uuid;

// Open the database connection
include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/do_submit.php';
include 'include/clientsubmit.php';
include 'include/version.php';
require_once 'models/project.php';

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db || !pdo_select_db("$CDASH_DB_NAME", $db)) {
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

$projectname = htmlspecialchars(pdo_real_escape_string($_GET['project']));
$projectid = get_project_id($projectname);

// If not a valid project we return
if ($projectid == -1) {
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

$expected_md5 = isset($_GET['MD5']) ? htmlspecialchars(pdo_real_escape_string($_GET['MD5'])) : '';
$file_path = 'php://input';
$fp = fopen($file_path, 'r');

if ($CDASH_BERNARD_SUBMISSION) {
    // @todo what serializer should be used?
    $factory = new PersistentFactory($CDASH_BERNARD_DRIVER, new Serializer());
    $producer = new Producer($factory, new EventDispatcher());

    $buildSubmissionId = Uuid::uuid4()->toString();
    $destinationFilename = $CDASH_BACKUP_DIRECTORY . '/' . $buildSubmissionId . '.xml';

    if (copy('php://input', $destinationFilename)) {
        $producer->produce(new DefaultMessage('DoSubmit', array(
            'buildsubmissionid' => $buildSubmissionId,
            'filename' => $destinationFilename,
            'projectid' => $projectid,
            'expected_md5' => $expected_md5,
            'do_checksum' => true,
            'submission_id' => 0, // The submit endpoint does not allow a submission_id
            'submission_ip' => $_SERVER['REMOTE_ADDR'])));
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
