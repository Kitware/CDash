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

// Open the database connection
include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/version.php';
include_once 'include/common.php';
include_once 'include/ctestparser.php';

if ($argc != 2) {
    echo "Usage: php $argv[0] directory \n";
    return -1;
}

$directory = $argv[1];
@set_time_limit(0);

echo "checking new build files $directory \n";

// Write the current time in the file
$lastcheckfile = $directory . '/lastcheck';
@$lastcheck = file_get_contents($lastcheckfile);
if (!empty($lastcheck)) {
    echo 'last check was ' . date('Y-m-d H:i:s', $lastcheck) . "\n";
}
$handle = fopen($lastcheckfile, 'wb');
fwrite($handle, time());
fclose($handle);
unset($handle);

$files = glob($directory . '/*.xml');
$filelist = array();
foreach ($files as $file) {
    if (filemtime($file) > $lastcheck) {
        $filelist[] = $file;
    }
}

$i = 0;
$n = count($filelist);
foreach ($filelist as $filename) {
    ++$i;

    // split on path separator
    $pathParts = preg_split('_[\\\\/]_', $filename);
    // split on cdash separator "_"
    $cdashParts = explode('_', $pathParts[count($pathParts) - 1]);
    $projectid = get_project_id($cdashParts[0]);

    if ($projectid != -1) {
        $name = get_project_name($projectid);
        echo 'Project [' . $name . '] importing file (' . $i . '/' . $n . '): ' . $filename . "\n";
        ob_flush();
        flush();

        $handle = fopen($filename, 'r');
        ctest_parse($handle, $projectid);
        fclose($handle);
        unset($handle);
    } else {
        echo 'Project id not found - skipping file (' . $i . '/' . $n . '): ' . $filename . "\n";
        ob_flush();
        flush();
    }
}

echo 'Import backup complete. ' . $i . ' files processed.' . "\n";
echo "\n";
return 0;
