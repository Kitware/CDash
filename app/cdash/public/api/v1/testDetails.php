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
require_once 'include/pdo.php';
require_once 'include/api_common.php';
include_once 'include/repository.php';

use App\Models\BuildTest;
use App\Models\Test;
use App\Models\TestOutput;
use App\Services\PageTimer;
use App\Services\TestingDay;

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;

if (!function_exists('findTest')) {
    // Helper function
    function findTest($buildid, $testid, $pdo)
    {
        $stmt = $pdo->prepare('
        SELECT id FROM build2test
        WHERE buildid = :buildid AND
              testid = :testid');
        pdo_execute($stmt, [':buildid' => $buildid, ':testid' => $testid]);
        $buildtestid = $stmt->fetchColumn();
        if ($buildtestid === false) {
            return 0;
        }
        return $buildtestid;
    }
}

if (!function_exists('utf8_for_xml')) {
    // Helper function to remove bad characters for XML parser
    function utf8_for_xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{001b}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }
}

$pageTimer = new PageTimer();
$config = Config::getInstance();

$buildtestid = $_GET['buildtestid'];
if (!isset($buildtestid) || !is_numeric($buildtestid)) {
    json_error_response(['error' => 'A valid test was not specified.']);
}

$buildtest = BuildTest::where('id', '=', $buildtestid)->first();
if ($buildtest === null) {
    json_error_response(['error' => 'test not found'], 404);
    return;
}
$testid = $buildtest->test->id;
$build = new Build();
$build->Id = $buildtest->buildid;
$build->FillFromId($build->Id);

$projectid = $build->ProjectId;
if (!$projectid) {
    json_error_response(['error' => 'This build does not exist.']);
}

if (!can_access_project($projectid)) {
    return;
}

$pdo = Database::getInstance()->getPdo();
$response = begin_JSON_response();

// If we have a fileid we download it.
if (isset($_GET['fileid']) && is_numeric($_GET['fileid'])) {
    $stmt = $pdo->prepare(
        "SELECT id, value, name FROM testmeasurement
        WHERE outputid = :outputid AND type = 'file'
        ORDER BY id");
    pdo_execute($stmt, [':outputid' => $buildtest->outputid]);
    $result_array = $stmt->fetch();
    header('Content-type: tar/gzip');
    header('Content-Disposition: attachment; filename="' . $result_array['name'] . '.tgz"');
    echo base64_decode($result_array['value']);
    flush();
    ob_flush();
    return;
}

$project = new Project();
$project->Id = $projectid;
$project->Fill();

$site = new Site();
$site->Id = $build->SiteId;
$site->Fill();

$date = TestingDay::get($project, $build->StartTime);
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
    JOIN testoutput ON testoutput.id = b2t.outputid
    WHERE b2t.testid = :testid AND b2t.buildid = :buildid');
pdo_execute($stmt, [':testid' => $testid, ':buildid' => $build->Id]);
$testRow = $stmt->fetch();
$testName = $testRow['name'];
$outputid = $testRow['outputid'];

$menu = [];
$menu['back'] = "/viewTest.php?buildid=$build->Id";

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
    $previous_buildtestid = findTest($previous_buildid, $testid, $pdo);
    if ($previous_buildtestid) {
        $menu['previous'] = "/test/{$previous_buildtestid}{$extra_url}";
    }
} else {
    $menu['previous'] = false;
}

// Current build
if ($current_buildtestid = findTest($current_buildid, $testid, $pdo)) {
    $menu['current'] = "/test/{$current_buildtestid}{$extra_url}";
}

// Next build
if ($next_buildid > 0) {
    if ($next_buildtestid = findTest($next_buildid, $testid, $pdo)) {
        $menu['next'] = "/test/{$next_buildtestid}{$extra_url}";
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
$test_response['output'] = utf8_for_xml(TestOutput::DecompressOutput($testRow['output']));

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
$update_response = [
    'revision' => '',
    'priorrevision' => '',
    'path' => '',
    'revisionurl' => '',
    'revisiondiff' => ''
];
$stmt = $pdo->prepare(
    'SELECT status, revision, priorrevision, path
     FROM buildupdate bu
     JOIN build2update b2u ON (b2u.updateid = bu.id)
     WHERE b2u.buildid = :buildid');
pdo_execute($stmt, [':buildid' => $build->Id]);
$status_array = $stmt->fetch();
if (is_array($status_array)) {
    if (strlen($status_array['status']) > 0 && $status_array['status'] != '0') {
        $update_response['status'] = $status_array['status'];
    }
    $update_response['revision'] = $status_array['revision'];
    $update_response['priorrevision'] = $status_array['priorrevision'];
    $update_response['path'] = $status_array['path'];
    $update_response['revisionurl'] =
        get_revision_url($projectid, $status_array['revision'], $status_array['priorrevision']);
    $update_response['revisiondiff'] =
        get_revision_url($projectid, $status_array['priorrevision'], ''); // no prior revision...
}
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
    WHERE outputid = :outputid AND
        (role = 'TestImage' OR role = 'ValidImage' OR role = 'BaselineImage' OR
         role ='DifferenceImage2')
    ORDER BY id");
pdo_execute($stmt, [':outputid' => $outputid]);
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
    WHERE outputid = :outputid AND
          role != 'ValidImage' AND role != 'BaselineImage' AND
          role != 'DifferenceImage2'
    ORDER BY id");
pdo_execute($stmt, [':outputid' => $outputid]);
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
    WHERE outputid = :outputid
    ORDER BY id');
pdo_execute($stmt, [':outputid' => $outputid]);
$fileid = 1;
$test_response['environment'] = '';
while ($row = $stmt->fetch()) {
    if ($row['name'] === 'Environment' && $row['type'] === 'text/string') {
        $test_response['environment'] = $row['value'];
        continue;
    }

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
$test_response['measurements'] = $measurements_response;
$response['test'] = $test_response;
$pageTimer->end($response);
echo json_encode($response);
