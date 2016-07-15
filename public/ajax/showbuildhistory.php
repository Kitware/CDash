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
$build = pdo_query("SELECT name,type,siteid,projectid,starttime
                    FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$siteid = $build_array['siteid'];
$starttime = $build_array['starttime'];
$projectid = $build_array['projectid'];

// Find the other builds
$previousbuilds = pdo_query("SELECT build.id,build.starttime,build.endtime,build.builderrors,
                             build.buildwarnings,build.testfailed,
                             buildupdate.status as updatestatus,
                             buildupdate.warnings AS updatewarnings,
                             buildupdate.nfiles,
                             configure.status AS configurestatus,
                             configure.warnings AS configurewarnings
                             FROM build
                             JOIN build2update ON (build2update.buildid=build.id)
                             JOIN buildupdate ON (build2update.updateid=buildupdate.id)
                             JOIN configure ON (configure.buildid=build.id)
                             WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                             AND build.projectid='$projectid' AND build.starttime<='$starttime'
                             ORDER BY build.starttime DESC LIMIT 50");
?>
<table width="100%" border="0">
    <tr class="table-heading">
        <th>
            <center>Date</center>
        </th>
        <th>
            <center>Update Files</center>
        </th>
        <th>
            <center>Update Errors</center>
        </th>
        <th>
            <center>Update Warnings</center>
        </th>
        <th>
            <center>Configure Errors</center>
        </th>
        <th>
            <center>Configure Warnings</center>
        </th>
        <th>
            <center>Build Errors</center>
        </th>
        <th>
            <center>Build Warnings</center>
        </th>
        <th>
            <center>Tests Failed</center>
        </th>
    </tr>
    <?php
    $i = 0;
    while ($build_array = pdo_fetch_array($previousbuilds)) {
        $updateerrors = $build_array['updatestatus'];
        if ($updateerrors == 0) {
            $updateerrors = 0;
        }
        $updatewarnings = $build_array['updatewarnings'];
        if ($updatewarnings == 0) {
            $updatewarnings = 0;
        }
        $configureerrors = $build_array['configurestatus'];
        if ($configureerrors == 0) {
            $configureerrors = 0;
        }
        $configurewarnings = $build_array['configurewarnings'];
        if ($configurewarnings == 0) {
            $configurewarnings = 0;
        }

        $builderrors = $build_array['builderrors'];
        if ($builderrors == 0) {
            $builderrors = 0;
        }
        $buildwarnings = $build_array['buildwarnings'];
        if ($buildwarnings == 0) {
            $buildwarnings = 0;
        }
        $testfailed = $build_array['testfailed'];
        if ($testfailed == 0) {
            $testfailed = 0;
        } ?>
        <tr>
            <td>
                <center>
                    <?php
                    if ($i > 0) {
                        // Don't link the current build

                    ?>
                    <a href="buildSummary.php?buildid=<?php echo $build_array['id'] ?>">
                        <?php

                    }
        echo date('Y-m-d H:i:d', strtotime($build_array['starttime']));
        if ($i > 0) {
            echo '</a>';
        } ?>
                </center>
            </td>
            <td>
                <center><?php echo $build_array['nfiles']; ?></center>
            </td>
            <td class=<?php if ($updateerrors > 0) {
            echo 'error';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $updateerrors; ?></center>
            </td>
            <td class=<?php if ($updatewarnings > 0) {
            echo 'warning';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $updatewarnings; ?></center>
            </td>
            <td class=<?php if ($configureerrors > 0) {
            echo 'error';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $configureerrors; ?></center>
            </td>
            <td class=<?php if ($configurewarnings > 0) {
            echo 'warning';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $configurewarnings; ?></center>
            </td>
            <td class=<?php if ($builderrors > 0) {
            echo 'error';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $builderrors; ?></center>
            </td>
            <td class=<?php if ($buildwarnings > 0) {
            echo 'warning';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $buildwarnings; ?></center>
            </td>
            <td class=<?php if ($testfailed > 0) {
            echo 'error';
        } else {
            echo 'normal';
        } ?>>
                <center><?php echo $testfailed; ?></center>
            </td>
        </tr>
        <?php
        $i++;
    }
    ?>
</table>
