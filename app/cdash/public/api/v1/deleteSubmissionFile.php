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

namespace CDash\Api\v1\DeleteSubmissionFile;

require_once 'include/ctestparser.php';

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delete the temporary file related to a particular submission.
 *
 * Related: getSubmissionFile.php
 *
 * DELETE /deleteSubmissionFile.php
 * Required Params:
 * filename=[string] Filename to delete, must live in tmp_submissions directory
 * Optional Params:
 * dest=[string] Instead of deleting, rename filename to dest
 **/

if (!config('cdash.remote_workers')) {
    return response('This feature is disabled', Response::HTTP_CONFLICT);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_REQUEST['filename'])) {
    $filename = $_REQUEST['filename'];
    if (!Storage::exists($filename)) {
        return response('File not found', Response::HTTP_NOT_FOUND);
    }
    if (config('cdash.backup_timeframe') == 0) {
        // Delete the file.
        if (Storage::delete($filename)) {
            return response('OK', Response::HTTP_OK);
        } else {
            return response('Deletion failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    } elseif (isset($_REQUEST['dest'])) {
        // Rename the file.
        $dest = $_REQUEST['dest'];
        if (Storage::move($filename, $dest)) {
            return response('OK', Response::HTTP_OK);
        } else {
            return response('Rename failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} else {
    return response('Bad request', Response::HTTP_BAD_REQUEST);
}
