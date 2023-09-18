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

namespace CDash\Controller\Api;

use App\Models\BuildTest;
use App\Models\Project as EloquentProject;

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use App\Models\BuildInformation;

require_once 'include/filterdataFunctions.php';

class ViewTest extends BuildApi
{
    public $JSONEncodeResponse;

    private $extraMeasurements;
    private $numExtraMeasurements;

    public function __construct(Database $db, Build $build)
    {
        parent::__construct($db, $build);
        $this->JSONEncodeResponse = true;
        $this->config = Config::getInstance();
        $this->project->Fill();

        $this->extraMeasurements = [];
        $this->numExtraMeasurements = 0;
    }

    public function getResponse()
    {
        if (isset($_GET['tests'])) {
            // AJAX call to load history & summary data for currently visible tests.
            $this->loadTestDetails();
            exit(0);
        }

        $this->setDate($this->build->GetDate());

        $response = begin_JSON_response();
        $response['title'] = "{$this->project->Name} - Tests";
        $response['groupid'] = $this->build->GroupId;
        get_dashboard_JSON_by_name($this->project->Name, $this->date, $response);

        // Filters
        $filterdata = get_filterdata_from_request();
        $response['filterdata'] = $filterdata;
        $this->setFilterData($filterdata);
        $response['filterurl'] = get_filterurl();

        // Menu
        $menu = [];

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

        if ($this->build->GetParentId() > 0) {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&date=$this->date";
        }

        // Get the IDs of the four previous builds.
        // These are used to check the recent history of this test.
        $n = 3;
        $id = $buildid = $this->build->Id;
        $previous_buildid = 0;
        $previous_buildids = [];

        // Include the current buildid in this list so the current status will
        // be reflected in the history column.
        $previous_buildids[] = $id;

        for ($i = 0; $i < $n; $i++) {
            $b = new Build();
            $b->Id = $id;

            $id = $b->GetPreviousBuildId();

            if ($i == 0) {
                $previous_buildid = $id;
                $current_buildid = $b->GetCurrentBuildId();
                $next_buildid = $b->GetNextBuildId();
            }

            if ($id == 0) {
                break;
            }
            $previous_buildids[] = $id;
        }

        $previous_buildids_str = '';
        if ($previous_buildid > 0) {
            $menu['previous'] = "viewTest.php?buildid={$previous_buildid}{$extraquery}";
            if (count($previous_buildids) > 1) {
                $previous_buildids_str = implode(', ', $previous_buildids);
            }
        } else {
            $menu['previous'] = false;
        }
        $response['previous_builds'] = $previous_buildids_str;

        $menu['current'] = "viewTest.php?buildid={$current_buildid}{$extraquery}";

        if ($next_buildid > 0) {
            $menu['next'] = "viewTest.php?buildid={$next_buildid}{$extraquery}";
        } else {
            $menu['next'] = false;
        }

        $response['menu'] = $menu;

        $build_response = Build::MarshalResponseArray($this->build, [
            'displaylabels' => $this->project->DisplayLabels,
            'site' =>  $this->build->GetSite()->name,
            'testtime' => $this->build->EndTime,
        ]);

        // Find the OS and compiler information
        $buildinformation = BuildInformation::find($this->build->Id);
        if ($buildinformation !== null) {
            if ($buildinformation->osname != '') {
                $build_response['osname'] = $buildinformation->osname;
            }
            if ($buildinformation->osplatform != '') {
                $build_response['osplatform'] = $buildinformation->osplatform;
            }
            if ($buildinformation->osrelease != '') {
                $build_response['osrelease'] = $buildinformation->osrelease;
            }
            if ($buildinformation->osversion != '') {
                $build_response['osversion'] = $buildinformation->osversion;
            }
            if ($buildinformation->compilername != '') {
                $build_response['compilername'] = $buildinformation->compilername;
            }
            if ($buildinformation->compilerversion != '') {
                $build_response['compilerversion'] = $buildinformation->compilerversion;
            }
        }
        $response['build'] = $build_response;
        $response['csvlink'] = "api/v1/viewTest.php?buildid=$buildid&export=csv";

        $project_response = [];
        $project_response['showtesttime'] = $this->project->ShowTestTime;
        $response['project'] = $project_response;
        $response['parentBuild'] = $this->build->GetParentId() == Build::PARENT_BUILD;

        $params = [':buildid' => $buildid];
        $displaydetails = 1;
        $status = 'AND bt.status = :status';
        if ($onlypassed) {
            $displaydetails = 0;
            $params[':status'] = 'passed';
        } elseif ($onlyfailed) {
            $params[':status'] = 'failed';
        } elseif ($onlynotrun) {
            $params[':status'] = 'notrun';
        } elseif ($onlytimestatus) {
            $status = 'AND bt.timestatus >= :maxtimestatus';
            $params[':maxtimestatus'] = $this->project->TestTimeMaxStatus;
        } else {
            $status = '';
        }

        $response['displaydetails'] = $displaydetails;
        $response['display'] = $display;

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
        if ($this->project->DisplayLabels && config('database.default') != 'pgsql') {
            $labeljoin_sql = '
                LEFT JOIN label2test AS l2t ON (l2t.outputid=bt.outputid)
                LEFT JOIN label AS l ON (l.id=l2t.labelid)';
            $label_sql = ", GROUP_CONCAT(DISTINCT l.text SEPARATOR ', ') AS labels";
            $groupby_sql = ' GROUP BY bt.id';
        }

        if ($this->build->GetParentId() == Build::PARENT_BUILD) {
            $parentBuildFieldSql = ', sp2b.subprojectid, sp.name subprojectname';
            $parentBuildJoinSql = 'JOIN build b ON (b.id = bt.buildid)
                JOIN subproject2build sp2b on (sp2b.buildid = b.id)
                JOIN subproject sp on (sp.id = sp2b.subprojectid)';
            $parentBuildWhere = 'b.parentid = :buildid';
        } else {
            $parentBuildFieldSql = '';
            $parentBuildJoinSql = '';
            $parentBuildWhere = 'bt.buildid = :buildid';
        }

        $sql = "
            SELECT bt.status, bt.newstatus, bt.timestatus, t.id, bt.time, bt.buildid, bt.details,
                   bt.id AS buildtestid, t.name $label_sql $parentBuildFieldSql
                       FROM build2test AS bt
                       LEFT JOIN test AS t ON (t.id=bt.testid)
                       $parentBuildJoinSql
                       $labeljoin_sql
                       WHERE $parentBuildWhere $status $this->filterSQL $limitnew $groupby_sql
                       $this->limitSQL";
        $stmt = $this->db->prepare($sql);
        $this->db->execute($stmt, $params);

        $numPassed = 0;
        $numFailed = 0;
        $numNotRun = 0;
        $numTimeFailed = 0;

        // Are we looking for tests that were performed by this build,
        // or tests that were performed by children of this build?
        if ($this->build->GetParentId() == Build::PARENT_BUILD) {
            $buildid_field = 'parentid';
        } else {
            $buildid_field = 'id';
        }

        // Get the list of extra measurements that should be displayed on this page.
        $response['hasprocessors'] = false;
        $processors_idx = -1;
        $extra_measurements = EloquentProject::findOrFail($this->project->Id)
            ->measurements()
            ->orderBy('position')
            ->get();
        foreach ($extra_measurements as $extra_measurement) {
            $this->extraMeasurements[] = $extra_measurement->name;
            // If we have the Processors measurement, then we should also
            // compute and display 'Proc Time'.
            if ($extra_measurement->name === 'Processors') {
                $processors_idx = count($this->extraMeasurements) - 1;
                $response['hasprocessors'] = true;
            }
        }
        $this->numExtraMeasurements = count($this->extraMeasurements);
        $response['columnnames'] = $this->extraMeasurements;

        $params = [':buildid' => $buildid];
        $status_clause = 'AND build2test.status = :status';
        if ($onlypassed) {
            $params[':status'] = 'passed';
        } elseif ($onlyfailed) {
            $params[':status'] = 'failed';
        } elseif ($onlynotrun) {
            $params[':status'] = 'notrun';
        } else {
            $status_clause = '';
        }

        $getalltestlistsql = "SELECT build2test.id
            FROM build2test
            JOIN build ON (build.id = build2test.buildid)
            WHERE build.$buildid_field=:buildid $onlydelta_extra
            $status_clause
            ORDER BY build2test.id
            ";
        $getalltestlist = $this->db->prepare($getalltestlistsql);
        $this->db->execute($getalltestlist, $params);

        // Allocate empty array for all possible measurements.
        $test_measurements = [];

        while ($row = $getalltestlist->fetch()) {
            $test_measurements[$row['id']] = [];
            for ($i = 0; $i < $this->numExtraMeasurements; $i++) {
                $test_measurements[$row['id']][$i] = '';
            }
        }

        $etestquery = null;
        if ($this->numExtraMeasurements > 0) {
            $etestquery = $this->db->prepare(
                "SELECT build2test.id, test.projectid, build2test.buildid,
                    build2test.status, build2test.timestatus, test.name, testmeasurement.name,
                    testmeasurement.value, build.starttime,
                    build2test.time FROM test
                    JOIN build2test ON (build2test.testid = test.id)
                    JOIN build ON (build.id = build2test.buildid)
                    JOIN testmeasurement ON (build2test.outputid = testmeasurement.outputid)
                    JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
                    WHERE build.$buildid_field = :buildid
                    $onlydelta_extra
                    $status_clause
                    ORDER BY build2test.id, testmeasurement.name");
            $this->db->execute($etestquery, $params);
        }

        if (@$_GET['export'] == 'csv') {
            $this->JSONEncodeResponse = false;
            return $this->exportAsCsv($etestquery, null, $stmt, $this->project->ShowTestTime, $this->project->TestTimeMaxStatus);
        }

        // Keep track of extra measurements for each test.
        // Overwrite the empty values with the correct ones if exists.
        if ($etestquery) {
            while ($row = $etestquery->fetch()) {
                // Get the index of this extra measurement.
                $idx = array_search($row['name'], $this->extraMeasurements);

                // Fill in this measurement value for this test.
                $test_measurements[$row['id']][$idx] = $row['value'];
            }
        }

        // Gather test info
        $tests = [];

        // Find the time to run all the tests
        $time_stmt = $this->db->prepare(
            'SELECT SUM(time) FROM build2test WHERE buildid = ?');
        $this->db->execute($time_stmt, [$buildid]);
        $time = $time_stmt->fetchColumn();
        $response['totaltime'] = time_difference($time, true, '', true);

        // Gather date information.
        $testdate = $this->date;
        [$previousdate, $currentstarttime, $nextdate, $today] =
            get_dates($this->date, $this->project->NightlyTime);
        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;
        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);
        $response['time_begin'] = $beginning_UTCDate;
        $response['time_end'] = $end_UTCDate;
        $labels_found = false;

        // Generate a response for each test found.
        while ($row = $stmt->fetch()) {
            $marshaledTest = BuildTest::marshal($row, $row['buildid'], $this->build->ProjectId, $this->project->ShowTestTime, $this->project->TestTimeMaxStatus, $testdate);

            if ($marshaledTest['status'] == 'Passed') {
                $numPassed++;
            } elseif ($marshaledTest['status'] == 'Failed') {
                $numFailed++;
            } elseif ($marshaledTest['status'] == 'Not Run') {
                $numNotRun++;
            }

            if ($row['timestatus'] >= $this->project->TestTimeMaxStatus) {
                $numTimeFailed++;
            }

            $labels_found = (config('database.default') != 'pgsql' && !empty($marshaledTest['labels']));

            $marshaledTest['measurements'] = $test_measurements[$marshaledTest['buildtestid']];
            if ($response['hasprocessors']) {
                // Show an additional column "proc time" if these tests have
                // the Processor measurement.
                $num_procs = $test_measurements[$marshaledTest['buildtestid']][$processors_idx];
                if (!$num_procs) {
                    $num_procs = 1;
                }
                $marshaledTest['procTimeFull'] =
                    $marshaledTest['execTimeFull'] * $num_procs;
                $marshaledTest['procTime'] =
                    time_difference($marshaledTest['procTimeFull'], true, '', true);
            }
            $tests[] = $marshaledTest;
        }

        // Check for missing tests
        $numMissing = $this->build->GetNumberOfMissingTests();

        if ($numMissing > 0) {
            foreach ($this->build->MissingTests as $name) {
                $marshaledTest = buildtest::marshalMissing($name, $buildid, $this->build->ProjectId, $this->project->ShowTestTime, $this->project->TestTimeMaxStatus, $testdate);
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

        $response['columncount'] = $this->numExtraMeasurements;

        $this->pageTimer->end($response);
        return $response;
    }

    private function getTestHistory($testname, $previous_buildids)
    {
        $retval = [];

        // STRAIGHT_JOIN is a MySQL specific enhancement.
        $join_type = 'INNER JOIN';
        if (config('database.default') === 'mysql') {
            $join_type = 'STRAIGHT_JOIN';
        }

        $history_query = "
            SELECT DISTINCT status FROM build2test AS b2t
            $join_type test AS t ON (t.id = b2t.testid)
            WHERE b2t.buildid IN ($previous_buildids) AND t.name = :testname";
        $history_stmt = $this->db->prepare($history_query);
        $this->db->execute($history_stmt, [':testname' => $testname]);
        $statuses = [];
        while ($row = $history_stmt->fetch()) {
            $statuses[] = $row['status'];
        }
        $num_statuses = count($statuses);
        if ($num_statuses > 0) {
            if ($num_statuses > 1) {
                $retval['history'] = 'Unstable';
                $retval['historyclass'] = 'warning';
            } else {
                $status = $statuses[0];
                $retval['history'] = ucfirst($status);

                switch ($status) {
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

    private function getTestSummary($testname, $projectid, $groupid, $begin, $end)
    {
        $retval = [];

        // STRAIGHT_JOIN is a MySQL specific enhancement.
        $join_type = 'INNER JOIN';
        if (config('database.default') === 'mysql') {
            $join_type = 'STRAIGHT_JOIN';
        }

        $summary_query = "
            SELECT DISTINCT b2t.status FROM build AS b
            $join_type build2group AS b2g ON (b.id = b2g.buildid)
            $join_type build2test AS b2t ON (b.id = b2t.buildid)
            $join_type test AS t ON (b2t.testid = t.id)
            WHERE b2g.groupid = :groupid
            AND b.projectid = :projectid
            AND b.starttime >= :begin
            AND b.starttime < :end
            AND t.name = :testname";
        $params = [
            ':groupid' => $groupid,
            ':projectid' => $projectid,
            ':begin' => $begin,
            ':end' => $end,
            ':testname' => $testname,
        ];
        $summary_stmt = $this->db->prepare($summary_query);
        $this->db->execute($summary_stmt, $params);
        $statuses = [];
        while ($row = $summary_stmt->fetch()) {
            $statuses[] = $row['status'];
        }
        $num_statuses = count($statuses);
        if ($num_statuses > 0) {
            if ($num_statuses > 1) {
                $retval['summary'] = 'Unstable';
                $retval['summaryclass'] = 'warning';
            } else {
                $status = $statuses[0];
                $retval['summary'] = ucfirst($status);
                switch ($status) {
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

    private function loadTestDetails()
    {
        // Parse input arguments.
        $tests = [];
        foreach ($_GET['tests'] as $test) {
            $tests[] = pdo_real_escape_string($test);
        }
        if (empty($tests)) {
            return;
        }

        $projectid = pdo_real_escape_numeric($_GET['projectid']);

        $previous_buildids = [];
        if (array_key_exists('previous_builds', $_GET)) {
            foreach (explode(', ', $_GET['previous_builds']) as $previous_buildid) {
                if (is_numeric($previous_buildid) && $previous_buildid > 1) {
                    $previous_build_row = \DB::table('build')
                        ->where('id', $previous_buildid)
                        ->first();
                    if ($previous_build_row->projectid == $projectid) {
                        $previous_buildids[] = $previous_buildid;
                    }
                }
            }
        }
        $previous_builds = implode(', ', $previous_buildids);
        $time_begin = '';
        if (array_key_exists('time_begin', $_GET)) {
            $time_begin = pdo_real_escape_string($_GET['time_begin']);
        }
        $time_end = '';
        if (array_key_exists('time_end', $_GET)) {
            $time_end = pdo_real_escape_string($_GET['time_end']);
        }
        $groupid = pdo_real_escape_numeric($_GET['groupid']);

        $response = [];
        $tests_response = [];

        foreach ($tests as $test) {
            $test_response = [];
            $test_response['name'] = $test;
            $data_found = false;

            if ($time_begin && $time_end) {
                $summary_response = $this->getTestSummary($test, $projectid, $groupid,
                    $time_begin, $time_end);
                if (!empty($summary_response)) {
                    $test_response = array_merge($test_response, $summary_response);
                    $data_found = true;
                }
            }

            if ($previous_builds) {
                $history_response = $this->getTestHistory($test, $previous_builds);
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
    private function exportAsCsv($etestquery, $etest, $stmt, $projectshowtesttime, $testtimemaxstatus)
    {
        // Store named measurements in an array
        if (!is_null($etestquery)) {
            while ($row = $etestquery->fetch()) {
                $etest[$row['id']][$row['name']] = $row['value'];
            }
        }

        $csv_contents = [];
        // Standard columns.
        $csv_headers = ['Name', 'Time','Details', 'Status'];
        if ($projectshowtesttime) {
            $csv_headers[] = 'Time Status';
        }

        for ($c = 0; $c < $this->numExtraMeasurements; $c++) {
            // Add extra coluns.
            $csv_headers[] = $this->extraMeasurements[$c];
        }
        $csv_contents[] = $csv_headers;

        while ($row = $stmt->fetch()) {
            $csv_row = [];
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
            for ($t = 0; $t < $this->numExtraMeasurements; $t++) {
                $csv_row[] = $etest[$row['id']][$this->extraMeasurements[$t]];
            }
            $csv_contents[] = $csv_row;
        }

        $output = fopen('php://temp', 'w');
        foreach ($csv_contents as $csv_row) {
            fputcsv($output, $csv_row);
        }
        rewind($output);

        $file = stream_get_contents($output);

        fclose($output);
        return $file;
    }
}
