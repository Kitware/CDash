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

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;

if (!function_exists('findTest')) {
    // Helper function
    function findTest($buildid, $testName, $pdo)
    {
        $stmt = $pdo->prepare(
            'SELECT build2test.testid FROM build2test
        WHERE build2test.buildid = :buildid
        AND build2test.testid IN
            (SELECT id FROM test WHERE name = :testname)');
        pdo_execute($stmt, [':buildid' => $buildid, ':testname' => $testName]);
        $testid = $stmt->fetchColumn();
        if ($testid === false) {
            return 0;
        }
        return $testid;
    }
}

if (!function_exists('utf8_for_xml')) {
    // Helper function to remove bad characters for XML parser
    function utf8_for_xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{001b}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }
}

$start = microtime_float();
$config = Config::getInstance();

$_REQUEST['buildid'] = $_GET['build'];
$build = get_request_build();

$testid = pdo_real_escape_numeric($_GET['test']);
// Checks
if (!isset($testid) || !is_numeric($testid)) {
    json_error_response(['error' => 'A valid test was not specified.']);
}

$projectid = $build->ProjectId;
if (!$projectid) {
    json_error_response(['error' => 'This build does not exist.']);
}

if (!can_access_project($projectid)) {
    return;
}

$pdo = Database::getInstance()->getPdo();
$response = begin_JSON_response();
$response['title'] = 'CDash : Test Details';

// If we have a fileid we download it.
if (isset($_GET['fileid']) && is_numeric($_GET['fileid'])) {
    $stmt = $pdo->prepare(
        "SELECT id, value, name FROM testmeasurement
        WHERE testid = :testid AND type = 'file'
        ORDER BY id");
    pdo_execute($stmt, [':testid' => $testid]);
    for ($i = 0; $i < $_GET['fileid']; $i++) {
        $result_array = $stmt->fetch();
    }
    header('Content-type: tar/gzip');
    header('Content-Disposition: attachment; filename="' . $result_array['name'] . '.tgz"');

    if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
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

$project = new Project();
$project->Id = $projectid;
$project->Fill();

$site = new Site();
$site->Id = $build->SiteId;
$site->Fill();

$date = $project->GetTestingDay($build->StartTime);
list($previousdate, $currenttime, $nextdate) = get_dates($date, $project->NightlyTime);
$logoid = getLogoID($projectid);

$response['title'] = "CDash : $project->Name";
get_dashboard_JSON_by_name($project->Name, $date, $response);

$project_response = [];
$project_response['showtesttime'] = $project->ShowTestTime;
$response['project'] = $project_response;

$stmt = $pdo->prepare(
    'SELECT * FROM build2test b2t
    JOIN test t ON t.id = b2t.testid
    WHERE b2t.testid = :testid AND b2t.buildid = :buildid');
pdo_execute($stmt, [':testid' => $testid, ':buildid' => $build->Id]);
$testRow = $stmt->fetch();
$testName = $testRow['name'];

$menu = [];
$menu['back'] = "viewTest.php?buildid=$build->Id";

$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

// Did the user request a specific chart?
// If so we should make that chart appear when they click next or previous.
$extra_url = '';
if (array_key_exists('graph', $_GET)) {
    $extra_url = "&graph=" . $_GET['graph'];
}

// Previous build
if ($previous_buildid > 0) {
    $previous_testid = findTest($previous_buildid, $testName, $pdo);
    if ($previous_testid) {
        $menu['previous'] = "testDetails.php?test=$previous_testid&build=$previous_buildid$extra_url";
    }
} else {
    $menu['previous'] = false;
}

// Current build
if ($current_testid = findTest($current_buildid, $testName, $pdo)) {
    $menu['current'] = "testDetails.php?test=$current_testid&build=$current_buildid$extra_url";
}

// Next build
if ($next_buildid > 0) {
    if ($next_testid = findTest($next_buildid, $testName, $pdo)) {
        $menu['next'] = "testDetails.php?test=$next_testid&build=$next_buildid$extra_url";
    }
} else {
    $menu['next'] = false;
}

$response['menu'] = $menu;

$summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";

$test_response = [];
$test_response['id'] = $testid;
$test_response['buildid'] = $build->Id;
$test_response['build'] = $build->Name;
$test_response['buildstarttime'] = date(FMT_DATETIMESTD, strtotime($build->StartTime . ' UTC'));
$test_response['site'] = $site->Name;
$test_response['siteid'] = $site->Id;
$test_response['test'] = $testName;
$test_response['time'] = time_difference($testRow['time'], true, '', true);
$test_response['command'] = $testRow['command'];
$test_response['details'] = $testRow['details'];

if ($config->get('CDASH_USE_COMPRESSION')) {
    if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
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
        $test_response['statusColor'] = 'normal-text';
        break;
    case 'failed':
        $test_response['status'] = 'Failed';
        $test_response['statusColor'] = 'error-text';
        break;
    case 'notrun':
        $test_response['status'] = 'Not Run';
        $test_response['statusColor'] = 'warning-text';
        break;
}

// Find the repository revision.
$update_response = [];
$stmt = $pdo->prepare(
    'SELECT status, revision, priorrevision, path
     FROM buildupdate bu
     JOIN build2update b2u ON (b2u.updateid = bu.id)
     WHERE b2u.buildid = :buildid');
pdo_execute($stmt, [':buildid' => $build->Id]);
$status_array = $stmt->fetch();
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

$testtimemaxstatus = $project->TestTimeMaxStatus;
if ($testRow['timestatus'] == 0) {
    $test_response['timestatus'] = 'Passed';
    $test_response['timeStatusColor'] = 'normal-text';
} else {
    $threshold = $test_response['timemean'] +
        $project->TestTimeStd * $test_response['timestd'];
    $test_response['threshold'] = time_difference($threshold, true, '', true);
    if ($testRow['timestatus'] >= $testtimemaxstatus) {
        $test_response['timestatus'] = 'Failed';
        $test_response['timeStatusColor'] = 'error-text';
    } else {
        $test_response['timestatus'] = 'Warning';
        $test_response['timeStatusColor'] = 'warning-text';
    }
}

// Get any images associated with this test.
$compareimages_response = [];
$stmt = $pdo->prepare(
    "SELECT imgid, role FROM test2image
    WHERE testid = :testid AND
        (role = 'TestImage' OR role = 'ValidImage' OR role = 'BaselineImage' OR
         role ='DifferenceImage2')
    ORDER BY id");
pdo_execute($stmt, [':testid' => $testid]);
while ($row = $stmt->fetch()) {
    $image_response = [];
    $image_response['imgid'] = $row['imgid'];
    $image_response['role'] = $row['role'];
    $compareimages_response[] = $image_response;
}
if (!empty($compareimages_response)) {
    $test_response['compareimages'] = $compareimages_response;
}

$images_response = [];
$stmt = $pdo->prepare(
    "SELECT imgid, role FROM test2image
    WHERE testid = :testid AND
          role != 'ValidImage' AND role != 'BaselineImage' AND
          role != 'DifferenceImage2'
    ORDER BY id");
pdo_execute($stmt, [':testid' => $testid]);
while ($row = $stmt->fetch()) {
    $image_response = [];
    $image_response['imgid'] = $row['imgid'];
    $image_response['role'] = $row['role'];
    $images_response[] = $image_response;
}
if (!empty($images_response)) {
    $test_response['images'] = $images_response;
}

// Get any measurements associated with this test.
$measurements_response = [];
$stmt = $pdo->prepare(
    'SELECT name, type, value FROM testmeasurement
    WHERE testid = :testid
    ORDER BY id');
pdo_execute($stmt, [':testid' => $testid]);
$fileid = 1;
while ($row = $stmt->fetch()) {
    $measurement_response = [];
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
