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

use CDash\Config;

/** Remove builds by their group-specific auto-remove timeframe setting */
function removeBuildsGroupwise($projectid, $maxbuilds, $force = false)
{
    require_once 'include/pdo.php';
    require_once 'include/common.php';

    $config = Config::getInstance();

    if (!$force && !$config->get('CDASH_AUTOREMOVE_BUILDS')) {
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
function removeFirstBuilds($projectid, $days, $maxbuilds, $force = false, $echo = true)
{
    require_once 'include/pdo.php';
    require_once 'include/common.php';

    @set_time_limit(0);
    $config = Config::getInstance();
    $remove_builds = $config->get('CDASH_AUTOREMOVE_BUILDS');
    if (!$force && !$remove_builds) {
        return;
    }

    if (!$force && $remove_builds != '1') {
        return;
    }

    if (!$force && $days < 2) {
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
    if ($echo) {
        echo '  -- ' . $s . "\n"; // for "interactive" command line feedback
    }
    remove_build($buildids);
}
