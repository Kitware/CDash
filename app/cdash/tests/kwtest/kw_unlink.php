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

function cdash_testsuite_unlink($filename)
{
    $try_count = 1;
    echo "unlink '" . $filename . "'\n";
    $success = unlink($filename);

    while (file_exists($filename) && $try_count < 300) {
        usleep(1000000); // == 1000 ms, == 1.0 seconds

        $try_count++;
        echo "attempt $try_count to unlink '" . $filename . "'\n";
        $success = unlink($filename);
    }

    if ($try_count > 3) {
        echo "excessive try_count=$try_count unlinking '" . $filename . "'\n";
    }

    if (file_exists($filename)) {
        throw new Exception("file still exists after unlink: $filename");
    }
    return $success;
}
