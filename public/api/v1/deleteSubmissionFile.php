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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';

global $CDASH_BACKUP_DIRECTORY, $CDASH_BERNARD_CONSUMERS_WHITELIST;

/**
 * Delete the temporary file related to a particular submission.
 *
 * Related: getSubmissionFile.php
 *
 * DELETE /deleteSubmissionFile.php
 * Required Params:
 * filename=[string] Filename to delete, must live in tmp_submissions directory
 **/

if (is_array($CDASH_BERNARD_CONSUMERS_WHITELIST) &&
    !in_array($_SERVER['REMOTE_ADDR'], $CDASH_BERNARD_CONSUMERS_WHITELIST)) {
    http_response_code(403);
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_REQUEST['filename'])) {
    $filename = $CDASH_BACKUP_DIRECTORY . '/' . basename($_REQUEST['filename']);

    if (file_exists($filename)) {
        $deleted = @unlink($filename);

        if (!$deleted) {
            http_response_code(500);
            exit();
        }
    }
} else {
    http_response_code(400);
    exit();
}
