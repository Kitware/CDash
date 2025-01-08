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

namespace CDash\Api\v1\RequeueSubmissionFile;

use App\Jobs\ProcessSubmission;
use CDash\Model\PendingSubmissions;
use Illuminate\Contracts\Encryption\DecryptException;
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
    try {
        $filename = decrypt($_REQUEST['filename']);
    } catch (DecryptException $e) {
        return response('This feature is disabled', Response::HTTP_CONFLICT);
    }
    $buildid = $_REQUEST['buildid'];
    $projectid = $_REQUEST['projectid'];
    if (!Storage::exists("inprogress/{$filename}")) {
        return response('File not found', Response::HTTP_NOT_FOUND);
    }

    $retry_handler = new \RetryHandler(Storage::path("inprogress/{$filename}"));
    $retry_handler->Increment();

    // Move file back to inbox.
    Storage::move("inprogress/{$filename}", "inbox/{$filename}");

    // Requeue the file with exponential backoff.
    PendingSubmissions::IncrementForBuildId($buildid);
    $delay = pow(config('cdash.retry_base'), $retry_handler->Retries);
    ProcessSubmission::dispatch($filename, $projectid, $buildid, md5_file(Storage::path("inbox/{$filename}")))->delay(now()->addSeconds($delay));
    return response('OK', Response::HTTP_OK);
} else {
    return response('Bad request', Response::HTTP_BAD_REQUEST);
}
