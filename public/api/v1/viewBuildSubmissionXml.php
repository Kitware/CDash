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

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';

global $CDASH_BACKUP_DIRECTORY, $CDASH_BERNARD_CONSUMERS_WHITELIST;

/**
 * Retrieve the XML of a particular build submission.
 *
 * GET /viewBuildSubmissionXml.php
 * Required Params:
 * buildsubmissionid=[string] UUID of a build submission
 **/

if (is_array($CDASH_BERNARD_CONSUMERS_WHITELIST) &&
    !in_array($_SERVER['REMOTE_ADDR'], $CDASH_BERNARD_CONSUMERS_WHITELIST)) {
    header('HTTP/1.1 403 Forbidden');
    exit();
} else if (isset($_GET['buildsubmissionid']) &&
    preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $_GET['buildsubmissionid'])) {
    $filename = $CDASH_BACKUP_DIRECTORY . '/' . $_GET['buildsubmissionid'] . '.xml';

    if (!is_readable($filename)) {
        header('HTTP/1.1 404 Not Found');
        exit();
    } else {
        exit(file_get_contents($filename));
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    exit();
}
