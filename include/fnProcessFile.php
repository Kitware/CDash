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

require_once 'include/common.php';
require_once 'include/do_submit.php';

// Try to open the file and process it (call "do_submit" on it)
//
function ProcessFile($projectid, $filename, $md5)
{
    unset($fp);

    if (!file_exists($filename)) {
        // check in parent dir also
        $filename = "../$filename";
    }

    if (file_exists($filename)) {
        $fp = fopen($filename, 'r');
    }

    if (@$fp) {
        global $PHP_ERROR_SUBMISSION_ID;
        do_submit($fp, $projectid, $md5, false, $PHP_ERROR_SUBMISSION_ID);
        $PHP_ERROR_SUBMISSION_ID = 0;

        @fclose($fp);
        unset($fp);

        global $CDASH_BACKUP_TIMEFRAME;
        if ($CDASH_BACKUP_TIMEFRAME != '0') {
            // Delete the temporary backup file since we now have a better-named one.
            cdash_unlink($filename);
        }

        $new_status = 2; // done, did call do_submit, finished normally
    } else {
        add_log("Cannot open file '" . $filename . "'", 'ProcessFile',
            LOG_ERR, $projectid);
        $new_status = 3; // done, did *NOT* call do_submit
    }
    return $new_status;
}
