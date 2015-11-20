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

require_once(dirname(dirname(__DIR__))."/config/config.php");
require_once("include/pdo.php");
require_once("include/common.php");

$testid = pdo_real_escape_numeric($_GET["testid"]);
$buildid = pdo_real_escape_numeric($_GET["buildid"]);
@$zoomout = $_GET["zoomout"];


if (!isset($buildid) || !is_numeric($buildid)) {
    echo "Not a valid buildid!";
    return;
}
if (!isset($testid) || !is_numeric($testid)) {
    echo "Not a valid testid!";
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$test = pdo_query("SELECT name FROM test WHERE id='$testid'");
$test_array = pdo_fetch_array($test);
$testname = $test_array["name"];


$build = pdo_query("SELECT name,type,siteid,projectid,starttime
FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$starttime = $build_array["starttime"];
$projectid = $build_array["projectid"];

// Find the other builds
$previousbuilds = pdo_query("SELECT build.id,build.starttime,build2test.time,build2test.testid
FROM build
JOIN build2test ON (build.id = build2test.buildid)
WHERE build.siteid = '$siteid'
AND build.projectid = '$projectid'
AND build.starttime <= '$starttime'
AND build.type = '$buildtype'
AND build.name = '$buildname'
AND build2test.testid IN (SELECT id FROM test WHERE name = '$testname')
ORDER BY build.starttime DESC
");
$tarray = array();
while ($build_array = pdo_fetch_array($previousbuilds)) {
    $t['x'] = strtotime($build_array["starttime"])*1000;
    $t['y'] = $build_array["time"];
    $t['buildid'] = $build_array["id"];
    $t['testid'] = $build_array["testid"];
    $tarray[] = $t;
}

$tarray = array_reverse($tarray);
echo json_encode($tarray);
