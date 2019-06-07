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

$siteid = pdo_real_escape_numeric($_GET['siteid']);
$buildname = htmlspecialchars(pdo_real_escape_string($_GET['buildname']));
$projectid = pdo_real_escape_numeric($_GET['projectid']);
$buildtype = htmlspecialchars(pdo_real_escape_string($_GET['buildtype']));
$currenttime = htmlspecialchars(pdo_real_escape_string($_GET['currenttime']));

// Checks
if (!isset($siteid) || !is_numeric($siteid)) {
    echo 'Not a valid siteid!';
    return;
}
if (!isset($projectid) || !is_numeric($projectid)) {
    echo 'Not a valid projectid!';
    return;
}

$project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);

$currentUTCtime = gmdate(FMT_DATETIME, $currenttime);

// Find the last build corresponding to thie siteid and buildid
$lastbuild = pdo_query("SELECT starttime FROM build
                          WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                          AND projectid='$projectid' AND starttime<='$currentUTCtime' ORDER BY starttime DESC LIMIT 1");

if (pdo_num_rows($lastbuild) > 0) {
    $lastbuild_array = pdo_fetch_array($lastbuild);
    $datelastbuild = $lastbuild_array['starttime'];
    $lastsbuilddays = round(($currenttime - strtotime($datelastbuild)) / (3600 * 24));
} else {
    $lastsbuilddays = -1;
}
?>
<table width="100%" border="0">
    <tr>
        <td bgcolor="#DDDDDD" id="nob"><font size="2">
                <?php
                if ($lastsbuilddays == -1) {
                    echo 'This build has never submitted.';
                } elseif ($lastsbuilddays >= 0) {
                    $date = date2year($datelastbuild) . date2month($datelastbuild) . date2day($datelastbuild);
                    echo 'This build has not been submitting since <b><a href="index.php?project=' . urlencode($project_array['name']) . '&date=' . $date . '">' . date('M j, Y ', strtotime($datelastbuild)) . '</a> (' . $lastsbuilddays . ' days)</b>';
                }
                ?>
            </font></td>
    </tr>
</table>
