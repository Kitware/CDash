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
 * testSummary.php displays a list of all builds that performed a given test
 * on a specific day.  It also displays information (success, execution time)
 * about each copy of the test that was run.
 */
$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include_once 'include/repository.php';
include 'include/version.php';

$response = begin_JSON_response();
$response['title'] = 'CDash : Test Summary';
$response['showcalendar'] = 1;

// Checks
$date = htmlspecialchars(pdo_real_escape_string($_GET['date']));
if (!isset($date) || strlen($date) == 0) {
    $response['error'] = 'No date specified.';
    echo json_encode($response);
    return;
}
$projectid = pdo_real_escape_numeric($_GET['project']);
if (!isset($projectid)) {
    $response['error'] = 'No project specified.';
    echo json_encode($response);
    return;
}
if (!isset($projectid) || !is_numeric($projectid)) {
    $response['error'] = 'Not a valid projectid!';
    echo json_encode($response);
    return;
}

$testName = htmlspecialchars(pdo_real_escape_string($_GET['name']));
if (!isset($testName)) {
    $response['error'] = 'No test name specified.';
    echo json_encode($response);
    return;
}

$start = microtime_float();

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array['name'];
    $nightlytime = $project_array['nightlytime'];
    $projectshowtesttime = $project_array['showtesttime'];
} else {
    $response['error'] = 'Not a valid projectid!';
    echo json_encode($response);
    return;
}

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array['id'], 1)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$response['title'] = "CDash : $projectname";
get_dashboard_JSON_by_name($projectname, $date, $response);
$response['testName'] = $testName;

list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $nightlytime);
$menu = array();
$menu['back'] = 'index.php?project=' . urlencode($projectname) . "&date=$date";
$menu['previous'] = "testSummary.php?project=$projectid&name=$testName&date=$previousdate";
$menu['current'] = "testSummary.php?project=$projectid&name=$testName&date=" . date(FMT_DATE);
if ($date != '' && date(FMT_DATE, $currentstarttime) != date(FMT_DATE)) {
    $menu['next'] = "testSummary.php?project=$projectid&name=$testName&date=$nextdate";
} else {
    $menu['nonext'] = 1;
}
$response['menu'] = $menu;

$testName = pdo_real_escape_string($testName);
list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600 * 24;

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

$getcolumnnumber = pdo_query(
    "SELECT testmeasurement.name, COUNT(DISTINCT test.name) as xxx FROM test
        JOIN testmeasurement ON (test.id = testmeasurement.testid)
        JOIN build2test ON (build2test.testid = test.id)
        JOIN build ON (build.id = build2test.buildid)
        JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
        WHERE test.name='$testName'
        AND build.starttime>='$beginning_UTCDate'
        AND build.starttime<'$end_UTCDate'
        AND test.projectid=$projectid
        AND measurement.summarypage= 1
        GROUP by testmeasurement.name
        "); // We need to keep the count of columns for correct column-data assign

$columns = array();
while ($row = pdo_fetch_array($getcolumnnumber)) {
    $columns[] = $row['name'];
}
$response['columns'] = $columns;

$columncount = pdo_num_rows($getcolumnnumber);
// If at least one column is selected
if ($columncount > 0) {
    $etestquery = pdo_query(
        "SELECT test.id, test.projectid, build2test.buildid,
            build2test.status, build2test.timestatus, test.name,
            testmeasurement.name, testmeasurement.value, build.starttime,
            build2test.time, measurement.testpage FROM test
            JOIN testmeasurement ON (test.id = testmeasurement.testid)
            JOIN build2test ON (build2test.testid = test.id)
            JOIN build ON (build.id = build2test.buildid)
            JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
            WHERE test.name='$testName'
            AND build.starttime>='$beginning_UTCDate'
            AND build.starttime<'$end_UTCDate'
            AND test.projectid=$projectid
            AND measurement.summarypage= 1
            ORDER BY build2test.buildid, testmeasurement.name
            ");

    // Start creating etests for each column with matching buildid, testname
    // and the value.
    $etests = array();
    $i = 0;
    $currentcolumn = -1;
    $prevtestid = 0;
    $checkarray = array();
    while ($etestquery && $row = pdo_fetch_array($etestquery)) {
        if (!isset($checkarray[$row['name']]) || !in_array($row['id'], $checkarray[$row['name']])) {
            for ($columnkey = 0; $columnkey < $columncount; $columnkey++) {
                if ($columns[$columnkey] == $row['name']) {
                    $columnkey += 1;
                    break;
                }
            }
            $currentcolumn = ($currentcolumn + 1) % $columncount; // Go to next column
            if ($currentcolumn == 0) {
                $prevtestid = $row['id'];
            }
            if ($currentcolumn != $columnkey - 1) {
                // If data does not belong to this column
                for ($t = 0; $t < $columncount; $t++) {
                    if (($currentcolumn + $t) % $columncount != $columnkey - 1) {
                        // Add blank values till you find the required column
                        $etest = array();
                        $etest['name'] = '';
                        $etest['testid'] = $row['id'];
                        $etest['buildid'] = $row['buildid'];
                        $etest['value'] = '';
                        $etests[] = $etest;
                        $prevtestid = $row['id'];
                    } else {
                        // Go to next column again.
                        $currentcolumn = ($currentcolumn + $t) % $columncount;
                        break;
                    }
                }
                // Add correct values to correct column
                if ($prevtestid == $row['id'] and $currentcolumn != 0) {
                    $etest = array();
                    $etest['name'] = $row['name'];
                    $etest['testid'] = $row['id'];
                    $etest['buildid'] = $row['buildid'];
                    $etest['value'] = $row['value'];
                    $etests[] = $etest;

                    $checkarray[$row['name']][$i] = $row['id'];
                    $prevtestid = $row['id'];
                } else {
                    if ($prevtestid != $row['id'] and $prevtestid != 0 and $currentcolumn != 0) {
                        for ($t = 0; $t < $columncount; $t++) {
                            $etest = array();
                            $etest['name'] = '';
                            $etest['testid'] = '';
                            $etest['buildid'] = '';
                            $etest['rowcheck'] = '-';
                            $etests[] = $etest;
                        }
                    }

                    $etest = array();
                    $etest['name'] = $row['name'];
                    $etest['testid'] = $row['id'];
                    $etest['buildid'] = $row['buildid'];
                    $etest['value'] = $row['value'];
                    $etests[] = $etest;
                    $checkarray[$row['name']][$i] = $row['id'];
                    $prevtestid = $row['id'];
                }
            } else {
                if ($prevtestid != $row['id'] and $prevtestid != 0 and $currentcolumn != 0) {
                    for ($t = 0; $t < $columncount; $t++) {
                        $etest = array();
                        $etest['name'] = '';
                        $etest['testid'] = '';
                        $etest['buildid'] = '';
                        $etest['value'] = '';
                        $etests[] = $etest;
                    }
                }
                // Add correct values to correct column
                $etest = array();
                $etest['name'] = $row['name'];
                $etest['testid'] = $row['id'];
                $etest['buildid'] = $row['buildid'];
                $etest['value'] = $row['value'];
                $etests[] = $etest;
                $checkarray[$row['name']][$i] = $row['id'];
                $prevtestid = $row['id'];
            }
        }
        $i++;
    }
    $response['etests'] = $etests;
}

// Add the date/time
$response['projectid'] = $projectid;
$response['currentstarttime'] = $currentstarttime;
$response['teststarttime'] = date(FMT_DATETIME, $beginning_timestamp);
$response['testendtime'] = date(FMT_DATETIME, $end_timestamp);

//Get information about all the builds for the given date and project
$builds = array();

$columncount = pdo_num_rows($getcolumnnumber);
// If at least one column is selected
if ($columncount > 0) {
    $etestquery = pdo_query(
        "SELECT test.id, test.projectid, build2test.buildid,
            build2test.status, build2test.timestatus, test.name,
            testmeasurement.name, testmeasurement.value, build.starttime,
            build2test.time, measurement.testpage FROM test
            JOIN testmeasurement ON (test.id = testmeasurement.testid)
            JOIN build2test ON (build2test.testid = test.id)
            JOIN build ON (build.id = build2test.buildid)
            JOIN measurements ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
            WHERE test.name='$testName'
            AND build.starttime>='$beginning_UTCDate'
            AND build.starttime<'$end_UTCDate'
            AND test.projectid=$projectid
            AND measurement.summarypage= 1
            ORDER BY build2test.buildid, testmeasurement.name
            ");
}

$query = "
    SELECT b.id AS buildid, b.name, b.stamp, b2t.status,
           b2t.time, t.id AS testid, s.name AS sitename
    FROM test AS t
    LEFT JOIN build2test AS b2t ON (t.id = b2t.testid)
    LEFT JOIN build AS b ON (b.id = b2t.buildid)
    LEFT JOIN site AS s ON (s.id = b.siteid)
    WHERE t.name='$testName' AND
        b.projectid = '$projectid' AND
        b.starttime BETWEEN '$beginning_UTCDate' AND '$end_UTCDate'
    ORDER BY buildid";
$result = pdo_query($query);

// If user wants to export as CSV file.
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Cache-Control: public');
    header('Content-Description: File Transfer');
    // Prepare some headers to download.
    header('Content-Disposition: attachment; filename=testExport.csv');
    header('Content-Type: application/octet-stream;');
    header('Content-Transfer-Encoding: binary');
    // Standard columns.
    $filecontent = 'Site,Build Name,Build Stamp,Status,Time(s)';

    // Store named measurements in an array.
    while (isset($etestquery) && $row = pdo_fetch_array($etestquery)) {
        $etest[$row['buildid']][$row['name']] = $row['value'];
    }

    for ($c = 0; $c < count($columns); $c++) {
        $filecontent .= ',' . $columns[$c]; // Add selected columns to the next
    }

    $filecontent .= "\n";

    while ($row = pdo_fetch_array($result)) {
        $currentStatus = $row['status'];

        $filecontent .= "{$row['sitename']},{$row['name']},{$row['stamp']},{$row['time']},";

        if ($projectshowtesttime) {
            if ($row['timestatus'] < $testtimemaxstatus) {
                $filecontent .= 'Passed,';
            } else {
                $filecontent .= 'Failed,';
            }
        }

        switch ($currentStatus) {
            case 'passed':
                $filecontent .= 'Passed,';
                break;
            case 'failed':
                $filecontent .= 'Failed,';
                break;
            case 'notrun':
                $filecontent .= 'Not Run,';
                break;
        }
        // start writing test results
        for ($t = 0; $t < count($columns); $t++) {
            $filecontent .= $etest[$row['id']][$columns[$t]] . ',';
        }
        $filecontent .= "\n";
    }
    echo($filecontent); // Start file download
    die; // to suppress unwanted output
}

//now that we have the data we need, generate our response.
$numpassed = 0;
$numfailed = 0;
$numtotal = 0;

while ($row = pdo_fetch_array($result)) {
    $buildid = $row['buildid'];
    $build_response = array();

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
        get_revision_url($projectid, $status_array['priorrevision'], ''); // no prior prior revision...
    $build_response['update'] = $update_response;

    $build_response['site'] = $row['sitename'];
    $build_response['buildName'] = $row['name'];
    $build_response['buildStamp'] = $row['stamp'];
    $build_response['time'] = $row['time'];

    $buildLink = "viewTest.php?buildid=$buildid";
    $build_response['buildid'] = $buildid;
    $build_response['buildLink'] = $buildLink;
    $testid = $row['testid'];
    $testLink = "testDetails.php?test=$testid&build=$buildid";
    $build_response['testLink'] = $testLink;
    switch ($row['status']) {
        case 'passed':
            $build_response['status'] = 'Passed';
            $build_response['statusclass'] = 'normal';
            $numpassed += 1;
            break;
        case 'failed':
            $build_response['status'] = 'Failed';
            $build_response['statusclass'] = 'error';
            $numfailed += 1;
            break;
        case 'notrun':
            $build_response['status'] = 'Not Run';
            $build_response['statusclass'] = 'warning';
            break;
    }
    $numtotal += 1;
    $builds_response[] = $build_response;
}

$response['builds'] = $builds_response;
$response['csvlink'] = htmlspecialchars($_SERVER['REQUEST_URI']) . '&amp;export=csv';
$response['columncount'] = count($columns);
$response['numfailed'] = $numfailed;
$response['numtotal'] = $numtotal;
$response['percentagepassed'] = round($numpassed / $numtotal, 2) * 100;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);

echo json_encode($response);
