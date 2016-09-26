<html>
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

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';

$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$siteid = $build_array['siteid'];
$starttime = $build_array['starttime'];
$projectid = $build_array['projectid'];

$project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);

$buildfailing = pdo_num_rows(pdo_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='0'"));
$testfailing = pdo_num_rows(pdo_query("SELECT buildid FROM build2test WHERE buildid='$buildid' AND status='failed'"));

if ($buildfailing) {
    // Find the last build that have no error
    $cleanbuild = pdo_query("SELECT starttime FROM build
                           WHERE id NOT IN
                                 (SELECT b.id FROM build AS b, builderror AS e WHERE b.siteid='$siteid' AND b.type='$buildtype' AND b.name='$buildname' AND
                                  e.buildid=b.id AND b.projectid='$projectid' AND b.starttime<='$starttime' AND e.type='0')
                           AND siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                           AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime DESC LIMIT 1");

    if (pdo_num_rows($cleanbuild) > 0) {
        $cleanbuild_array = pdo_fetch_array($cleanbuild);
        $gmtdate = strtotime($cleanbuild_array['starttime'] . ' UTC');
        $datefirstbuildfailing = date(FMT_DATETIMETZ, $gmtdate);
    } else {
        // Find the first build
        $firstbuild = pdo_query("SELECT starttime FROM build
                            WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                            AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC LIMIT 1");
        $firstbuild_array = pdo_fetch_array($firstbuild);
        $gmtdate = strtotime($firstbuild_array['starttime'] . ' UTC');
        $datefirstbuildfailing = date(FMT_DATETIMETZ, $gmtdate);
    }

    $buildfailingdays = round((strtotime($starttime) - $gmtdate) / (3600 * 24));
} // end build failing

if ($testfailing) {
    // Find the last test that have no error
    $cleanbuild = pdo_query("SELECT starttime FROM build
                           WHERE id NOT IN
                                 (SELECT b.id FROM build AS b, build2test AS t WHERE b.siteid='$siteid' AND b.type='$buildtype' AND b.name='$buildname' AND
                                  t.buildid=b.id AND b.projectid='$projectid' AND b.starttime<='$starttime' AND t.status='failed')
                           AND siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                           AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime DESC LIMIT 1");

    echo pdo_error();

    if (pdo_num_rows($cleanbuild) > 0) {
        $cleanbuild_array = pdo_fetch_array($cleanbuild);
        $gmtdate = strtotime($cleanbuild_array['starttime'] . ' UTC');
        $datefirsttestfailing = date(FMT_DATETIMETZ, $gmtdate);
    } else {
        // Find the first build
        $firstbuild = pdo_query("SELECT starttime FROM build
                            WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                            AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC LIMIT 1");
        $firstbuild_array = pdo_fetch_array($firstbuild);
        $gmtdate = strtotime($firstbuild_array['starttime'] . ' UTC');
        $datefirsttestfailing = date(FMT_DATETIMETZ, $gmtdate);
    }

    $testfailingdays = round((strtotime($starttime) - $gmtdate) / (3600 * 24));
} // end build failing

?>
<table width="100%" border="0">
    <?php if ($buildfailing) {
    ?>
        <tr>
            <td bgcolor="#DDDDDD" id="nob"><font size="2">Build has been failing since <b>
                        <?php
                        if ($buildfailingdays > 1) {
                            $date = date2year($datefirstbuildfailing) . date2month($datefirstbuildfailing) . date2day($datefirstbuildfailing);
                            echo '<a href="index.php?project=' . urlencode($project_array['name']) . '&date=' . $date . '">' . $datefirstbuildfailing . '</a> (' . $buildfailingdays . ' days)';
                        } elseif ($buildfailingdays == 1) {
                            $date = date2year($datefirstbuildfailing) . date2month($datefirstbuildfailing) . date2day($datefirstbuildfailing);
                            echo '<a href="index.php?project=' . urlencode($project_array['name']) . '&date=' . $date . '">' . $datefirstbuildfailing . '</a> (' . $buildfailingdays . ' day)';
                        } else {
                            echo $datefirstbuildfailing . ' (today)';
                        } ?>
                    </b></font></td>
        </tr>
        <?php

} // end buildfailing?>

    <?php if ($testfailing) {
    ?>
        <tr>
            <td bgcolor="#DDDDDD" id="nob"><font size="2">Tests have been failing since <b>
                        <?php
                        if ($testfailingdays > 1) {
                            $date = date2year($datefirsttestfailing) . date2month($datefirsttestfailing) . date2day($datefirsttestfailing);
                            echo '<a href="index.php?project=' . urlencode($project_array['name']) . '&date=' . $date . '">' . $datefirsttestfailing . '</a> (' . $testfailingdays . ' days)';
                        } elseif ($testfailingdays == 1) {
                            echo $datefirsttestfailing . ' (' . $testfailingdays . ' day)';
                        } else {
                            echo $datefirsttestfailing . ' (today)';
                        } ?>
                    </b></font></td>
        </tr>
        <?php

} ?>


</table>
</html>
