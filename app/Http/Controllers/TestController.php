<?php
namespace App\Http\Controllers;

use App\Models\BuildTest;
use CDash\Database;
use Illuminate\View\View;

class TestController extends AbstractProjectController
{
    // Render the test details page.
    public function details($buildtest_id = null)
    {
        $buildtest = BuildTest::findOrFail($buildtest_id);
        $this->setProjectById($buildtest->test->projectid);
        return view('test.details')
            ->with('title', 'Test Results')
            ->with('project', $this->project);
    }

    public function ajaxTestFailureGraph(): View
    {
        $this->setProjectById((int)($_GET['projectid'] ?? -1));

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
                              AND build2test.testid IN (
                                  SELECT id
                                  FROM test
                                  WHERE name = ?
                              )
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

        return view('test.ajax-test-failure-graph')
            ->with('tarray', $tarray);
    }
}
