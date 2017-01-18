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
 * Retrieve a file from a particular submission.
 * This includes XML files as well as coverage tarballs, which are temporarily
 * stored in $CDASH_BACKUP_DIRECTORY.
 * These are temporarily stored files which are removed after they've been processed,
 * usually by a queue.
 *
 * Related: deleteSubmissionFile.php
 *
 * GET /getSubmissionFile.php
 * Required Params:
 * filename=[string] Filename to retrieve, must live in tmp_submissions directory
 **/

if (is_array($CDASH_BERNARD_CONSUMERS_WHITELIST) &&
    !in_array($_SERVER['REMOTE_ADDR'], $CDASH_BERNARD_CONSUMERS_WHITELIST)) {
    http_response_code(403);
    exit();
} elseif (isset($_GET['filename'])) {
    $filename = $CDASH_BACKUP_DIRECTORY . '/' . basename($_REQUEST['filename']);

    if (!is_readable($filename)) {
        add_log('couldn\'t find ' . $filename, 'getSubmissionFile', LOG_ERR);
        http_response_code(404);
        exit();
    } else {
        exit(file_get_contents($filename));
    }
} else {
    http_response_code(400);
    exit();
}
