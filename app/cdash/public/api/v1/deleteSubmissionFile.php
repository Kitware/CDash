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
include_once 'include/ctestparser.php';

use CDash\Config;
use Illuminate\Support\Facades\Storage;

$config = Config::getInstance();

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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_REQUEST['filename'])) {
    $success = true;
    $filename = Storage::path('inbox') . '/' . basename($_REQUEST['filename']);
    if (file_exists($filename)) {
        if (config('cdash.backup_timeframe') == 0) {
            // Delete the file.
            $success = @unlink($filename);
        } elseif (isset($_REQUEST['dest'])) {
            // Rename the file.
            $dest_filename = Storage::path('parsed') . '/' . basename($_REQUEST['dest']);
            $fh = fopen($filename, 'r');
            if (!$fh) {
                $success = false;
            } else {
                if (safelyWriteBackupFile($fh, '', $dest_filename) === false) {
                    $success = false;
                }
                fclose($fh);
                if ($success && @!unlink($filename)) {
                    $success = false;
                }
            }
        }
    }
    if (!$success) {
        http_response_code(500);
        exit();
    }
} else {
    http_response_code(400);
    exit();
}
