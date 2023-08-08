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

namespace CDash\Api\v1\GetSubmissionFile;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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

if (!config('cdash.remote_workers')) {
    return response('This feature is disabled', Response::HTTP_CONFLICT);
}

if (isset($_GET['filename'])) {
    $filename = Storage::path('inbox') . '/' . basename($_REQUEST['filename']);
    if (!is_readable($filename)) {
        return response('Not found', Response::HTTP_NOT_FOUND);
    } else {
        exit(file_get_contents($filename));
    }
} else {
    return response('Bad request', Response::HTTP_BAD_REQUEST);
}
