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

use CDash\Model\ClientJob;
use CDash\Model\ClientJobSchedule;
use CDash\Model\Job;

/** Remove builds by their group-specific auto-remove timeframe setting */
function removeBuildsGroupwise($projectid, $maxbuilds, $force = false)
{
    require_once 'config/config.php';
    require_once 'include/pdo.php';
    require_once 'include/common.php';

    if (!$force && !isset($CDASH_AUTOREMOVE_BUILDS)) {
        return;
    }

    @set_time_limit(0);

    $buildgroups = pdo_query('SELECT id,autoremovetimeframe FROM buildgroup WHERE projectid=' . qnum($projectid));

    $buildids = array();
    while ($buildgroup = pdo_fetch_array($buildgroups)) {
        $days = $buildgroup['autoremovetimeframe'];

        if ($days < 2) {
            continue;
        }
        $groupid = $buildgroup['id'];

        $cutoff = time() - 3600 * 24 * $days;
        $cutoffdate = date(FMT_DATETIME, $cutoff);

        $builds = pdo_query(
            "SELECT build.id AS id FROM build, build2group
                WHERE build.parentid IN (0, -1) AND
                build.starttime<'" . $cutoffdate . "' AND
                build2group.buildid=build.id AND
                build2group.groupid=" . qnum($groupid) .
            "ORDER BY build.starttime ASC LIMIT $maxbuilds");
        add_last_sql_error('autoremove::removeBuildsGroupwise');

        while ($build = pdo_fetch_array($builds)) {
            $buildids[] = $build['id'];
        }
    }

    $s = 'removing old buildids for projectid: ' . $projectid;
    add_log($s, 'removeBuildsGroupwise');
    echo '  -- ' . $s . "\n";
    remove_build($buildids);
}

/** Remove the first builds that are at the beginning of the queue */
function removeFirstBuilds($projectid, $days, $maxbuilds, $force = false)
{
    require 'config/config.php';
    require_once 'include/pdo.php';
    require_once 'include/common.php';

    @set_time_limit(0);

    if (!$force && !isset($CDASH_AUTOREMOVE_BUILDS)) {
        return;
    }

    if (!$force && $CDASH_AUTOREMOVE_BUILDS != '1') {
        return;
    }

    if ($days < 2) {
        return;
    }

    // First remove the builds with the wrong date
    $currentdate = time() - 3600 * 24 * $days;
    $startdate = date(FMT_DATETIME, $currentdate);

    add_log('about to query for builds to remove', 'removeFirstBuilds');
    $builds = pdo_query(
        "SELECT id FROM build
            WHERE parentid IN (0, -1) AND
            starttime<'$startdate' AND
            projectid=" . qnum($projectid) . "
            ORDER BY starttime ASC LIMIT $maxbuilds");
    add_last_sql_error('dailyupdates::removeFirstBuilds');

    $buildids = array();
    while ($builds_array = pdo_fetch_array($builds)) {
        $buildids[] = $builds_array['id'];
    }

    $s = 'removing old buildids for projectid: ' . $projectid;
    add_log($s, 'removeFirstBuilds');
    echo '  -- ' . $s . "\n"; // for "interactive" command line feedback
    remove_build($buildids);

    // Remove any job schedules that are older than our cutoff date
    // and not due to repeat again.
    require_once 'models/constants.php';
    $sql =
        'SELECT scheduleid FROM client_job AS cj
    LEFT JOIN client_jobschedule AS cjs ON cj.scheduleid = cjs.id
    WHERE cj.status > ' . Job::RUNNING . "
    AND cjs.projectid=$projectid AND cj.startdate < '$startdate'
    AND (cjs.repeattime = 0.00 OR
      (cjs.enddate < '$startdate' AND cjs.enddate != '1980-01-01 00:00:00'))";

    $job_schedules = pdo_query($sql);
    while ($job_schedule = pdo_fetch_array($job_schedules)) {
        $ClientJobSchedule = new ClientJobSchedule();
        $ClientJobSchedule->Id = $job_schedule['scheduleid'];
        $ClientJobSchedule->Remove();
    }

    // Remove any jobs that are older than our cutoff date.
    // This occurs when a job schedule is set to continue repeating, but
    // some of its past runs are older than our autoremove threshold.

    $sql =
        'SELECT cj.id FROM client_job AS cj
    LEFT JOIN client_jobschedule AS cjs ON cj.scheduleid = cjs.id
    WHERE cj.status > ' . Job::RUNNING . "
    AND cjs.projectid=$projectid AND cj.startdate < '$startdate'";

    $jobs = pdo_query($sql);
    while ($job = pdo_fetch_array($jobs)) {
        $ClientJob = new ClientJob();
        $ClientJob->Id = $job['id'];
        $ClientJob->Remove();
    }
}
