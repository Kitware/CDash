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
require_once 'include/pdo.php';
include_once 'include/common.php';

use CDash\Config;
use Illuminate\Support\Facades\Storage;

$config = Config::getInstance();

/**
 * Retrieve a file from a particular submission.
 * This includes XML files as well as coverage tarballs, which are temporarily
 * stored in storage/app/inbox.
 * These are temporarily stored files which are removed after they've been processed,
 * usually by a queue.
 *
 * Related: deleteSubmissionFile.php
 *
 * GET /getSubmissionFile.php
 * Required Params:
 * filename=[string] Filename to retrieve, must live in tmp_submissions directory
 **/

if (isset($_GET['filename'])) {
    $filename = Storage::path('inbox') . '/' . basename($_REQUEST['filename']);

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
