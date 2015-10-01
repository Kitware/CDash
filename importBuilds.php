<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
/** Adding some PHP include path */
$path = dirname(__FILE__);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

// Open the database connection
include("cdash/config.php");
require_once("cdash/pdo.php");
include("cdash/version.php");
include_once('cdash/common.php');
include_once("cdash/ctestparser.php");

if ($argc != 2) {
    print "Usage: php $argv[0] directory \n";
    return -1;
}

$directory=$argv[1];
set_time_limit(0);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

print "checking new build files $directory \n";

// Write the current time in the file
$lastcheckfile = $directory."/lastcheck";
@$lastcheck = file_get_contents($lastcheckfile);
if (!empty($lastcheck)) {
    print "last check was ".date("Y-m-d H:i:s", $lastcheck)."\n";
}
$handle = fopen($lastcheckfile, "wb");
fwrite($handle, time());
fclose($handle);
unset($handle);

$files = glob($directory.'/*.xml');
$filelist = array();
foreach ($files as $file) {
    if (filemtime($file) > $lastcheck) {
        $filelist[] = $file;
    }
} // end foreach

$i = 0;
$n = count($filelist);
foreach ($filelist as $filename) {
    ++$i;

  # split on path separator
  $pathParts = split("[/\\]", $filename);
  # split on cdash separator "_"
  $cdashParts = split("[_]", $pathParts[count($pathParts)-1]);
    $projectid = get_project_id($cdashParts[0]);

    if ($projectid != -1) {
        $name = get_project_name($projectid);
        echo 'Project ['.$name.'] importing file ('.$i.'/'.$n.'): '.$filename."\n";
        ob_flush();
        flush();

        $handle = fopen($filename, "r");
        ctest_parse($handle, $projectid);
        fclose($handle);
        unset($handle);
    } else {
        echo 'Project id not found - skipping file ('.$i.'/'.$n.'): '.$filename."\n";
        ob_flush();
        flush();
    }
}

echo 'Import backup complete. '.$i.' files processed.'."\n";
echo "\n";

return 0;
