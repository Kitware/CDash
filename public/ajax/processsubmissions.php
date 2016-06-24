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

require_once dirname(dirname(__DIR__)) . '/config/config.php';

// Only used to setup the parallel submissions test case.
global $CDASH_DO_NOT_PROCESS_SUBMISSIONS;
if ($CDASH_DO_NOT_PROCESS_SUBMISSIONS) {
    exit(0);
}

require_once 'include/common.php';
require_once 'include/do_submit.php';
require_once 'include/fnProcessFile.php';
require_once 'include/pdo.php';
require_once 'include/submission_functions.php';

ob_start();
@set_time_limit(0);
ignore_user_abort(true);

// Parse script arguments. This file can be run in a web browser or called
// from the php command line executable.
//
// When called by command line, argv[1] is the projectid, and argv[2] may
// optionally be "--force" to force acquiring the processing lock.
// If "--force" is given, $force is 1, otherwise it's 0.
//
// When called by http, use "?projectid=1&force=1" to pass the info through
// php's _GET array. Use value 0 or 1 for force. If omitted, $force is 0.
//
echo '<pre>';
echo "begin processSubmissions.php\n";

$force = 0;

if (isset($argc) && $argc > 1) {
    echo "argc, context is php command-line invocation...\n";
    echo "argc='" . $argc . "'\n";
    for ($i = 0; $i < $argc; ++$i) {
        echo 'argv[' . $i . "]='" . $argv[$i] . "'\n";

        if ($argv[$i] == '--force') {
            $force = 1;
        }
    }

    $projectid = $argv[1];
} else {
    echo "no argc, context is web browser or some other non-command-line...\n";
    @$projectid = $_GET['projectid'];
    if ($projectid != null) {
        $projectid = pdo_real_escape_numeric($projectid);
    }

    @$force = $_GET['force'];
    @$pid = $_GET['pid'];
}

if (!is_numeric($projectid)) {
    echo "projectid/argv[1] should be a number\n";
    echo '</pre>';
    add_log("projectid '" . $projectid . "' should be a number",
        'ProcessSubmission',
        LOG_ERR, $projectid);
    return;
}

$multi = false;
if (!$pid) {
    $pid = getmypid();
} else {
    // if pid was specified then this is a parallel request.
    $multi = true;
}

// Catch any fatal errors during processing
//
register_shutdown_function('ProcessSubmissionsErrorHandler', $projectid, $pid);

echo "projectid='$projectid'\n";
echo "pid='$pid'\n";
echo "force='$force'\n";

if ($multi) {
    // multi processing, so lock was acquired in do_submit.php
    $lockAcquired = true;
} else {
    $lockAcquired = AcquireProcessingLock($projectid, $force, $pid);
}

if ($lockAcquired) {
    echo "AcquireProcessingLock returned true\n";

    ResetApparentlyStalledSubmissions($projectid);
    echo "Done with ResetApparentlyStalledSubmissions\n";

    ProcessSubmissions($projectid, $pid, $multi);
    echo "Done with ProcessSubmissions\n";

    DeleteOldSubmissionRecords($projectid);
    echo "Done with DeleteOldSubmissionRecords\n";

    if (ReleaseProcessingLock($projectid, $pid, $multi)) {
        echo "ReleasedProcessingLock returned true\n";
    } else {
        echo "ReleasedProcessingLock returned false\n";
    }
} else {
    echo "AcquireProcessingLock returned false\n";
    echo "Another process is already processing or there was a locking error\n";
}

echo "end processSubmissions.php\n";
echo '</pre>';

ob_end_flush();
