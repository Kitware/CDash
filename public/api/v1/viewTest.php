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

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';
require_once 'include/filterdataFunctions.php';
include_once 'models/build.php';

/**
 * View tests of a particular build.
 * etest functionality isn't supported for parent builds.
 *
 * GET /viewTest.php
 * Required Params:
 * buildid=[integer] The ID of the build
 *
 * Optional Params:
 *
 * date=[YYYY-mm-dd]
 * tests=[array of test names]
 *   If tests is passed the following parameters apply:
 *       Required:
 *         projectid=[integer]
 *         groupid=[integer]
 *       Optional:
 *         previous_builds=[comma separated list of build ids]
 *         time_begin=[SQL compliant comparable to timestamp]
 *         time_end=[SQL compliant comparable to timestamp]
 * onlypassed=[presence]
 * onlyfailed=[presence]
 * onlytimestatus=[presence]
 * onlynotrun=[presence]
 * onlydelta=[presence]
 * filterstring
 * export=[presence]
 **/

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}
@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

if (isset($_GET['tests'])) {
    // AJAX call to load history & summary data for currently visible tests.
    load_test_details();
    exit(0);
}

$response = begin_JSON_response();
$response['title'] = 'CDash : View Tests';

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    $response['error'] = 'Not a valid buildid!';
    echo json_encode($response);
    return;
}

$start = microtime_float();
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build_array = pdo_fetch_array(pdo_query(
    "SELECT parentid, projectid, siteid, type, name, starttime, endtime, groupid
     FROM build AS b
     LEFT JOIN build2group AS b2g ON (b.id = b2g.buildid)
     WHERE id='$buildid'"));
$projectid = $build_array['projectid'];
if (!isset($projectid) || $projectid == 0) {
    $response['error'] = "This build doesn't exist. Maybe it has been deleted.";
    echo json_encode($response);
    return;
}

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$project = pdo_query("SELECT name,showtesttime,testtimemaxstatus,nightlytime,displaylabels FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array['name'];
    $projectshowtesttime = $project_array['showtesttime'];
    $testtimemaxstatus = $project_array['testtimemaxstatus'];
}

$response['title'] = "CDash : $projectname";
$siteid = $build_array['siteid'];
$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$starttime = $build_array['starttime'];
$endtime = $build_array['endtime'];
$groupid = $build_array['groupid'];
$response['groupid'] = $groupid;

$date = get_dashboard_date_from_build_starttime($starttime, $project_array['nightlytime']);
get_dashboard_JSON_by_name($projectname, $date, $response);

// Menu
$menu = array();

$onlypassed = 0;
$onlyfailed = 0;
$onlytimestatus = 0;
$onlynotrun = 0;
$onlydelta = 0;
$extraquery = '';
$display = '';

if (isset($_GET['onlypassed'])) {
    $onlypassed = 1;
    $extraquery = '&onlypassed';
    $display = 'onlypassed';
} elseif (isset($_GET['onlyfailed'])) {
    $onlyfailed = 1;
    $extraquery = '&onlyfailed';
    $display = 'onlyfailed';
} elseif (isset($_GET['onlytimestatus'])) {
    $onlytimestatus = 1;
    $extraquery = '&onlytimestatus';
    $display = 'onlytimestatus';
} elseif (isset($_GET['onlynotrun'])) {
    $onlynotrun = 1;
    $extraquery = '&onlynotrun';
    $display = 'onlynotrun';
} elseif (isset($_GET['onlydelta'])) {
    // new test that are showing up for this category
    $onlydelta = 1;
    $extraquery = '&onlydelta';
    $display = 'onlydelta';
} else {
    $display = 'all';
}

$nightlytime = get_project_property($projectname, 'nightlytime');
$menu['back'] = 'index.php?project=' . urlencode($projectname) . '&date=' . get_dashboard_date_from_build_starttime($starttime, $nightlytime);

// Get the IDs of the four previous builds.
// These are used to check the recent history of this test.
$build = new Build();
$n = 3;
$id = $buildid;
$previous_buildid = 0;
$previous_buildids = array();

// Include the current buildid in this list so the current status will
// be reflected in the history column.
$previous_buildids[] = $id;

for ($i = 0; $i < $n; $i++) {
    $build->Id = $id;
    $build->Filled = false;

    $id = $build->GetPreviousBuildId();

    if ($i == 0) {
        $previous_buildid = $id;
        $current_buildid = $build->GetCurrentBuildId();
        $next_buildid = $build->GetNextBuildId();
    }

    if ($id == 0) {
        break;
    }
    $previous_buildids[] = $id;
}

$previous_buildids_str = '';
if ($previous_buildid > 0) {
    $menu['previous'] = "viewTest.php?buildid=$previous_buildid$extraquery";
    if (count($previous_buildids) > 1) {
        $previous_buildids_str = implode(', ', $previous_buildids);
    }
} else {
    $menu['noprevious'] = '1';
}
$response['previous_builds'] = $previous_buildids_str;

$menu['current'] = "viewTest.php?buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu['next'] = 'viewTest.php?buildid=' . $next_buildid . $extraquery;
} else {
    $menu['nonext'] = '1';
}

$response['menu'] = $menu;

$build = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build['displaylabels'] = $project_array['displaylabels'];
$build['site'] = $site_array['name'];
$build['siteid'] = $siteid;
$build['buildname'] = $buildname;
$build['buildid'] = $buildid;
$build['testtime'] = $endtime;

// Find the OS and compiler information
$buildinformation = pdo_query("SELECT * FROM buildinformation WHERE buildid='$buildid'");
if (pdo_num_rows($buildinformation) > 0) {
    $buildinformation_array = pdo_fetch_array($buildinformation);
    if ($buildinformation_array['osname'] != '') {
        $build['osname'] = $buildinformation_array['osname'];
    }
    if ($buildinformation_array['osplatform'] != '') {
        $build['osplatform'] = $buildinformation_array['osplatform'];
    }
    if ($buildinformation_array['osrelease'] != '') {
        $build['osrelease'] = $buildinformation_array['osrelease'];
    }
    if ($buildinformation_array['osversion'] != '') {
        $build['osversion'] = $buildinformation_array['osversion'];
    }
    if ($buildinformation_array['compilername'] != '') {
        $build['compilername'] = $buildinformation_array['compilername'];
    }
    if ($buildinformation_array['compilerversion'] != '') {
        $build['compilerversion'] = $buildinformation_array['compilerversion'];
    }
}
$response['build'] = $build;
$response['csvlink'] = "api/v1/viewTest.php?buildid=$buildid&export=csv";

$project = array();
$project['showtesttime'] = $projectshowtesttime;
$response['project'] = $project;
$response['parentBuild'] = $build_array['parentid'] == -1;

$displaydetails = 1;
$status = '';
$order = 't.name';

if ($onlypassed) {
    $displaydetails = 0;
    $status = "AND bt.status='passed'";
} elseif ($onlyfailed) {
    $status = "AND bt.status='failed'";
} elseif ($onlynotrun) {
    $displaydetails = 0;
    $status = "AND bt.status='notrun'";
} elseif ($onlytimestatus) {
    $status = "AND bt.timestatus>='$testtimemaxstatus'";
} else {
    $order = 'bt.status,bt.timestatus DESC,t.name';
}

$response['displaydetails'] = $displaydetails;
$response['display'] = $display;

// Filters:
//
$filterdata = get_filterdata_from_request();
unset($filterdata['xml']);
$response['filterdata'] = $filterdata;
$filter_sql = $filterdata['sql'];
$limit_sql = '';
if ($filterdata['limit'] > 0) {
    $limit_sql = ' LIMIT ' . $filterdata['limit'];
}
$response['filterurl'] = get_filterurl();

$limitnew = '';
$onlydelta_extra = '';
if ($onlydelta) {
    $limitnew = ' AND newstatus=1 ';
    $onlydelta_extra = ' AND build2test.newstatus=1 ';
}

// Postgres differs from MySQL on how to aggregate results
// into a single column.
$labeljoin_sql = '';
$label_sql = '';
$groupby_sql = '';
if ($project_array['displaylabels'] && $CDASH_DB_TYPE != 'pgsql') {
    $labeljoin_sql = '
        LEFT JOIN label2test AS l2t ON (l2t.testid=t.id)
        LEFT JOIN label AS l ON (l.id=l2t.labelid)';
    $label_sql = ", GROUP_CONCAT(DISTINCT l.text SEPARATOR ', ') AS labels";
    $groupby_sql = ' GROUP BY t.id';
}

if ($build_array['parentid'] == -1) {
    $parentBuildFieldSql = ', sp2b.subprojectid, sp.name subprojectname';
    $parentBuildJoinSql = 'JOIN build b ON (b.id = bt.buildid)
                           JOIN subproject2build sp2b on (sp2b.buildid = b.id)
                           JOIN subproject sp on (sp.id = sp2b.subprojectid)';
    $parentBuildWhere = "b.parentid = $buildid";
} else {
    $parentBuildFieldSql = '';
    $parentBuildJoinSql = '';
    $parentBuildWhere = "bt.buildid = $buildid";
}

$sql = "
    SELECT bt.status, bt.newstatus, bt.timestatus, t.id, bt.time, bt.buildid, t.details,
           t.name $label_sql $parentBuildFieldSql
    FROM build2test AS bt
    LEFT JOIN test AS t ON (t.id=bt.testid)
    $parentBuildJoinSql
    $labeljoin_sql
    WHERE $parentBuildWhere $status $filter_sql $limitnew $groupby_sql
          $limit_sql";

$result = pdo_query($sql);

$numPassed = 0;
$numFailed = 0;
$numNotRun = 0;
$numTimeFailed = 0;

$columns = array();
$getcolumnnumber = pdo_query("SELECT testmeasurement.name, COUNT(DISTINCT test.name) as xxx FROM test
JOIN testmeasurement ON (test.id = testmeasurement.testid)
JOIN build2test ON (build2test.testid = test.id)
JOIN build ON (build.id = build2test.buildid)
JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
WHERE build.id='$buildid'
AND measurement.testpage=1
GROUP by testmeasurement.name
"); // We need to keep the count of columns for correct column-data assign

while ($row = pdo_fetch_array($getcolumnnumber)) {
    $columns[] = $row['name'];
}
$response['columnnames'] = $columns;

$columncount = pdo_num_rows($getcolumnnumber);
// If at least one column is selected
$extras = '';
if ($onlypassed) {
    $extras .= "AND build2test.status='passed'";
} elseif ($onlyfailed) {
    $extras .= "AND build2test.status='failed'";
} elseif ($onlynotrun) {
    $extras .= "AND build2test.status='notrun'";
}

$getalltestlistsql = "SELECT test.id
  FROM test
  JOIN build2test ON (build2test.testid = test.id)
  JOIN build ON (build.id = build2test.buildid)
  WHERE build.id='$buildid' $onlydelta_extra
  $extras
  ORDER BY test.id
";

// Allocate empty array for all possible measurements
$tmpr = array();
$getalltestlist = pdo_query($getalltestlistsql);
while ($row = pdo_fetch_array($getalltestlist)) {
    for ($i = 0; $i < $columncount; $i++) {
        $tmpr[$row['id']][$columns[$i]] = '';
    }
}

$etestquery = null;

if ($columncount > 0) {
    $etestquery = pdo_query("SELECT test.id, test.projectid, build2test.buildid,
  build2test.status, build2test.timestatus, test.name, testmeasurement.name,
  testmeasurement.value, build.starttime,
  build2test.time, measurement.testpage FROM test
  JOIN testmeasurement ON (test.id = testmeasurement.testid)
  JOIN build2test ON (build2test.testid = test.id)
  JOIN build ON (build.id = build2test.buildid)
  JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
  WHERE build.id= '$buildid'
  AND measurement.testpage=1 $onlydelta_extra
  $extras
  ORDER BY test.id, testmeasurement.name
  ");
}

if (@$_GET['export'] == 'csv') {
    export_as_csv($etestquery, null, $result, $projectshowtesttime, $testtimemaxstatus, $columns);
}

// Start creating etests for each column with matching buildid, testname and the value.
$etests = array();
$i = 0;
$currentcolumn = -1;
$prevtestid = 0;
$checkarray = array();

// Overwrite the empty values with the correct ones if exists
while ($etestquery && $row = pdo_fetch_array($etestquery)) {
    $tmpr[$row['id']][$row['name']] = $row['value'];
}

// Write everything we have in the array
foreach ($tmpr as $testid => $testname) {
    foreach ($testname as $val) {
        $etest = array();
        $etest['name'] = key($testname);
        $etest['testid'] = $testid;
        $etest['value'] = $val;
        $etests[] = $etest;
    }
}
$response['etests'] = $etests;

// Gather test info
$tests = array();

// Find the time to run all the tests
$time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
$time = $time_array[0];
$response['totaltime'] = time_difference($time, true, '', true);

$num_tests = pdo_num_rows($result);

// Gather date information.
$testdate = get_dashboard_date_from_build_starttime($starttime, $nightlytime);
list($previousdate, $currentstarttime, $nextdate, $today) =
    get_dates($date, $nightlytime);
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600 * 24;
$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);
$response['time_begin'] = $beginning_UTCDate;
$response['time_end'] = $end_UTCDate;
$labels_found = false;

// Generate a response for each test found.
while ($row = pdo_fetch_array($result)) {
    $marshaledTest = buildtest::marshal($row, $row['buildid'], $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate);

    if ($marshaledTest['status'] == 'Passed') {
        $numPassed++;
    } elseif ($marshaledTest['status'] == 'Failed') {
        $numFailed++;
    } elseif ($marshaledTest['status'] == 'Not Run') {
        $numNotRun++;
    }

    if ($row['timestatus'] >= $testtimemaxstatus) {
        $numTimeFailed++;
    }

    $labels_found = ($CDASH_DB_TYPE != 'pgsql' && !empty($marshaledTest['labels']));
    $tests[] = $marshaledTest;
}

// Check for missing tests
$Build = new Build();
$Build->Id = $buildid;
$numMissing = $Build->GetNumberOfMissingTests();

if ($numMissing > 0) {
    foreach ($Build->MissingTests as $name) {
        $marshaledTest = buildtest::marshalMissing($name, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate);
        array_unshift($tests, $marshaledTest);
    }
}

$response['tests'] = $tests;
$response['numPassed'] = $numPassed;
$response['numFailed'] = $numFailed;
$response['numNotRun'] = $numNotRun;
$response['numTimeFailed'] = $numTimeFailed;
$response['numMissing'] = $numMissing;

// Only show the labels column if some were found.
$response['build']['displaylabels'] &= $labels_found;

$end = microtime_float();
$generation_time = round($end - $start, 3);
$response['generationtime'] = $generation_time;
$response['columncount'] = $columncount;

echo json_encode(cast_data_for_JSON($response));

function get_test_history($testname, $previous_buildids)
{
    $retval = array();

    // STRAIGHT_JOIN is a MySQL specific enhancement.
    $join_type = 'INNER JOIN';
    global $CDASH_DB_TYPE;
    if ($CDASH_DB_TYPE === 'mysql') {
        $join_type = 'STRAIGHT_JOIN';
    }

    $history_query = "
        SELECT DISTINCT status FROM build2test AS b2t
        $join_type test AS t ON (t.id = b2t.testid)
        WHERE b2t.buildid IN ($previous_buildids) AND t.name = '$testname'";
    $history_results = pdo_query($history_query);

    $num_statuses = pdo_num_rows($history_results);
    if ($num_statuses > 0) {
        if ($num_statuses > 1) {
            $retval['history'] = 'Unstable';
            $retval['historyclass'] = 'warning';
        } else {
            $row = pdo_fetch_array($history_results);

            $retval['history'] = ucfirst($row['status']);

            switch ($row['status']) {
                case 'passed':
                    $retval['historyclass'] = 'normal';
                    $retval['history'] = 'Stable';
                    break;
                case 'failed':
                    $retval['historyclass'] = 'error';
                    $retval['history'] = 'Broken';
                    break;
                case 'notrun':
                    $retval['historyclass'] = 'warning';
                    $retval['history'] = 'Inactive';
                    break;
            }
        }
    }
    return $retval;
}

function get_test_summary($testname, $projectid, $groupid, $begin, $end)
{
    $retval = array();

    // STRAIGHT_JOIN is a MySQL specific enhancement.
    $join_type = 'INNER JOIN';
    global $CDASH_DB_TYPE;
    if ($CDASH_DB_TYPE === 'mysql') {
        $join_type = 'STRAIGHT_JOIN';
    }

    $summary_query = "
        SELECT DISTINCT b2t.status FROM build AS b
        $join_type build2group AS b2g ON (b.id = b2g.buildid)
        $join_type build2test AS b2t ON (b.id = b2t.buildid)
        $join_type test AS t ON (b2t.testid = t.id)
        WHERE b2g.groupid = $groupid
        AND b.projectid = $projectid
        AND b.starttime>='$begin'
        AND b.starttime<'$end'
        AND t.name = '$testname'";

    $summary_results = pdo_query($summary_query);

    $num_statuses = pdo_num_rows($summary_results);
    if ($num_statuses > 0) {
        if ($num_statuses > 1) {
            $retval['summary'] = 'Unstable';
            $retval['summaryclass'] = 'warning';
        } else {
            $row = pdo_fetch_array($summary_results);

            $retval['summary'] = ucfirst($row['status']);

            switch ($row['status']) {
                case 'passed':
                    $retval['summaryclass'] = 'normal';
                    $retval['summary'] = 'Stable';
                    break;
                case 'failed':
                    $retval['summaryclass'] = 'error';
                    $retval['summary'] = 'Broken';
                    break;
                case 'notrun':
                    $retval['summaryclass'] = 'warning';
                    $retval['summary'] = 'Inactive';
                    break;
            }
        }
    }
    return $retval;
}

function load_test_details()
{
    // Parse input arguments.
    $tests = array();
    foreach ($_GET['tests'] as $test) {
        $tests[] = pdo_real_escape_string($test);
    }
    if (empty($tests)) {
        return;
    }

    $previous_builds = '';
    if (array_key_exists('previous_builds', $_GET)) {
        $previous_builds = pdo_real_escape_string($_GET['previous_builds']);
    }
    $time_begin = '';
    if (array_key_exists('time_begin', $_GET)) {
        $time_begin = pdo_real_escape_string($_GET['time_begin']);
    }
    $time_end = '';
    if (array_key_exists('time_end', $_GET)) {
        $time_end = pdo_real_escape_string($_GET['time_end']);
    }
    $projectid = pdo_real_escape_numeric($_GET['projectid']);
    $groupid = pdo_real_escape_numeric($_GET['groupid']);

    $response = array();
    $tests_response = array();

    foreach ($tests as $test) {
        // Send the client a character to see if they're still connected.
        // If they disconnected this will cause the script to terminate early.
        echo " ";
        flush();
        ob_flush();

        $test_response = array();
        $test_response['name'] = $test;
        $data_found = false;

        if ($time_begin && $time_end) {
            $summary_response = get_test_summary($test, $projectid, $groupid,
                $time_begin, $time_end);
            if (!empty($summary_response)) {
                $test_response = array_merge($test_response, $summary_response);
                $data_found = true;
            }
        }

        if ($previous_builds) {
            $history_response = get_test_history($test, $previous_builds);
            if (!empty($history_response)) {
                $test_response = array_merge($test_response, $history_response);
                $response['displayhistory'] = true;
                $data_found = true;
            }
        }

        if ($data_found) {
            $tests_response[] = $test_response;
        }
    }

    if (!empty($tests_response)) {
        $response['tests'] = $tests_response;
    }

    echo json_encode($response);
}

// Export test results as CSV file.
function export_as_csv($etestquery, $etest, $result, $projectshowtesttime, $testtimemaxstatus, $columns)
{
    // Store named measurements in an array
    if (!is_null($etestquery)) {
        while ($row = pdo_fetch_array($etestquery)) {
            $etest[$row['id']][$row['name']] = $row['value'];
        }
    }

    $csv_contents = array();
    // Standard columns.
    $csv_headers = array('Name', 'Time' ,'Details' , 'Status');
    if ($projectshowtesttime) {
        $csv_headers[] = 'Time Status';
    }

    for ($c = 0; $c < count($columns); $c++) {
        // Add extra coluns.
        $csv_headers[] = $columns[$c];
    }
    $csv_contents[] = $csv_headers;

    while ($row = pdo_fetch_array($result)) {
        $csv_row = array();
        $csv_row[] = $row['name'];
        $csv_row[] = $row['time'];
        $csv_row[] = $row['details'];

        switch ($row['status']) {
            case 'passed':
                $csv_row[] = 'Passed';
                break;
            case 'failed':
                $csv_row[] = 'Failed';
                break;
            case 'notrun':
            default:
                $csv_row[] = 'Not Run';
                break;
        }

        if ($projectshowtesttime) {
            if ($row['timestatus'] < $testtimemaxstatus) {
                $csv_row[] = 'Passed';
            } else {
                $csv_row[] = 'Failed';
            }
        }

        // Extra columns.
        for ($t = 0; $t < count($columns); $t++) {
            $csv_row[] = $etest[$row['id']][$columns[$t]];
        }
        $csv_contents[] = $csv_row;
    }

    // Write out our data as CSV.
    header('Content-type: text/csv');
    header('Content-Disposition: attachment; filename="testExport.csv";');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    foreach ($csv_contents as $csv_row) {
        fputcsv($output, $csv_row);
    }
    fclose($output);
    die; // to suppress unwanted output
}
