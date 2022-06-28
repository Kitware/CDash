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
use CDash\Model\PendingSubmissions;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requeue the file related to a particular submission.
 *
 * Related: getSubmissionFile.php
 * Related: deleteSubmissionFile.php
 *
 * DELETE /requeueSubmissionFile.php
 * Required Params:
 * filename=[string] Filename to requeue, must live in the 'inprogress' directory
 **/

if (!config('cdash.remote_workers')) {
    return response('This feature is disabled', Response::HTTP_CONFLICT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['filename']) && isset($_REQUEST['buildid']) && isset($_REQUEST['projectid'])) {
    $filename = $_REQUEST['filename'];
    $buildid = $_REQUEST['buildid'];
    $projectid = $_REQUEST['projectid'];
    if (!Storage::exists("inprogress/{$filename}")) {
        return response('File not found', Response::HTTP_NOT_FOUND);
    }

    $retry_handler = new \RetryHandler(Storage::path("inprogress/{$filename}"));
    $retry_handler->Increment();

    // Move file back to inbox.
    Storage::move("inprogress/{$filename}", "inbox/{$filename}");

    // Requeue the file.
    PendingSubmissions::IncrementForBuildId($buildid);
    ProcessSubmission::dispatch($filename, $projectid, $buildid, md5_file(Storage::path("inbox/{$filename}")));
    return response('OK', Response::HTTP_OK);
} else {
    return response('Bad request', Response::HTTP_BAD_REQUEST);
}
