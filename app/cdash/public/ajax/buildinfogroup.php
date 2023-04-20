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
require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';

use CDash\Database;

$buildid = $_GET['buildid'];
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}
$buildid = intval($buildid);

$db = Database::getInstance();

// Find the project variables
$build = $db->executePreparedSingleRow('SELECT name, type, siteid, projectid, starttime FROM build WHERE id=?', [intval($buildid)]);

$buildtype = $build['type'];
$buildname = $build['name'];
$siteid = intval($build['siteid']);
$starttime = $build['starttime'];
$projectid = intval($build['projectid']);

if (!can_access_project($projectid)) {
    return 'You do not have permission to view this project.';
}

$project = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=?', [$projectid]);

$buildfailing = intval($db->executePreparedSingleRow("
                           SELECT COUNT(*) AS c
                           FROM builderror
                           WHERE buildid=? AND type='0'
                       ", [$buildid])['c']) > 0;
$testfailing = intval($db->executePreparedSingleRow("
                          SELECT COUNT(*) AS c
                          FROM build2test
                          WHERE buildid=? AND status='failed'
                      ", [$buildid])['c']) > 0;

if ($buildfailing) {
    // Find the last build that has no error
    $cleanbuild = $db->executePreparedSingleRow("
                      SELECT starttime
                      FROM build
                      WHERE
                          id NOT IN (
                              SELECT b.id
                              FROM build AS b, builderror AS e
                              WHERE
                                  b.siteid=?
                                  AND b.type=?
                                  AND b.name=?
                                  AND e.buildid=b.id
                                  AND b.projectid=?
                                  AND b.starttime<=?
                                  AND e.type='0'
                          )
                          AND siteid=?
                          AND type=?
                          AND name=?
                          AND projectid=?
                          AND starttime<=?
                      ORDER BY starttime DESC
                      LIMIT 1
                  ", [
                      $siteid,
                      $buildtype,
                      $buildname,
                      $projectid,
                      $starttime,
                      $siteid,
                      $buildtype,
                      $buildname,
                      $projectid,
                      $starttime
                 ]);

    if (!empty($cleanbuild)) {
        $gmtdate = strtotime($cleanbuild['starttime'] . ' UTC');
        $datefirstbuildfailing = date(FMT_DATETIMETZ, $gmtdate);
    } else {
        // Find the first build
        $firstbuild = $db->executePreparedSingleRow('
                          SELECT starttime
                          FROM build
                          WHERE
                              siteid=?
                              AND type=?
                              AND name=?
                              AND projectid=?
                              AND starttime<=?
                          ORDER BY starttime ASC
                          LIMIT 1
                      ', [$siteid, $buildtype, $buildname, $projectid, $starttime]);
        $gmtdate = strtotime($firstbuild['starttime'] . ' UTC');
        $datefirstbuildfailing = date(FMT_DATETIMETZ, $gmtdate);
    }

    $buildfailingdays = round((strtotime($starttime) - $gmtdate) / (3600 * 24));
} // end build failing

if ($testfailing) {
    // Find the last test that have no error
    $cleanbuild = $db->executePreparedSingleRow("
                      SELECT starttime
                      FROM build
                      WHERE
                          id NOT IN (
                              SELECT b.id
                              FROM build AS b, build2test AS t
                              WHERE
                                  b.siteid=?
                                  AND b.type=?
                                  AND b.name=?
                                  AND t.buildid=b.id
                                  AND b.projectid=?
                                  AND b.starttime<=?
                                  AND t.status='failed'
                          )
                          AND siteid=?
                          AND type=?
                          AND name=?
                          AND projectid=?
                          AND starttime<=?
                      ORDER BY starttime DESC
                      LIMIT 1
                  ", [
                      $siteid,
                      $buildtype,
                      $buildname,
                      $projectid,
                      $starttime,
                      $siteid,
                      $buildtype,
                      $buildname,
                      $projectid,
                      $starttime
                  ]);

    echo pdo_error();

    if (!empty($cleanbuild)) {
        $gmtdate = strtotime($cleanbuild['starttime'] . ' UTC');
        $datefirsttestfailing = date(FMT_DATETIMETZ, $gmtdate);
    } else {
        // Find the first build
        $firstbuild = $db->executePreparedSingleRow('
                          SELECT starttime
                          FROM build
                          WHERE
                              siteid=?
                              AND type=?
                              AND name=?
                              AND projectid=?
                              AND starttime<=?
                          ORDER BY starttime ASC
                          LIMIT 1
                      ', [$siteid, $buildtype, $buildname, $projectid, $starttime]);
        $gmtdate = strtotime($firstbuild['starttime'] . ' UTC');
        $datefirsttestfailing = date(FMT_DATETIMETZ, $gmtdate);
    }

    $testfailingdays = round((strtotime($starttime) - $gmtdate) / (3600 * 24));
} // end build failing

?>
<table width="100%" border="0">
    <?php if ($buildfailing) { ?>
        <tr>
            <td bgcolor="#DDDDDD" id="nob"><font size="2">Build has been failing since <b>
                <?php
                    if ($buildfailingdays > 1) {
                        $date = date2year($datefirstbuildfailing) . date2month($datefirstbuildfailing) . date2day($datefirstbuildfailing);
                        echo '<a href="index.php?project=' . urlencode($project['name']) . '&date=' . $date . '">' . $datefirstbuildfailing . '</a> (' . $buildfailingdays . ' days)';
                    } elseif ($buildfailingdays == 1) {
                        $date = date2year($datefirstbuildfailing) . date2month($datefirstbuildfailing) . date2day($datefirstbuildfailing);
                        echo '<a href="index.php?project=' . urlencode($project['name']) . '&date=' . $date . '">' . $datefirstbuildfailing . '</a> (' . $buildfailingdays . ' day)';
                    } else {
                        echo $datefirstbuildfailing . ' (today)';
                    } ?>
            </b></font></td>
        </tr>
    <?php
    } // end buildfailing
    if ($testfailing) { ?>
        <tr>
            <td bgcolor="#DDDDDD" id="nob"><font size="2">Tests have been failing since <b>
                <?php
                if ($testfailingdays > 1) {
                    $date = date2year($datefirsttestfailing) . date2month($datefirsttestfailing) . date2day($datefirsttestfailing);
                    echo '<a href="index.php?project=' . urlencode($project['name']) . '&date=' . $date . '">' . $datefirsttestfailing . '</a> (' . $testfailingdays . ' days)';
                } elseif ($testfailingdays == 1) {
                    echo $datefirsttestfailing . ' (' . $testfailingdays . ' day)';
                } else {
                    echo $datefirsttestfailing . ' (today)';
                } ?>
            </b></font></td>
        </tr>
    <?php } ?>
</table>
