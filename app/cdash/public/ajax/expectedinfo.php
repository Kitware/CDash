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

use CDash\Database;

require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';

$siteid = $_GET['siteid'];
$buildname = htmlspecialchars($_GET['buildname']);
$projectid = $_GET['projectid'];
$buildtype = htmlspecialchars($_GET['buildtype']);
$currenttime = htmlspecialchars($_GET['currenttime']);

// Checks
if (!isset($siteid) || !is_numeric($siteid)) {
    echo 'Not a valid siteid!';
    return;
}
if (!isset($projectid) || !is_numeric($projectid) || !can_access_project($projectid)) {
    echo 'Not a valid projectid!';
    return;
}

$siteid = intval($siteid);
$projectid = intval($projectid);

$db = Database::getInstance();

$project = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=?', [$projectid]);

$currentUTCtime = gmdate(FMT_DATETIME, $currenttime);

// Find the last build corresponding to thie siteid and buildid
$lastbuild = $db->executePreparedSingleRow('
                 SELECT starttime
                 FROM build
                 WHERE
                     siteid=?
                     AND type=?
                     AND name=?
                     AND projectid=?
                     AND starttime<=?
                 ORDER BY starttime DESC
                 LIMIT 1
             ', [$siteid, $buildtype, $buildname, $projectid, $currentUTCtime]);

if ($lastbuild) {
    $datelastbuild = $lastbuild['starttime'];
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
                    echo 'This build has not been submitting since <b><a href="index.php?project=' . urlencode($project['name']) . '&date=' . $date . '">' . date('M j, Y ', strtotime($datelastbuild)) . '</a> (' . $lastsbuilddays . ' days)</b>';
                }
?>
            </font></td>
    </tr>
</table>
