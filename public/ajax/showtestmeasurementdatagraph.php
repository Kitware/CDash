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

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';

$noforcelogin = 1;
include 'public/login.php';

$testid = pdo_real_escape_numeric($_GET['testid']);
$buildid = pdo_real_escape_numeric($_GET['buildid']);
$measurement = preg_replace('/[^\da-z]/i', '', $_GET['measurement']);
$measurementname = htmlspecialchars(pdo_real_escape_string(stripslashes($measurement)));

if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}
if (!isset($testid) || !is_numeric($testid)) {
    echo 'Not a valid testid!';
    return;
}
if (!isset($measurementname) || !is_string($measurementname)) {
    echo 'Not a valid measurementname!';
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$test = pdo_query("SELECT name FROM test WHERE id='$testid'");
$test_array = pdo_fetch_array($test);
$testname = $test_array['name'];

$build = pdo_query("SELECT name,type,siteid,projectid,starttime
FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildname = $build_array['name'];
$siteid = $build_array['siteid'];
$buildtype = $build_array['type'];
$starttime = $build_array['starttime'];
$projectid = $build_array['projectid'];

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    echo 'You are not authorized to view this page.';
    return;
}

// Find the other builds
$previousbuilds = pdo_query("SELECT
build.id,build.starttime,build2test.testid,testmeasurement.value
FROM build
JOIN build2test ON (build.id = build2test.buildid)
JOIN testmeasurement ON(build2test.testid = testmeasurement.testid)
WHERE testmeasurement.name = '$measurementname'
AND build.siteid = '$siteid'
AND build.projectid = '$projectid'
AND build.starttime <= '$starttime'
AND build.type = '$buildtype'
AND build.name = '$buildname'
AND build2test.testid IN (SELECT id FROM test WHERE name = '$testname')
ORDER BY build.starttime DESC
");

$tarray = array();
while ($build_array = pdo_fetch_array($previousbuilds)) {
    $t['x'] = strtotime($build_array['starttime']) * 1000;
    $time[] = date('Y-m-d H:i:s', strtotime($build_array['starttime']));
    $t['y'] = $build_array['value'];
    $t['builid'] = $build_array['id'];
    $t['testid'] = $build_array['testid'];

    $tarray[] = $t;
}

if (@$_GET['export'] == 'csv') {
    // If user wants to export as CSV file
    header('Cache-Control: public');
    header('Content-Description: File Transfer');

    // Prepare some headers to download
    header('Content-Disposition: attachment; filename=' . $testname . '_' . $measurementname . '.csv');
    header('Content-Type: application/octet-stream;');
    header('Content-Transfer-Encoding: binary');

    // Standard columns
    $filecontent = "Date,$measurementname\n";
    for ($c = 0; $c < count($tarray); $c++) {
        $filecontent .= "{$time[$c]},{$tarray[$c]['y']}\n";
    }
    echo($filecontent); // Start file download
    die; // to suppress unwanted output
}

$tarray = array_reverse($tarray);
echo json_encode($tarray);
