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

use CDash\Model\Build;
use Illuminate\Support\Facades\DB;

/** Compute the timing for test
 *  For each test we compare with the previous build and if the percentage time
 *  is more than the project.testtimepercent we increas test.timestatus by one.
 *  We also store the test.reftime which is the time of the test passing
 *
 *  If test.timestatus is more than project.testtimewindow we reset
 *  the test.timestatus to zero and we set the test.reftime to the previous build time.
 */
function ComputeTestTiming($days = 4)
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
        $builds = pdo_query("SELECT starttime,siteid,name,type,id
                FROM build
                WHERE build.projectid='$projectid' AND build.starttime>'$now'
                ORDER BY build.starttime ASC");

        $total = pdo_num_rows($builds);
        echo pdo_error();

        $i = 0;
        $previousperc = 0;
        while ($build_array = pdo_fetch_array($builds)) {
            $buildid = $build_array['id'];
            $buildname = $build_array['name'];
            $buildtype = $build_array['type'];
            $starttime = $build_array['starttime'];
            $siteid = $build_array['siteid'];

            // Find the previous build
            $previousbuild = pdo_query("SELECT id FROM build
                    WHERE build.siteid='$siteid'
                    AND build.type='$buildtype' AND build.name='$buildname'
                    AND build.projectid='$projectid'
                    AND build.starttime<'$starttime'
                    AND build.starttime>'$now'
                    ORDER BY build.starttime DESC LIMIT 1");

            echo pdo_error();

            // If we have one
            if (pdo_num_rows($previousbuild) > 0) {
                // Loop through the tests
                $previousbuild_array = pdo_fetch_array($previousbuild);
                $previousbuildid = $previousbuild_array ['id'];

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

/** Compute the statistics for the updated file. Number of produced errors, warning, test failings. */
function ComputeUpdateStatistics($days = 4)
{
    // Loop through the projects
    $project = pdo_query('SELECT id FROM project');

    while ($project_array = pdo_fetch_array($project)) {
        $projectid = $project_array['id'];

        // only test a couple of days
        $now = gmdate(FMT_DATETIME, time() - 3600 * 24 * $days);

        // Find the builds
        $builds = pdo_query("SELECT starttime,siteid,name,type,id
                FROM build
                WHERE build.projectid='$projectid' AND build.starttime>'$now'
                ORDER BY build.starttime ASC");

        $total = pdo_num_rows($builds);
        echo pdo_error();

        $i = 0;
        $previousperc = 0;
        while ($build_array = pdo_fetch_array($builds)) {
            $Build = new Build();
            $Build->Id = $build_array['id'];
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

/** Delete unused rows */
function delete_unused_rows($table, $field, $targettable, $selectfield = 'id')
{
    DB::delete("DELETE FROM $table WHERE $field NOT IN (SELECT $selectfield AS $field FROM $targettable)");
    echo pdo_error();
}
