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

/*
* testDetails.php shows more detailed information for a particular test that
* was run.  This includes test output and image comparison information
*/
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';
include_once 'include/repository.php';
include 'include/version.php';
require_once 'models/build.php';

$start = microtime_float();
$response = [];

$testid = pdo_real_escape_numeric($_GET['test']);
// Checks
if (!isset($testid) || !is_numeric($testid)) {
    $response['error'] = 'A valid test was not specified.';
    echo json_encode($response);
    return;
}

$buildid = pdo_real_escape_numeric($_GET['build']);
if (!isset($buildid) || !is_numeric($buildid)) {
    $response['error'] = 'A valid buildid was not specified.';
    echo json_encode($response);
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$testRow = pdo_fetch_array(pdo_query("SELECT * FROM build2test,test WHERE build2test.testid = '$testid' AND build2test.buildid = '$buildid' AND build2test.testid=test.id"));
$buildRow = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id = '$buildid'"));
$projectid = $buildRow['projectid'];

if (!$projectid) {
    echo "This build doesn't exist.";
    return;
}

if (!can_access_project($projectid)) {
    return;
}

$response = begin_JSON_response();
$response['title'] = 'CDash : Test Details';

// If we have a fileid we download it
if (isset($_GET['fileid']) && is_numeric($_GET['fileid'])) {
    $result = pdo_query("SELECT id,value,name FROM testmeasurement WHERE testid=$testid AND type='file' ORDER BY id");
    for ($i = 0; $i < $_GET['fileid']; $i++) {
        $result_array = pdo_fetch_array($result);
    }
    header('Content-type: tar/gzip');
    header('Content-Disposition: attachment; filename="' . $result_array['name'] . '.tgz"');

    if ($CDASH_DB_TYPE == 'pgsql') {
        $buf = '';
        while (!feof($result_array['value'])) {
            $buf .= fread($result_array['value'], 2048);
        }
        $buf = stripslashes($buf);
    } else {
        $buf = $result_array['value'];
    }
    echo base64_decode($buf);
    flush();
    return;
}

$siteid = $buildRow['siteid'];

$project = pdo_query("SELECT name,nightlytime,showtesttime FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array['name'];
}

$projectRow = pdo_fetch_array(pdo_query("SELECT name,testtimemaxstatus FROM project WHERE id = '$projectid'"));
$projectname = $projectRow['name'];

$siteQuery = "SELECT name FROM site WHERE id = '$siteid'";
$siteResult = pdo_query($siteQuery);
$siteRow = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id = '$siteid'"));

$date = get_dashboard_date_from_build_starttime($buildRow['starttime'], $project_array['nightlytime']);
list($previousdate, $currenttime, $nextdate) = get_dates($date, $project_array['nightlytime']);
$logoid = getLogoID($projectid);

$response['title'] = "CDash : $projectname";
get_dashboard_JSON_by_name($projectname, $date, $response);

$project = array();
$project['showtesttime'] = $project_array['showtesttime'];
$response['project'] = $project;

$testName = $testRow['name'];
$buildtype = $buildRow['type'];
$buildname = $buildRow['name'];
$starttime = $buildRow['starttime'];

// Helper function
function findTest($buildid, $testName)
{
    $test = pdo_query('SELECT build2test.testid FROM build2test
                            WHERE build2test.buildid=' . qnum($buildid) . "
                            AND build2test.testid IN (SELECT id FROM test
                                 WHERE name='$testName')");
    if (pdo_num_rows($test) > 0) {
        $test_array = pdo_fetch_array($test);
        return $test_array['testid'];
    }
    return 0;
}

$menu = array();
$menu['back'] = "viewTest.php?buildid=$buildid";

$build = new Build();
$build->Id = $buildid;
$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

// Previous build
if ($previous_buildid > 0) {
    $previous_testid = findTest($previous_buildid, $testName);
    if ($previous_testid) {
        $menu['previous'] = "testDetails.php?test=$previous_testid&build=$previous_buildid";
    }
} else {
    $menu['noprevious'] = '1';
}

// Current build
if ($current_testid = findTest($current_buildid, $testName)) {
    $menu['current'] = "testDetails.php?test=$current_testid&build=$current_buildid";
}

// Next build
if ($next_buildid > 0) {
    if ($next_testid = findTest($next_buildid, $testName)) {
        $menu['next'] = "testDetails.php?test=$next_testid&build=$next_buildid";
    }
} else {
    $menu['nonext'] = '1';
}

$response['menu'] = $menu;

$summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";

$test_response = array();
$test_response['id'] = $testid;
$test_response['buildid'] = $buildid;
$test_response['build'] = $buildname;
$test_response['buildstarttime'] = date(FMT_DATETIMESTD, strtotime($starttime . ' UTC'));
$test_response['site'] = $siteRow['name'];
$test_response['siteid'] = $siteid;
$test_response['test'] = $testName;
$test_response['time'] = $testRow['time'];
$test_response['command'] = $testRow['command'];
$test_response['details'] = $testRow['details'];

// Helper function to remove bad characters for XML parser
function utf8_for_xml($string)
{
    return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
}

if ($CDASH_USE_COMPRESSION) {
    if ($CDASH_DB_TYPE == 'pgsql') {
        if (is_resource($testRow['output'])) {
            $testRow['output'] = base64_decode(stream_get_contents($testRow['output']));
        } else {
            $testRow['output'] = base64_decode($testRow['output']);
        }
    }
    @$uncompressedrow = gzuncompress($testRow['output']);
    if ($uncompressedrow !== false) {
        $test_response['output'] = utf8_for_xml($uncompressedrow);
    } else {
        $test_response['output'] = utf8_for_xml($testRow['output']);
    }
} else {
    $test_response['output'] = utf8_for_xml($testRow['output']);
}

$test_response['summaryLink'] = $summaryLink;
switch ($testRow['status']) {
    case 'passed':
        $test_response['status'] = 'Passed';
        $test_response['statusColor'] = '#00aa00';
        break;
    case 'failed':
        $test_response['status'] = 'Failed';
        $test_response['statusColor'] = '#aa0000';
        break;
    case 'notrun':
        $test_response['status'] = 'Not Run';
        $test_response['statusColor'] = '#ffcc66';
        break;
}

// Find the repository revision
$update_response = array();
// Return the status
$status_array = pdo_fetch_array(pdo_query("SELECT status,revision,priorrevision,path
                                              FROM buildupdate,build2update AS b2u
                                              WHERE b2u.updateid=buildupdate.id
                                              AND b2u.buildid='$buildid'"));
if (strlen($status_array['status']) > 0 && $status_array['status'] != '0') {
    $update_response['status'] = $status_array['status'];
} else {
    $update_response['status'] = ''; // empty status
}
$update_response['revision'] = $status_array['revision'];
$update_response['priorrevision'] = $status_array['priorrevision'];
$update_response['path'] = $status_array['path'];
$update_response['revisionurl'] =
    get_revision_url($projectid, $status_array['revision'], $status_array['priorrevision']);
$update_response['revisiondiff'] =
    get_revision_url($projectid, $status_array['priorrevision'], ''); // no prior revision...
$test_response['update'] = $update_response;

$test_response['timemean'] = $testRow['timemean'];
$test_response['timestd'] = $testRow['timestd'];

$testtimemaxstatus = $projectRow['testtimemaxstatus'];
if ($testRow['timestatus'] < $testtimemaxstatus) {
    $test_response['timestatus'] = 'Passed';
    $test_response['timeStatusColor'] = '#00aa00';
} else {
    $test_response['timestatus'] = 'Failed';
    $test_response['timeStatusColor'] = '#aa0000';
}

//get any images associated with this test
$query = "SELECT imgid,role FROM test2image WHERE testid = '$testid' AND (role='TestImage' "
    . "OR role='ValidImage' OR role='BaselineImage' OR role='DifferenceImage2') ORDER BY id";
$result = pdo_query($query);
if (pdo_num_rows($result) > 0) {
    $compareimages_response = array();
    while ($row = pdo_fetch_array($result)) {
        $image_response = array();
        $image_response['imgid'] = $row['imgid'];
        $image_response['role'] = $row['role'];
        $compareimages_response[] = $image_response;
    }
    $test_response['compareimages'] = $compareimages_response;
}

$images_response = array();
$query = "SELECT imgid,role FROM test2image WHERE testid = '$testid' "
    . "AND role!='ValidImage' AND role!='BaselineImage' AND role!='DifferenceImage2' ORDER BY id";
$result = pdo_query($query);
while ($row = pdo_fetch_array($result)) {
    $image_response = array();
    $image_response['imgid'] = $row['imgid'];
    $image_response['role'] = $row['role'];
    $images_response[] = $image_response;
}
if (!empty($images_response)) {
    $test_response['images'] = $images_response;
}

//get any measurements associated with this test
$measurements_response = array();
$query = "SELECT name,type,value FROM testmeasurement WHERE testid = '$testid' ORDER BY id";
$result = pdo_query($query);
$fileid = 1;
while ($row = pdo_fetch_array($result)) {
    $measurement_response = array();
    $measurement_response['name'] = $row['name'];
    $measurement_response['type'] = $row['type'];

    // ctest base64 encode the type text/plain...
    $value = $row['value'];
    if ($row['type'] == 'text/plain') {
        if (substr($value, strlen($value) - 2) == '==') {
            $value = base64_decode($value);
        }
    } elseif ($row['type'] == 'file') {
        $measurement_response['fileid'] = $fileid++;
    }
    // Add nl2br for type text/plain and text/string
    if ($row['type'] == 'text/plain' || $row['type'] == 'text/string') {
        $value = nl2br($value);
    }

    // If the type is a file we just don't pass the text (too big) to the output
    if ($row['type'] == 'file') {
        $value = '';
    }

    $measurement_response['value'] = $value;
    $measurements_response[] = $measurement_response;
}
if (!empty($measurements_response)) {
    $test_response['measurements'] = $measurements_response;
}
$response['test'] = $test_response;
$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode($response);
