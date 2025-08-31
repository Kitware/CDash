<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Utils\PageTimer;
use App\Utils\RepositoryUtils;
use CDash\Controller\Api\QueryTests as LegacyQueryTestsController;
use CDash\Controller\Api\TestDetails as LegacyTestDetailsController;
use CDash\Controller\Api\TestGraph as LegacyTestGraphController;
use CDash\Controller\Api\TestOverview as LegacyTestOverviewController;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TestController extends AbstractProjectController
{
    // Render the test details page.
    public function details(int $buildtest_id): View
    {
        $buildtest = Test::findOrFail($buildtest_id);
        $projectid = $buildtest->build?->projectid;

        if ($projectid === null) {
            abort(500, "Unable to find build associated with test $buildtest->id.");
        }

        $this->setProjectById($projectid);
        return $this->vue('test-details', 'Test Results', [], false);
    }

    public function apiTestDetails(): JsonResponse|StreamedResponse
    {
        $buildtestid = request()->input('buildtestid');
        if (!is_numeric($buildtestid)) {
            abort(400, 'A valid test was not specified.');
        }

        $buildtest = Test::where('id', '=', $buildtestid)->first();
        if ($buildtest === null) {
            // Create a dummy project object to prevent information leakage between different error cases
            $project = new Project();
            $project->Id = -1;
        } else {
            $build = new Build();
            $build->Id = $buildtest->buildid;
            $build->FillFromId($build->Id);
            $project = $build->GetProject();
        }

        Gate::authorize('view-project', $project);

        // This case should never occur since it should always be caught by the Gate::authorize check above.
        // This is only here to satisfy PHPStan...
        if ($buildtest === null) {
            abort(500);
        }

        $controller = new LegacyTestDetailsController(Database::getInstance(), $buildtest);
        return $controller->getResponse();
    }

    public function ajaxTestFailureGraph(): View
    {
        $this->setProjectById((int) ($_GET['projectid'] ?? -1));

        if (!isset($_GET['testname'])) {
            abort(400, 'Not a valid test name!');
        }
        $testname = htmlspecialchars($_GET['testname']);

        if (!isset($_GET['starttime'])) {
            abort(400, 'Not a valid starttime!');
        }
        $starttime = $_GET['starttime'];

        $db = Database::getInstance();

        // We have to loop for the previous days
        $failures = [];
        for ($beginning_timestamp = $starttime; $beginning_timestamp > $starttime - 3600 * 24 * 7; $beginning_timestamp -= 3600 * 24) {
            $end_timestamp = $beginning_timestamp + 3600 * 24;

            $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
            $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

            $result = $db->executePreparedSingleRow("
                          SELECT count(*) AS c
                          FROM build
                          JOIN build2test ON (build.id = build2test.buildid)
                          WHERE
                              build.projectid = ?
                              AND build.starttime >= ?
                              AND build.starttime < ?
                              AND build2test.testname = ?
                              AND (
                                  build2test.status <> 'passed'
                                  OR build2test.timestatus <> 0
                              )
                      ", [$this->project->Id, $beginning_UTCDate, $end_UTCDate, $testname]);
            $failures[$beginning_timestamp] = (int) $result['c'];
        }

        $tarray = [];
        foreach ($failures as $key => $value) {
            $t = [
                'x' => $key,
                'y' => $value,
            ];
            $tarray[] = $t;
        }
        $tarray = array_reverse($tarray);

        return $this->view('test.ajax-test-failure-graph', '')
            ->with('tarray', $tarray);
    }

    public function queryTests(): View
    {
        $this->setProjectByName(request()->string('project'));
        return $this->angular_view('queryTests', 'Query Tests');
    }

    public function apiQueryTests(): JsonResponse
    {
        if (!request()->has('project')) {
            return response()->json(['error' => 'Valid project required']);
        }

        $this->setProjectByName(request()->string('project'));

        $controller = new LegacyQueryTestsController(Database::getInstance(), $this->project);
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }

    public function testOverview(): View
    {
        $this->setProjectByName(request()->input('project'));
        return $this->angular_view('testOverview', 'Test Overview');
    }

    public function apiTestOverview(): JsonResponse
    {
        if (!request()->has('project')) {
            return response()->json(['error' => 'Valid project required']);
        }

        $this->setProjectByName(request()->input('project'));

        $db = Database::getInstance();
        $controller = new LegacyTestOverviewController($db, $this->project);
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }

    public function testSummary(): View
    {
        $this->setProjectById(request()->integer('project'));
        return $this->angular_view('testSummary', 'Test Summary');
    }

    public function apiTestSummary(): JsonResponse|StreamedResponse
    {
        // Checks
        $date = htmlspecialchars($_GET['date'] ?? '');
        if (strlen($date) === 0) {
            abort(400, 'No date specified.');
        }
        $this->setProjectById(intval($_GET['project'] ?? -1));

        $testName = $_GET['name'] ?? '';
        if ($testName === '' || !is_string($testName)) {
            abort(400, 'No test name specified.');
        }

        $pageTimer = new PageTimer();

        $response = begin_JSON_response();
        $response['showcalendar'] = 1;
        $response['title'] = "{$this->project->Name} - Test Summary";
        get_dashboard_JSON($this->project->Name, $date, $response);
        $response['testName'] = $testName;

        [$previousdate, $currentstarttime, $nextdate, $today] = get_dates($date, $this->project->NightlyTime);
        $menu = [
            'back' => 'index.php?project=' . urlencode($this->project->Name) . "&date=$date",
            'previous' => "testSummary.php?project={$this->project->Id}&name=$testName&date=$previousdate",
            'current' => "testSummary.php?project={$this->project->Id}&name=$testName&date=" . date(FMT_DATE),
        ];
        if (date(FMT_DATE, $currentstarttime) != date(FMT_DATE)) {
            $menu['next'] = "testSummary.php?project={$this->project->Id}&name=$testName&date=$nextdate";
        } else {
            $menu['next'] = false;
        }
        $response['menu'] = $menu;

        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;

        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        // Count how many extra test measurements we have.
        $getcolumnnumber = DB::select('
            SELECT testmeasurement.name
            FROM build2test
            JOIN build ON (build.id = build2test.buildid)
            JOIN testmeasurement ON (build2test.id = testmeasurement.testid)
            JOIN measurement ON (
                build.projectid=measurement.projectid
                AND testmeasurement.name=measurement.name
            )
            WHERE
                build2test.testname=?
                AND build.starttime>=?
                AND build.starttime<?
                AND build.projectid=?
            GROUP by testmeasurement.name
        ', [$testName, $beginning_UTCDate, $end_UTCDate, intval($this->project->Id)]);

        $columns = [];
        $response['hasprocessors'] = false;
        $processors_idx = -1;
        foreach ($getcolumnnumber as $row) {
            $columns[] = $row->name;
            if ($row->name === 'Processors') {
                $processors_idx = count($columns) - 1;
                $response['hasprocessors'] = true;
            }
        }
        $response['columns'] = $columns;

        // Add the date/time
        $response['projectid'] = $this->project->Id;
        $response['currentstarttime'] = $currentstarttime;
        $response['teststarttime'] = date(FMT_DATETIME, $beginning_timestamp);
        $response['testendtime'] = date(FMT_DATETIME, $end_timestamp);

        $columncount = count($getcolumnnumber);

        $etestquery = null;
        // If at least one column is selected
        if ($columncount > 0) {
            $etestquery = DB::select('
                SELECT
                    build.projectid,
                    build2test.buildid,
                    build2test.status,
                    build2test.timestatus,
                    build2test.testname AS name,
                    testmeasurement.name,
                    testmeasurement.value,
                    build.starttime,
                    build2test.time
                FROM build2test
                JOIN build ON (build.id = build2test.buildid)
                JOIN testmeasurement ON (build2test.id = testmeasurement.testid)
                JOIN measurement ON (
                    build.projectid = measurement.projectid
                    AND testmeasurement.name = measurement.name
                )
                WHERE
                    build2test.testname=?
                    AND build.starttime >= ?
                    AND build.starttime < ?
                    AND build.projectid = ?
                ORDER BY
                    build2test.buildid,
                    testmeasurement.name
            ', [$testName, $beginning_UTCDate, $end_UTCDate, intval($this->project->Id)]);
        }

        $result = DB::select('
            SELECT
                b.id AS buildid,
                b.name,
                b.stamp,
                b2t.id AS buildtestid,
                b2t.status,
                b2t.time,
                s.name AS sitename
            FROM build2test AS b2t
            LEFT JOIN build AS b ON (b.id = b2t.buildid)
            LEFT JOIN site AS s ON (s.id = b.siteid)
            WHERE
                b2t.testname = ?
                AND b.projectid = ?
                AND b.starttime BETWEEN ? AND ?
        ', [$testName, intval($this->project->Id), $beginning_UTCDate, $end_UTCDate]);

        // If user wants to export as CSV file.
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            //    header('Cache-Control: public');
            //    header('Content-Description: File Transfer');
            //    // Prepare some headers to download.
            //    header('Content-Disposition: attachment; filename=testExport.csv');
            //    header('Content-Type: application/octet-stream;');
            //    header('Content-Transfer-Encoding: binary');
            // Standard columns.
            $filecontent = 'Site,Build Name,Build Stamp,Status,Time(s)';

            $etest = [];

            // Store named measurements in an array.
            if (is_array($etestquery)) {
                foreach ($etestquery as $row) {
                    $etest[$row->buildid][$row->name] = $row->value;
                }
            }

            for ($c = 0; $c < count($columns); $c++) {
                $filecontent .= ',' . $columns[$c]; // Add selected columns to the next
            }

            $filecontent .= "\n";

            foreach ($result as $row) {
                $currentStatus = $row->status;

                $filecontent .= "{$row->sitename},{$row->name},{$row->stamp},{$row->time},";

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
                // Start writing test results
                for ($t = 0; $t < count($columns); $t++) {
                    $filecontent .= $etest[$row->buildid][$columns[$t]] . ',';
                }
                $filecontent .= "\n";
            }

            return response()->streamDownload(function () use ($filecontent): void {
                echo $filecontent;
            }, 'test-export.csv', ['Content-type' => 'text/csv']);
        }

        // Now that we have the data we need, generate our response.
        $numpassed = 0;
        $numfailed = 0;
        $numtotal = 0;
        $test_measurements = [];

        $buildids = [];
        foreach ($result as $row) {
            $buildids[] = (int) $row->buildid;
        }
        $buildids = array_values(array_unique($buildids));
        $status_by_buildid = [];
        if ($buildids !== []) {
            $prepared_array = Database::getInstance()->createPreparedArray(count($buildids));
            $query = DB::select("
                SELECT
                    b2u.buildid as buildid,
                    status,
                    revision,
                    priorrevision,
                    path
                FROM
                    buildupdate,
                    build2update AS b2u
                WHERE
                    b2u.updateid = buildupdate.id
                    AND b2u.buildid IN $prepared_array
            ", $buildids);
            foreach ($query as $row) {
                $status_by_buildid[(int) $row->buildid] = $row;
            }
        }

        $builds_response = [];
        foreach ($result as $row) {
            $buildid = (int) $row->buildid;
            $build_response = [];

            // Find the repository revision
            $update_response = [
                'revision' => '',
                'priorrevision' => '',
                'path' => '',
                'revisionurl' => '',
                'revisiondiff' => '',
            ];

            if (isset($status_by_buildid[$buildid])) {
                $status_array = $status_by_buildid[$buildid];
                if (strlen($status_array->status) > 0 && $status_array->status != '0') {
                    $update_response['status'] = $status_array->status;
                } else {
                    $update_response['status'] = ''; // empty status
                }
                $update_response['revision'] = $status_array->revision;
                $update_response['priorrevision'] = $status_array->priorrevision;
                $update_response['path'] = $status_array->path;
                $update_response['revisionurl'] =
                    RepositoryUtils::get_revision_url($this->project->Id, $status_array->revision, $status_array->priorrevision);
                $update_response['revisiondiff'] =
                    RepositoryUtils::get_revision_url($this->project->Id, $status_array->priorrevision, ''); // no prior prior revision...
            }
            $build_response['update'] = $update_response;

            $build_response['site'] = $row->sitename;
            $build_response['buildName'] = $row->name;
            $build_response['buildStamp'] = $row->stamp;
            $build_response['time'] = floatval($row->time);

            $buildLink = "viewTest.php?buildid=$buildid";
            $build_response['buildid'] = $buildid;
            $build_response['buildLink'] = $buildLink;
            $buildtestid = $row->buildtestid;
            $testLink = "test/$buildtestid";
            $build_response['testLink'] = $testLink;
            switch ($row->status) {
                case 'passed':
                    $build_response['status'] = 'Passed';
                    $build_response['statusclass'] = 'normal';
                    $numpassed++;
                    break;
                case 'failed':
                    $build_response['status'] = 'Failed';
                    $build_response['statusclass'] = 'error';
                    $numfailed++;
                    break;
                case 'notrun':
                    $build_response['status'] = 'Not Run';
                    $build_response['statusclass'] = 'warning';
                    break;
            }
            $numtotal++;

            // Initialize an empty array of extra test measurements for this build.
            $test_measurements[$buildid] = [];
            for ($i = 0; $i < $columncount; $i++) {
                $test_measurements[$buildid][$i] = '';
            }

            $builds_response[] = $build_response;
        }

        // Fill in extra test measurements for each build.
        if ($columncount > 0) {
            $etestquery = DB::select('
                SELECT
                    build.projectid,
                    build2test.buildid,
                    build2test.status,
                    build2test.timestatus,
                    build2test.testname,
                    testmeasurement.name,
                    testmeasurement.value,
                    build.starttime,
                    build2test.time
                FROM build2test
                JOIN build ON (build.id = build2test.buildid)
                JOIN testmeasurement ON (build2test.id = testmeasurement.testid)
                JOIN measurement ON (
                    build.projectid = measurement.projectid
                    AND testmeasurement.name = measurement.name
                )
                WHERE
                    build2test.testname = ?
                    AND build.starttime >= ?
                    AND build.starttime < ?
                    AND build.projectid = ?
                ORDER BY
                    build2test.buildid,
                    testmeasurement.name
            ', [$testName, $beginning_UTCDate, $end_UTCDate, intval($this->project->Id)]);
            if (is_array($etestquery)) {
                foreach ($etestquery as $row) {
                    // Get the index of this measurement in the list of columns.
                    $idx = array_search($row->name, $columns, true);

                    // Fill in this measurement value for this build's run of the test.
                    $test_measurements[$row->buildid][$idx] = $row->value;
                }
            }
        }

        // Assign these extra measurements to each build.
        foreach ($builds_response as $i => $build_response) {
            $buildid = $build_response['buildid'];
            $builds_response[$i]['measurements'] = $test_measurements[$buildid];
            if ($response['hasprocessors']) {
                // Show an additional column "proc time" if these tests have
                // the Processor measurement.
                $num_procs = $test_measurements[$buildid][$processors_idx];
                if (!$num_procs) {
                    $num_procs = 1;
                }
                $builds_response[$i]['proctime'] = floatval($builds_response[$i]['time'] * $num_procs);
            }
        }

        $response['builds'] = $builds_response;
        $response['csvlink'] = $_SERVER['REQUEST_URI'] . '&export=csv';
        $response['columncount'] = count($columns);
        $response['numfailed'] = $numfailed;
        $response['numtotal'] = $numtotal;
        $response['percentagepassed'] = $numtotal > 0 ? round($numpassed / $numtotal, 2) * 100 : 0;

        $pageTimer->end($response);
        return response()->json($response);
    }

    public function apiTestGraph(): JsonResponse
    {
        if (!request()->has('buildid')) {
            abort(400, '"buildid" parameter is required.');
        }
        $buildid = (int) request()->input('buildid');
        $build = new Build();
        $build->FillFromId($buildid);
        Gate::authorize('view-project', $build->GetProject());

        $db = Database::getInstance();

        $testname = request()->input('testname');

        $buildtest = Test::where('buildid', '=', $buildid)
            ->where('testname', '=', $testname)
            ->first();
        if ($buildtest === null) {
            abort(404, 'test not found');
        }

        $controller = new LegacyTestGraphController($db, $buildtest);
        $response = $controller->getResponse();
        return response()->json(cast_data_for_JSON($response));
    }
}
