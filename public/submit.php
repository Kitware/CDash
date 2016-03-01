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

// Open the database connection
include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/do_submit.php';
include 'include/clientsubmit.php';
include 'include/version.php';

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db || !pdo_select_db("$CDASH_DB_NAME", $db)) {
    echo '<cdash version="' . $CDASH_VERSION . "\">\n";
    echo " <status>ERROR</status>\n";
    echo " <message>Cannot connect to the database.</message>\n";
    echo "</cdash>\n";
    return;
}
set_time_limit(0);

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

// Catch the fatal errors during submission
register_shutdown_function('PHPErrorHandler', $projectid);

$expected_md5 = isset($_GET['MD5']) ? htmlspecialchars(pdo_real_escape_string($_GET['MD5'])) : '';
$file_path = 'php://input';
$fp = fopen($file_path, 'r');

// If the submission is asynchronous we store in the database
if ($CDASH_ASYNCHRONOUS_SUBMISSION) {
    do_submit_asynchronous($fp, $projectid, $expected_md5);
} else {
    do_submit($fp, $projectid, $expected_md5, true);
}
fclose($fp);
unset($fp);
