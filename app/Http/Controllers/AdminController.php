<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\DatabaseCleanupUtils;
use CDash\Model\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

require_once 'include/api_common.php';
require_once 'include/ctestparser.php';

final class AdminController extends AbstractController
{
    public function removeBuilds(): View|RedirectResponse
    {
        @set_time_limit(0);

        $projectid = intval($_GET['projectid'] ?? 0);

        $alert = '';

        // get date info here
        @$dayTo = intval($_POST['dayFrom']);
        if (empty($dayTo)) {
            $time = strtotime('2000-01-01 00:00:00');

            if ($projectid > 0) {
                // find the first and last builds
                $starttime = DB::select('
                    SELECT starttime
                    FROM build
                    WHERE projectid=?
                    ORDER BY starttime ASC
                    LIMIT 1
                ', [$projectid]);
                if (count($starttime) === 1) {
                    $time = strtotime($starttime[0]->starttime);
                }
            }
            $dayFrom = date('d', $time);
            $monthFrom = date('m', $time);
            $yearFrom = date('Y', $time);
            $dayTo = date('d');
            $yearTo = date('Y');
            $monthTo = date('m');
        } else {
            $dayFrom = intval($_POST['dayFrom']);
            $monthFrom = intval($_POST['monthFrom']);
            $yearFrom = intval($_POST['yearFrom']);
            $dayTo = intval($_POST['dayTo']);
            $monthTo = intval($_POST['monthTo']);
            $yearTo = intval($_POST['yearTo']);
        }

        // List the available projects
        $available_projects = [];
        $projects = DB::select('SELECT id, name FROM project');
        foreach ($projects as $projects_array) {
            $available_project = new Project();
            $available_project->Id = (int) $projects_array->id;
            $available_project->Name = $projects_array->name;
            $available_projects[] = $available_project;
        }

        // Delete the builds
        if (isset($_POST['Submit'])) {
            if (config('database.default') === 'pgsql') {
                $timestamp_sql = "CAST(CONCAT(?, '-', ?, '-', ?, ' 00:00:00') AS timestamp)";
            } else {
                $timestamp_sql = "TIMESTAMP(CONCAT(?, '-', ?, '-', ?, ' 00:00:00'))";
            }

            $build = DB::select("
                         SELECT id
                         FROM build
                         WHERE
                             projectid = ?
                             AND parentid IN (0, -1)
                             AND starttime <= $timestamp_sql
                             AND starttime >= $timestamp_sql
                         ORDER BY starttime ASC
                     ", [
                $projectid,
                $yearTo,
                $monthTo,
                $dayTo,
                $yearFrom,
                $monthFrom,
                $dayFrom,
            ]);

            $builds = [];
            foreach ($build as $build_array) {
                $builds[] = (int) $build_array->id;
            }

            DatabaseCleanupUtils::removeBuildChunked($builds);
            $alert = 'Removed ' . count($builds) . ' builds.';
        }

        return $this->view('admin.remove-builds')
            ->with('alert', $alert)
            ->with('selected_projectid', $projectid)
            ->with('available_projects', $available_projects)
            ->with('monthFrom', $monthFrom)
            ->with('dayFrom', $dayFrom)
            ->with('yearFrom', $yearFrom)
            ->with('monthTo', $monthTo)
            ->with('dayTo', $dayTo)
            ->with('yearTo', $yearTo);
    }

    /** Compute the timing for test
     *  For each test we compare with the previous build and if the percentage time
     *  is more than the project.testtimepercent we increas test.timestatus by one.
     *  We also store the test.reftime which is the time of the test passing
     *
     *  If test.timestatus is more than project.testtimewindow we reset
     *  the test.timestatus to zero and we set the test.reftime to the previous build time.
     */
    private static function ComputeTestTiming($days = 4): void
    {
        // Loop through the projects
        $project = pdo_query('SELECT id,testtimestd,testtimestdthreshold FROM project');
        $weight = 0.3;

        while ($project_array = pdo_fetch_array($project)) {
            $projectid = $project_array['id'];
            $testtimestd = $project_array['testtimestd'];
            $projecttimestdthreshold = $project_array['testtimestdthreshold'];

            // only test a couple of days
            $now = gmdate(FMT_DATETIME, time() - 3600 * 24 * $days);

            // Find the builds
            $builds = DB::select("SELECT starttime,siteid,name,type,id
                FROM build
                WHERE build.projectid='$projectid' AND build.starttime>'$now'
                ORDER BY build.starttime ASC");

            $total = count($builds);
            echo pdo_error();

            $i = 0;
            $previousperc = 0;
            foreach ($builds as $build) {
                $buildid = $build->id;
                $buildname = $build->name;
                $buildtype = $build->type;
                $starttime = $build->starttime;
                $siteid = $build->siteid;

                // Find the previous build
                $previousbuild = DB::select("SELECT id FROM build
                    WHERE build.siteid='$siteid'
                    AND build.type='$buildtype' AND build.name='$buildname'
                    AND build.projectid='$projectid'
                    AND build.starttime<'$starttime'
                    AND build.starttime>'$now'
                    ORDER BY build.starttime DESC LIMIT 1");

                echo pdo_error();

                // If we have one
                if (count($previousbuild) > 0) {
                    // Loop through the tests
                    $previousbuildid = $previousbuild->id;

                    $tests = pdo_query("SELECT build2test.time,build2test.testid,test.name
                        FROM build2test,test WHERE build2test.buildid='$buildid'
                        AND build2test.testid=test.id
                        ");
                    echo pdo_error();

                    flush();
                    ob_flush();

                    // Find the previous test
                    $previoustest = pdo_query("SELECT build2test.testid,test.name FROM build2test,test
                        WHERE build2test.buildid='$previousbuildid'
                        AND test.id=build2test.testid
                        ");
                    echo pdo_error();

                    $testarray = [];
                    while ($test_array = pdo_fetch_array($previoustest)) {
                        $test = [];
                        $test['id'] = $test_array['testid'];
                        $test['name'] = $test_array['name'];
                        $testarray[] = $test;
                    }

                    while ($test_array = pdo_fetch_array($tests)) {
                        $testtime = $test_array['time'];
                        $testid = $test_array['testid'];
                        $testname = $test_array['name'];

                        $previoustestid = 0;

                        foreach ($testarray as $test) {
                            if ($test['name'] == $testname) {
                                $previoustestid = $test['id'];
                                break;
                            }
                        }

                        if ($previoustestid > 0) {
                            $previoustest = pdo_query("SELECT timemean,timestd FROM build2test
                                WHERE buildid='$previousbuildid'
                                AND build2test.testid='$previoustestid'
                                ");

                            $previoustest_array = pdo_fetch_array($previoustest);
                            $previoustimemean = $previoustest_array['timemean'];
                            $previoustimestd = $previoustest_array['timestd'];

                            // Check the current status
                            if ($previoustimestd < $projecttimestdthreshold) {
                                $previoustimestd = $projecttimestdthreshold;
                            }

                            // Update the mean and std
                            $timemean = (1 - $weight) * $previoustimemean + $weight * $testtime;
                            $timestd = sqrt((1 - $weight) * $previoustimestd * $previoustimestd + $weight * ($testtime - $timemean) * ($testtime - $timemean));

                            // Check the current status
                            if ($testtime > $previoustimemean + $testtimestd * $previoustimestd) {
                                // only do positive std

                                $timestatus = 1; // flag
                            } else {
                                $timestatus = 0;
                            }
                        } else {
                            // the test doesn't exist

                            $timestd = 0;
                            $timestatus = 0;
                            $timemean = $testtime;
                        }

                        pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus'
                            WHERE buildid='$buildid' AND testid='$testid'");
                    }
                } else {
                    // this is the first build

                    $timestd = 0;
                    $timestatus = 0;

                    // Loop throught the tests
                    $tests = pdo_query("SELECT time,testid FROM build2test WHERE buildid='$buildid'");
                    while ($test_array = pdo_fetch_array($tests)) {
                        $timemean = $test_array['time'];
                        $testid = $test_array['testid'];

                        pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus'
                            WHERE buildid='$buildid' AND testid='$testid'");
                    }
                } // loop through the tests

                // Progress bar
                $perc = ($i / $total) * 100;
                if ($perc - $previousperc > 5) {
                    echo round($perc, 3) . '% done.<br>';
                    flush();
                    ob_flush();
                    $previousperc = $perc;
                }
                $i++;
            }
        }
    }

    private static function ComputeUpdateStatistics($days = 4): void
    {
        // Loop through the projects
        $project = pdo_query('SELECT id FROM project');

        while ($project_array = pdo_fetch_array($project)) {
            $projectid = $project_array['id'];

            // only test a couple of days
            $now = gmdate(FMT_DATETIME, time() - 3600 * 24 * $days);

            // Find the builds
            $builds = DB::select("SELECT starttime,siteid,name,type,id
                FROM build
                WHERE build.projectid='$projectid' AND build.starttime>'$now'
                ORDER BY build.starttime ASC");

            $total = count($builds);
            echo pdo_error();

            $i = 0;
            $previousperc = 0;
            foreach ($builds as $build) {
                $Build = new Build();
                $Build->Id = $build->id;
                $Build->ProjectId = $projectid;
                $Build->ComputeUpdateStatistics();

                // Progress bar
                $perc = ($i / $total) * 100;
                if ($perc - $previousperc > 5) {
                    echo round($perc, 3) . '% done.<br>';
                    flush();
                    ob_flush();
                    $previousperc = $perc;
                }
                $i++;
            }
        }
    }

    public function upgrade()
    {
        @set_time_limit(0);

        $xml = begin_XML_for_XSLT();
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Maintenance</menusubtitle>';

        @$AssignBuildToDefaultGroups = $_POST['AssignBuildToDefaultGroups'];
        @$FixBuildBasedOnRule = $_POST['FixBuildBasedOnRule'];
        @$DeleteBuildsWrongDate = $_POST['DeleteBuildsWrongDate'];
        @$CheckBuildsWrongDate = $_POST['CheckBuildsWrongDate'];
        @$ComputeTestTiming = $_POST['ComputeTestTiming'];
        @$ComputeUpdateStatistics = $_POST['ComputeUpdateStatistics'];

        // Compute the testtime
        if ($ComputeTestTiming) {
            $TestTimingDays = (int) ($_POST['TestTimingDays'] ?? 0);
            if ($TestTimingDays > 0) {
                self::ComputeTestTiming($TestTimingDays);
                $xml .= add_XML_value('alert', 'Timing for tests has been computed successfully.');
            } else {
                $xml .= add_XML_value('alert', 'Wrong number of days.');
            }
        }

        // Compute the user statistics
        if ($ComputeUpdateStatistics) {
            $UpdateStatisticsDays = (int) ($_POST['UpdateStatisticsDays'] ?? 0);
            if ($UpdateStatisticsDays > 0) {
                self::ComputeUpdateStatistics($UpdateStatisticsDays);
                $xml .= add_XML_value('alert', 'User statistics has been computed successfully.');
            } else {
                $xml .= add_XML_value('alert', 'Wrong number of days.');
            }
        }

        /* Check the builds with wrong date */
        if ($CheckBuildsWrongDate) {
            $currentdate = time() + 3600 * 24 * 3; // or 3 days away from now
            $forwarddate = date(FMT_DATETIME, $currentdate);

            $builds = pdo_query("SELECT id,name,starttime FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
            while ($builds_array = pdo_fetch_array($builds)) {
                echo $builds_array['name'] . '-' . $builds_array['starttime'] . '<br>';
            }
        }

        /* Delete the builds with wrong date */
        if ($DeleteBuildsWrongDate) {
            $currentdate = time() + 3600 * 24 * 3; // or 3 days away from now
            $forwarddate = date(FMT_DATETIME, $currentdate);

            $builds = pdo_query(
                "SELECT id FROM build WHERE parentid IN (0, -1) AND
          starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
            while ($builds_array = pdo_fetch_array($builds)) {
                $buildid = $builds_array['id'];
                DatabaseCleanupUtils::removeBuild($buildid);
            }
        }

        if ($FixBuildBasedOnRule) {
            // loop through the list of build2group
            $buildgroups = pdo_query('SELECT * from build2group');
            while ($buildgroup_array = pdo_fetch_array($buildgroups)) {
                $buildid = $buildgroup_array['buildid'];

                $build = pdo_query("SELECT * from build WHERE id='$buildid'");
                $build_array = pdo_fetch_array($build);
                $type = $build_array['type'];
                $name = $build_array['name'];
                $siteid = $build_array['siteid'];
                $projectid = $build_array['projectid'];
                $submittime = $build_array['submittime'];

                $build2grouprule = DB::select("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                    WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                    AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                    AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");
                echo pdo_error();
                if (count($build2grouprule) > 0) {
                    $groupid = $build2grouprule[0]->groupid;
                    DB::update("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
                }
            }
        }

        if ($AssignBuildToDefaultGroups) {
            // Loop throught the builds
            $builds = pdo_query('SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)');

            while ($build_array = pdo_fetch_array($builds)) {
                $buildid = $build_array['id'];
                $buildtype = $build_array['type'];
                $projectid = $build_array['projectid'];

                $buildgroup_array = pdo_fetch_array(pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));

                $groupid = $buildgroup_array['id'];
                DB::insert("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')");
            }

            $xml .= add_XML_value('alert', 'Builds have been added to default groups successfully.');
        }

        $xml .= '</cdash>';

        return $this->view('cdash', 'Maintenance')
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/upgrade', true));
    }

    public function userStatistics(): View
    {
        return $this->angular_view('userStatistics');
    }
}
